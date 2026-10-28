<?php
/**
 * Plugin Name: 访问量统计
 * Version: 1.0.0
 * Description: 记录今日 PV/UV 与全站累计访问次数
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
    if (fly_plugin_opt('traffic-stats', 'enable', '1') !== '1') { return; }
    if (php_sapi_name() === 'cli') { return; }
    $day = date('Y-m-d');
    $pv = (int)fly_cache_get('pv_' . $day, 86400 * 7) + 1;
    fly_cache_set('pv_' . $day, $pv, 86400 * 7);
    $uvKey = 'uv_' . $day . '_' . md5(fly_client_ip() . (($_SERVER['HTTP_USER_AGENT'] ?? '')));
    if (fly_cache_get($uvKey, 86400) === null) {
        fly_cache_set($uvKey, 1, 86400);
        $uv = (int)fly_cache_get('uv_' . $day, 86400 * 7) + 1;
        fly_cache_set('uv_' . $day, $uv, 86400 * 7);
    }
    $total = (int)fly_opt('stat_total_pv', 0) + 1;
    fly_set_option('stat_total_pv', (string)$total);
});
