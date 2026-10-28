<?php
/**
 * Plugin Name: 文件大小限制
 * Version: 1.0.0
 * Description: 后台可设置单文件上传上限（默认 5MB）
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
    if (fly_plugin_opt('upload-size-limit', 'enable', '1') !== '1') { return; }
    if (empty($_FILES)) { return; }
    $max = (int)fly_plugin_opt('upload-size-limit', 'max', 5) * 1048576;
    foreach ($_FILES as $f) {
        if (!empty($f['size']) && $f['size'] > $max) {
            http_response_code(413);
            exit('文件超过上限（' . fly_plugin_opt('upload-size-limit', 'max', 5) . 'MB）');
        }
    }
});
