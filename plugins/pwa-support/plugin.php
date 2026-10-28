<?php
/**
 * Plugin Name: PWA 支持
 * Version: 1.0.0
 * Description: 注册 Service Worker + manifest，支持离线阅读
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('pwa-support', 'enable', '1') !== '1') { return; }
    $base = rtrim(fly_base_url(), '/');
    echo '<link rel="manifest" href="' . fly_e($base . '/plugins/pwa-support/manifest.json') . '">';
    echo '<meta name="theme-color" content="#0A2A5E">';
});
fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('pwa-support', 'enable', '1') !== '1') { return; }
    echo "<script>if('serviceWorker' in navigator){navigator.serviceWorker.register('"
        . fly_e(rtrim(fly_base_url(), '/') . '/plugins/pwa-support/sw.js')
        . "').catch(function(){});}</script>";
});
