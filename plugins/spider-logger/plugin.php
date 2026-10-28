<?php
/**
 * Plugin Name: 蜘蛛爬虫记录
 * Version: 1.0.0
 * Description: 记录百度/谷歌等搜索引擎蜘蛛的访问路径与时间
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('spider-logger', 'enable', '1') !== '1') { return; }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $bots = array('Baiduspider', 'Googlebot', 'bingbot', 'Sogou', '360Spider', 'YisouSpider', 'Bytespider');
    $hit = '';
    foreach ($bots as $b) { if (stripos($ua, $b) !== false) { $hit = $b; break; } }
    if ($hit === '') { return; }
    fly_log('SPIDER ' . $hit . ' ' . (string)($_SERVER['REQUEST_URI'] ?? '-'), 'spider');
});
