<?php
/**
 * Plugin Name: 面包屑导航
 * Version: 1.0.0
 * Description: 首页 > 分类 > 文章 层级导航样式
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
    if (fly_plugin_opt('breadcrumbs', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.breadcrumb{font-size:13px;color:var(--text-2);margin-bottom:10px}
.breadcrumb a{color:var(--text-2);text-decoration:none}
.breadcrumb a:hover{color:var(--brand)}
.breadcrumb .sep{margin:0 6px;opacity:.5}
FLYCSS . '</style>';
});
