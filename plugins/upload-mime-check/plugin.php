<?php
/**
 * Plugin Name: 上传 MIME 校验
 * Version: 1.0.0
 * Description: 校验上传文件真实类型，防伪装后缀
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
    if (fly_plugin_opt('upload-mime-check', 'enable', '1') !== '1') { return; }
    if (empty($_FILES)) { return; }
    foreach ($_FILES as $f) {
        if (empty($f['tmp_name']) || !is_file($f['tmp_name'])) { continue; }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $real = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : '';
        $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                     'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf');
        if ($real !== '' && isset($map[$ext]) && strpos($real, $map[$ext]) !== 0) {
            http_response_code(400);
            exit('文件类型与后缀不符，已拒绝上传。');
        }
    }
});
