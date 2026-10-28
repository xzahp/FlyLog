<?php
/**
 * Plugin Name: 文章元信息显隐
 * Version: 1.0.0
 * Description: 控制文章页是否显示作者/阅读量/点赞数
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
    if (fly_plugin_opt('show-hide-meta', 'enable', '1') !== '1') { return; }
    $css = '';
    if (fly_plugin_opt('show-hide-meta', 'views', '1') !== '1') { $css .= '.post-full .meta{visibility:hidden}'; }
    if (fly_plugin_opt('show-hide-meta', 'likes', '1') !== '1') { $css .= '#fly-like{display:none !important}'; }
    if ($css !== '') { echo '<style>' . $css . '</style>'; }
});
