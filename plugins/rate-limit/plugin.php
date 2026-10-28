<?php
/**
 * Plugin Name: 请求频率限制
 * Version: 1.0.0
 * Description: 全站防 CC：同一 IP 每分钟超过 120 次请求直接拒绝
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('rate-limit', 'enable', '1') !== '1') { return; }
    $key = 'rl_' . md5(fly_client_ip());
    $n = (int)fly_cache_get($key, 60);
    fly_cache_set($key, $n + 1, 60);
    if ($n > 120) {
        http_response_code(429);
        exit('请求过于频繁，请稍后再试。');
    }
});
