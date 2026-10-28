<?php
/**
 * Plugin Name: 导航栏固定
 * Version: 1.0.0
 * Description: 顶部导航固定（滚动时不消失）
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
    if (fly_plugin_opt('sticky-nav', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.site-head{position:sticky;top:0;z-index:50;backdrop-filter:blur(8px);background:rgba(255,255,255,.92)}
[data-theme="dark"] .site-head{background:rgba(12,18,32,.92)}
FLYCSS . '</style>';
});
