<?php
/**
 * Plugin Name: 文件下载统计
 * Version: 1.0.0
 * Description: 记录每个附件的下载次数
 * Author: FlyLog
 * Category: 媒体
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('download-counter', 'enable', '1') !== '1') { return; }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'file.php') { return; }
    $f = isset($_GET['f']) ? basename((string)$_GET['f']) : '';
    if ($f === '') { return; }
    $key = 'dl_count_' . md5($f);
    $n = (int)fly_cache_get($key, 86400 * 30);
    fly_cache_set($key, $n + 1, 86400 * 30);
});
