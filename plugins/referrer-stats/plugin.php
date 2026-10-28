<?php
/**
 * Plugin Name: 访问来源统计
 * Version: 1.0.0
 * Description: 统计访客从百度/谷歌/直接访问来的比例
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
    if (fly_plugin_opt('referrer-stats', 'enable', '1') !== '1') { return; }
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $src = '直接访问';
    if ($ref !== '') {
        $host = (string)parse_url($ref, PHP_URL_HOST);
        foreach (array('baidu' => '百度', 'google' => '谷歌', 'bing' => 'Bing', 'so.com' => '360') as $k => $v) {
            if (stripos($host, $k) !== false) { $src = $v; break; }
        }
        if ($src === '直接访问') { $src = '其他外链'; }
    }
    $key = 'stat_ref_' . md5($src);
    fly_set_option($key, (string)((int)fly_opt($key, 0) + 1));
    fly_set_option('stat_ref_name_' . md5($src), $src);
});
