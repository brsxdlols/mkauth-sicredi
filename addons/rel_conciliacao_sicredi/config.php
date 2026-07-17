<?php
if (file_exists(__DIR__ . '/addons.class.php')) {
    include_once(__DIR__ . '/addons.class.php');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('mka');
    session_start();
}

$logged = !empty($_SESSION['mka_logado'])
    || !empty($_SESSION['MKA_Logado'])
    || !empty($_SESSION['MM_Usuario'])
    || !empty($_SESSION['MKA_Usuario'])
    || !empty($_SESSION['usuario'])
    || !empty($_SESSION['login'])
    || !empty($_SESSION['nome']);

$ext_mk = file_exists("../../login.hhvm") ? '.hhvm' : '.php';

if (!$logged && empty($_SESSION) && $ext_mk === '.php') {
    exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');
}

$Manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'));
$link = mysqli_connect('127.0.0.1', 'root', 'vertrigo', 'mkradius');

if (!$link) {
    exit('Falha ao conectar no banco: ' . mysqli_connect_error());
}

mysqli_set_charset($link, 'utf8');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_br($value)
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function date_br($value, $withTime = false)
{
    if (!$value) {
        return '';
    }

    $format = $withTime ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($value));
}
