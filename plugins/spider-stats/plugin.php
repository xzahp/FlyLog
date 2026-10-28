<?php
/**
 * Plugin Name: 蜘蛛访问统计
 * Version: 1.0.0
 * Description: 统计百度/谷歌蜘蛛每天爬了多少页
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('spider-stats', 'enable', '1') !== '1') { return; }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    foreach (array('Baiduspider' => '百度', 'Googlebot' => '谷歌', 'bingbot' => 'Bing') as $k => $v) {
        if (stripos($ua, $k) !== false) {
            $key = 'stat_spider_' . $v . '_' . date('Y-m-d');
            fly_set_option($key, (string)((int)fly_opt($key, 0) + 1));
            return;
        }
    }
});
