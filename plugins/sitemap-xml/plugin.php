<?php
/**
 * Plugin Name: 自动生成 Sitemap
 * Version: 1.0.0
 * Description: /sitemap.php 输出全站链接（核心已内置，本插件补充样式与更新提示）
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 每天首次访问时刷新一次 sitemap 静态缓存，保持链接最新
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('sitemap-xml', 'enable', '1') !== '1') { return; }
    $flag = fly_cache_get('sitemap_daily', 86400);
    if ($flag === null) {
        fly_cache_set('sitemap_daily', date('Y-m-d'), 86400);
        @touch(FLY_CACHE . '/sitemap-touch.txt');
    }
});
