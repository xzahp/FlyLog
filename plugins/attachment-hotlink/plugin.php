<?php
/**
 * Plugin Name: 附件防盗链
 * Version: 1.0.0
 * Description: 下载链接需带临时 Token，过期失效
 * Author: FlyLog
 * Category: 媒体
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('attachment-hotlink', 'enable', '0') !== '1') { return; }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'file.php') { return; }
    $t = isset($_GET['t']) ? (string)$_GET['t'] : '';
    $secret = (string)fly_opt('preview_secret', '');
    if ($secret === '' || $t === '') { return; }
    $f = isset($_GET['f']) ? basename((string)$_GET['f']) : '';
    $expect = substr(hash_hmac('sha256', $f, $secret), 0, 16);
    if (!hash_equals($expect, $t)) {
        http_response_code(403);
        exit('链接已失效');
    }
});
