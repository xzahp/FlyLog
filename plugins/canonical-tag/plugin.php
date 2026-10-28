<?php
/**
 * Plugin Name: Canonical 规范链接
 * Version: 1.0.0
 * Description: 输出规范链接，防止重复内容被搜索引擎判重
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('canonical-tag', 'enable', '1') !== '1') { return; }
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    echo '<link rel="canonical" href="' . fly_e(rtrim(fly_base_url(), '/') . $uri) . '">';
});
