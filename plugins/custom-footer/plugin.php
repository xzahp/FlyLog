<?php
/**
 * Plugin Name: 自定义页脚
 * Version: 1.0.0
 * Description: 自定义页脚版权信息与备案号
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('custom-footer', 'enable', '1') !== '1') { return; }
    $txt = (string)fly_plugin_opt('custom-footer', 'text', '');
    if ($txt === '') { return; }
    echo '<p style="text-align:center;color:var(--text-2);font-size:13px;margin-top:10px">'
        . fly_e($txt) . '</p>';
});
