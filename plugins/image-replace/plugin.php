<?php
/**
 * Plugin Name: 图片替换
 * Version: 1.0.0
 * Description: 上传同名图片时覆盖旧图
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
    if (fly_plugin_opt('image-replace', 'enable', '1') !== '1') { return; }
    // 默认即覆盖（uploads 同名写入），此插件把行为显式化并记录审计
    if (!empty($_FILES) && function_exists('fly_audit')) {
        foreach ($_FILES as $f) {
            if (!empty($f['name'])) { fly_audit('upload', 'file', 0, '上传文件 ' . basename($f['name'])); }
        }
    }
});
