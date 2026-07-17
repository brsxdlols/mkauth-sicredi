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

function date_br($value, $withTime = false)
{
    if (!$value) {
        return '';
    }

    return date($withTime ? 'd/m/Y H:i:s' : 'd/m/Y', strtotime($value));
}

function money_br($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function payload_value($payload, $key)
{
    return is_array($payload) && array_key_exists($key, $payload) ? $payload[$key] : '';
}

function payload_date($value)
{
    if (is_array($value) && count($value) >= 3) {
        $hour = $value[3] ?? 0;
        $minute = $value[4] ?? 0;
        $second = $value[5] ?? 0;
        return sprintf('%02d/%02d/%04d %02d:%02d:%02d', $value[2], $value[1], $value[0], $hour, $minute, $second);
    }

    if (is_string($value) && $value !== '') {
        $time = strtotime($value);
        return $time ? date('d/m/Y H:i:s', $time) : $value;
    }

    return '';
}

function pretty_json($value)
{
    $decoded = json_decode((string) $value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return (string) $value;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
