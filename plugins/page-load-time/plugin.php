<?php
/**
 * Plugin Name: 页面加载耗时
 * Version: 1.0.0
 * Description: 在页脚显示本页生成耗时与内存峰值
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('page-load-time', 'enable', '1') !== '1') { return; }
    if (!defined('FLY_START')) { return; }
    $cost = number_format(microtime(true) - FLY_START, 3);
    $mem = number_format(memory_get_peak_usage(true) / 1048576, 1);
    echo '<p style="text-align:center;color:#9ca3af;font-size:12px;margin:18px 0 0">'
        . '本页耗时 ' . $cost . 's · 内存 ' . $mem . 'MB</p>';
});
