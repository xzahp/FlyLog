<?php
/**
 * FlyLog 后台退出登录
 * 清除 session 并跳回登录页
 */
session_start();

$_SESSION = array();

// 同时清除会话 cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
