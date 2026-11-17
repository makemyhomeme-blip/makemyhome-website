<?php
require_once __DIR__ . '/sesija.php';
$_SESSION = [];
// session_destroy sam po sebi ne brise kolacic kod posjetioca
if (ini_get('session.use_cookies')) {
    $k = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $k['path'], $k['domain'], $k['secure'], $k['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
