<?php
/**
 * Plugin Name: 自定义字体
 * Version: 1.0.0
 * Description: 上传 Web 字体替换默认字体
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
    if (fly_plugin_opt('custom-font', 'enable', '1') !== '1') { return; }
    $url = (string)fly_plugin_opt('custom-font', 'url', '');
    $name = (string)fly_plugin_opt('custom-font', 'name', 'FlyCustomFont');
    if ($url === '') { return; }
    echo '<style>@font-face{font-family:"' . str_replace('"', '', $name) . '";src:url(' . str_replace("'", "", $url) . ')}'
        . 'body{font-family:"' . str_replace('"', '', $name) . '",system-ui,sans-serif}</style>';
});
