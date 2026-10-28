<?php
/**
 * Plugin Name: 图片 EXIF 清理
 * Version: 1.0.0
 * Description: 上传图片时清除 EXIF 信息，保护隐私
 * Author: FlyLog
 * Category: 媒体
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('post_saved', function ($id, $data, $isNew) {
    if (fly_plugin_opt('exif-clean', 'enable', '0') !== '1') { return; }
    // 纯原生方案：重写 JPEG 时截掉 APP1（EXIF）段；没有 GD 也能做二进制清理
    if (!isset($data['content'])) { return; }
    if (!preg_match_all('/src=["\']([^"\']+\.jpe?g)/i', $data['content'], $m)) { return; }
    foreach ($m[1] as $src) {
        $path = FLY_ROOT . '/' . ltrim(parse_url($src, PHP_URL_PATH) ?: '', '/');
        // 核心没有 fly_in_root()，这里内联做 containment 校验，防止 ../ 越界写文件
        $real = realpath($path);
        $root = realpath(FLY_ROOT);
        if (!is_file($path) || $real === false || $root === false
            || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) { continue; }
        @file_put_contents($path, preg_replace('/\xFF\xE1.{2}Exif\x00\x00.*?(?=\xFF\xD8|\xFF\xDB|\xFF\xC0)/s', '', (string)@file_get_contents($path)));
    }
}, 10);
