<?php
/**
 * Plugin Name: 后台地址自定义
 * Version: 1.0.0
 * Description: 把 /admin 换成一个自定义路径（配置后旧地址自动 404）
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
    $alias = (string)fly_plugin_opt('custom-admin-path', 'alias', '');
    if ($alias === '') { return; }
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    // 访问旧 /admin 时直接 404（不泄露后台存在）
    if (preg_match('#/admin(/|$|\?)#', $uri)) {
        http_response_code(404);
        exit('Not Found');
    }
});
