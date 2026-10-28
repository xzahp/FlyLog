<?php
/**
 * Plugin Name: 站点公告
 * Version: 1.0.0
 * Description: 前台顶部滚动显示站点公告
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


fly_add_action('fly_head', function () {
    if (fly_plugin_opt('site-announcement', 'enable', '1') !== '1') { return; }
    $text = (string)fly_plugin_opt('site-announcement', 'text', '');
    if ($text === '') { return; }
    echo '<style>.fly-notice{background:linear-gradient(90deg,#0a2a5e,#1f6feb);color:#fff;'
       . 'padding:8px 0;overflow:hidden;white-space:nowrap}.fly-notice span{display:inline-block;'
       . 'padding-left:100%;animation:flyMarquee 18s linear infinite}'
       . '@keyframes flyMarquee{from{transform:translateX(0)}to{transform:translateX(-100%)}}</style>';
});
fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('site-announcement', 'enable', '1') !== '1') { return; }
    $text = (string)fly_plugin_opt('site-announcement', 'text', '');
    if ($text === '') { return; }
    echo '<div class="fly-notice" style="position:fixed;bottom:0;left:0;right:0;z-index:70"><span>'
        . fly_e($text) . '</span></div>';
});
