<?php
/**
 * Plugin Name: 网站副标题
 * Version: 1.0.0
 * Description: 在站点标题下方显示副标题
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
    if (fly_plugin_opt('site-subtitle', 'enable', '1') !== '1') { return; }
    $sub = (string)fly_plugin_opt('site-subtitle', 'text', '');
    if ($sub === '') { return; }
    echo '<style>.site-head .brand::after{content:"' . str_replace('"', '', $sub) . '";'+
        'display:block;font-size:12px;font-weight:400;color:var(--text-2);margin-top:2px}</style>';
});
