<?php
require_once __DIR__ . '/config.php';

unset($_SESSION['doador_id']);

setcookie('LF_DOADOR', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

redirecionar('/index.php');
