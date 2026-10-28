<?php
/**
 * Plugin Name: 最近访客 IP
 * Version: 1.0.0
 * Description: 后台显示最近 20 个访客 IP（脱敏）
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
    if (fly_plugin_opt('recent-visitor-ip', 'enable', '1') !== '1') { return; }
    if (php_sapi_name() === 'cli') { return; }
    $ip = fly_client_ip();
    $masked = preg_replace('/(\d+\.\d+)\.\d+\.\d+/', '$1.*.*', $ip);
    $list = array_filter(explode(',', (string)fly_opt('stat_recent_ips', '')));
    array_unshift($list, $masked . '@' . date('H:i'));
    $list = array_slice(array_unique($list), 0, 20);
    fly_set_option('stat_recent_ips', implode(',', $list));
});
