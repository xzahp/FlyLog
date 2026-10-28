<?php
/**
 * Plugin Name: 自定义 Logo
 * Version: 1.0.0
 * Description: 用图片 Logo 替换站点文字标题
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('custom-logo', 'enable', '1') !== '1') { return; }
    $url = (string)fly_plugin_opt('custom-logo', 'url', '');
    if ($url === '') { return; }
    echo '<style>.site-head .brand-text{display:none}'
        . '.site-head .brand{background:url(' . str_replace("'", "", $url) . ') no-repeat left center;'
        . 'background-size:contain;min-width:120px;min-height:36px}</style>';
});
