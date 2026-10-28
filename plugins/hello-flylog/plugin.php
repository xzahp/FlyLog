<?php
/**
 * Plugin Name: Hello FlyLog（官方示例）
 * Version: 1.0.0
 * Description: 演示原生插件引擎的 Hook 机制：只用 fly_add_action / fly_add_filter 挂接，不改动任何核心文件
 * Author: FlyLog
 * Category: 示例
 * Price: free
 * Requires: 10.0
 *
 * 目录约定：plugins/hello-flylog/{plugin.php, config.php, screenshot.png}
 * 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// Action 示例 1：页脚追加一行
fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('hello-flylog', 'enable', '1') !== '1') { return; }
    $txt = (string)fly_plugin_opt('hello-flylog', 'text', '🔌 由 Hello FlyLog 示例插件驱动');
    echo '<div style="text-align:center;color:#9ca3af;font-size:12px;padding:4px 0">'
       . fly_e($txt) . '</div>';
});

// Action 示例 2：<head> 里追加 meta
fly_add_action('fly_head', function () {
    if (fly_plugin_opt('hello-flylog', 'enable', '1') !== '1') { return; }
    echo '<meta name="generator" content="FlyLog ' . fly_e(FLY_VERSION) . ' + HelloFlyLog">' . "\n";
});

// Filter 示例：给正文末尾追一句（可改写入栈值，return 必须回传）
fly_add_filter('the_content', function ($html) {
    if (fly_plugin_opt('hello-flylog', 'enable', '1') !== '1') { return $html; }
    if (fly_plugin_opt('hello-flylog', 'append', '0') !== '1') { return $html; }
    return $html . '<p style="color:#9ca3af;font-size:13px">—— 本段由 Hello FlyLog 插件追加</p>';
});
