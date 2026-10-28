<?php
/**
 * Plugin Name: 搜索词统计
 * Version: 1.0.0
 * Description: 记录访客在站内搜索了什么词
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
    if (fly_plugin_opt('search-keyword-stats', 'enable', '1') !== '1') { return; }
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if ($q === '' || fly_len($q) > 50) { return; }
    $key = 'stat_kw_' . md5($q);
    fly_set_option($key, (string)((int)fly_opt($key, 0) + 1));
    fly_set_option('stat_kw_name_' . md5($q), $q);
});
