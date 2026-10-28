<?php
/**
 * Plugin Name: 记住我（30天）
 * Version: 1.0.0
 * Description: 加密 Token 免登录 30 天
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('remember-me', 'enable', '1') !== '1') { return; }
    if (!empty($_SESSION['fly_admin_id'])) { return; }
    $cookie = (string)($_COOKIE['fly_remember'] ?? '');
    if ($cookie === '' || strpos($cookie, ':') === false) { return; }
    list($uid, $sig) = explode(':', $cookie, 2);
    $secret = (string)fly_opt('preview_secret', '');
    if ($secret === '') { return; }
    $expect = hash_hmac('sha256', 'remember' . $uid, $secret);
    if (hash_equals($expect, $sig)) {
        $_SESSION['fly_admin_id'] = (int)$uid;
    }
});
