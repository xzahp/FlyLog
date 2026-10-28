<?php
/**
 * Plugin Name: 访问热力图
 * Version: 1.0.0
 * Description: 按小时统计访问密度（后台简易热力条）
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
    if (fly_plugin_opt('visit-heatmap', 'enable', '1') !== '1') { return; }
    if (php_sapi_name() === 'cli') { return; }
    $key = 'heat_' . date('Y-m-d') . '_' . date('H');
    fly_cache_set($key, (int)fly_cache_get($key, 86400) + 1, 86400);
});
