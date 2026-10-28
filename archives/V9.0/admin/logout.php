<?php
/**
 * FlyLog V6.5 退出登录
 * 同时作废「记住我」长期令牌（清库 + 清 cookie），避免登出后旧 cookie 仍能进门。
 */
require __DIR__ . '/../config.php';

// 先取出管理员 id，再销毁会话
$adminId = isset($_SESSION['fly_admin_id']) ? (int)$_SESSION['fly_admin_id'] : 0;

if ($adminId > 0) {
    try {
        fly_db()->update(
            'admins',
            array('remember_token' => null, 'remember_expire' => null),
            'id = ?',
            array($adminId)
        );
    } catch (Exception $e) {
        // 列还没迁移出来时忽略：会话照样销毁，只是库里没东西可清
    }
}

// 清「记住我」cookie
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('flylog_admin_rm', '', time() - 3600, '/', '', $secure, true);

$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
fly_redirect('login.php');
