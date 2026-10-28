<?php
/**
 * Plugin Name: 内存占用峰值
 * Version: 1.0.0
 * Description: 把每次请求的内存峰值写入日志（排障用）
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
    if (fly_plugin_opt('memory-peak', 'enable', '0') !== '1') { return; }
    register_shutdown_function(function () {
        fly_log('MEM ' . number_format(memory_get_peak_usage(true) / 1048576, 2) . 'MB '
            . (string)($_SERVER['REQUEST_URI'] ?? '-'), 'mem');
    });
});
