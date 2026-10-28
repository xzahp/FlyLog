<?php
/**
 * Plugin Name: 分类/标签 SEO
 * Version: 1.0.0
 * Description: 分类页与标签页输出独立的标题与描述
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
    if (fly_plugin_opt('taxonomy-seo', 'enable', '1') !== '1') { return; }
    $tag = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
    if ($tag !== '') {
        echo '<meta name="description" content="' . fly_e('标签：' . $tag . ' - ' . fly_config('site.name', 'FlyLog')) . '">';
    }
});
