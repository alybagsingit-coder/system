<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_post();
verify_csrf();

$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => 'Lax',
]);
session_destroy();

header('Location: login.php?logged_out=1');
exit;
