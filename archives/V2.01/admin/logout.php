<?php
/** FlyLog V2.0 退出登录 */
require __DIR__ . '/../config.php';
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
fly_redirect('login.php');
