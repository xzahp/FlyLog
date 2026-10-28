<?php
/**
 * Plugin Name: 浏览器统计
 * Version: 1.0.0
 * Description: 统计 Chrome / Edge / Safari / Firefox 占比
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('browser-stats', 'enable', '1') !== '1') { return; }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $b = 'Other';
    foreach (array('Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari') as $k => $v) {
        if (stripos($ua, $k) !== false) { $b = $v; break; }
    }
    $key = 'stat_browser_' . $b;
    fly_set_option($key, (string)((int)fly_opt($key, 0) + 1));
});
