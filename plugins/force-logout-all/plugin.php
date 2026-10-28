<?php
/**
 * Plugin Name: 强制退出所有设备
 * Version: 1.0.0
 * Description: 改密码后使所有旧会话失效（改密即踢下线）
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('post_saved', function () { }, 10);   // 占位：真正的失效由下面的 token 校验完成
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('force-logout-all', 'enable', '1') !== '1') { return; }
    if (empty($_SESSION['fly_admin_id'])) { return; }
    $token = (string)fly_opt('admin_session_token', '');
    if ($token === '') { return; }
    if (($_SESSION['fly_sess_token'] ?? '') !== $token) {
        session_destroy();
        fly_redirect(fly_base_url() . 'admin/login.php');
    }
});
