<?php
/**
 * Plugin Name: CSS 变量调色盘
 * Version: 1.0.0
 * Description: 后台改主色/强调色，自动生成 CSS 变量
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
    if (fly_plugin_opt('css-variable-palette', 'enable', '1') !== '1') { return; }
    $brand = (string)fly_plugin_opt('css-variable-palette', 'brand', '');
    $accent = (string)fly_plugin_opt('css-variable-palette', 'accent', '');
    if ($brand === '' && $accent === '') { return; }
    echo '<style>:root{';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $brand)) { echo '--brand:' . $brand . ';'; }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) { echo '--accent:' . $accent . ';'; }
    echo '}</style>';
});
