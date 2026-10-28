<?php
/**
 * Plugin Name: 设备统计
 * Version: 1.0.0
 * Description: 统计 PC / 手机 / 平板访问占比
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
    if (fly_plugin_opt('device-stats', 'enable', '1') !== '1') { return; }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $dev = 'PC';
    if (preg_match('/Mobile|Android|iPhone/i', $ua)) { $dev = 'Mobile'; }
    elseif (preg_match('/Tablet|iPad/i', $ua)) { $dev = 'Tablet'; }
    $key = 'stat_dev_' . $dev;
    fly_set_option($key, (string)((int)fly_opt($key, 0) + 1));
});
