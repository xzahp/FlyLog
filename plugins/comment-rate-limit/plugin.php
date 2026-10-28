<?php
/**
 * Plugin Name: 评论频率限制
 * Version: 1.0.0
 * Description: 同一 IP 60 秒内只能发 1 条评论
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('comment-rate-limit', 'enable', '1') !== '1') { return; }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'post.php') { return; }
    $key = 'cmt_rate_' . md5(fly_client_ip());
    $n = (int)fly_cache_get($key, 60);
    if ($n >= 1) {
        http_response_code(429);
        exit('留言太频繁了，请 60 秒后再试。');
    }
    fly_cache_set($key, $n + 1, 60);
});
