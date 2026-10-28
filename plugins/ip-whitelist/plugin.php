<?php
/**
 * Plugin Name: IP 白名单
 * Version: 1.0.0
 * Description: 只允许指定 IP 访问后台
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
    if (fly_plugin_opt('ip-whitelist', 'enable', '0') !== '1') { return; }
    $list = (string)fly_plugin_opt('ip-whitelist', 'ips', '');
    $ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $list)));
    if (!$ips) { return; }
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($uri, '/admin/') === false) { return; }
    if (!in_array(fly_client_ip(), $ips, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
});
