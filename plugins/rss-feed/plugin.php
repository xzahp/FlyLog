<?php
/**
 * Plugin Name: RSS/Atom 订阅
 * Version: 1.0.0
 * Description: 在页面头部输出 RSS 订阅链接，便于阅读器发现
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
    if (fly_plugin_opt('rss-feed', 'enable', '1') !== '1') { return; }
    echo '<link rel="alternate" type="application/rss+xml" title="'
        . fly_e(fly_config('site.name', 'FlyLog')) . ' RSS" href="'
        . fly_e(fly_base_url() . 'feed.php') . '">';
});
