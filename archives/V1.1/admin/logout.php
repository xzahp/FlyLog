<?php
/**
 * FlyLog 后台退出登录
 * 清除 session 并跳回登录页
 * V1.01：与登录页使用相同的 cookie 参数，确保会话 cookie 能被彻底清除
 */
require_once __DIR__ . '/../config.php';
// V1.01：与登录页保持一致的 cookie 参数
session_set_cookie_params(fly_session_cookie_params());
session_start();

$_SESSION = array();

// 同时清除会话 cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', array(
        'expires' => time() - 42000,
        'path' => $p['path'],
        'domain' => $p['domain'],
        'secure' => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => isset($p['samesite']) ? $p['samesite'] : 'Lax',
    ));
}

session_destroy();

header('Location: login.php');
exit;
