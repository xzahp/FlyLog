<?php
/**
 * V10.0 插件截图代理
 * ------------------------------------------------------------------
 * plugins/ 目录被 .htaccess 全面拒绝直连（防 PHP 直闯），
 * 所以后台展示截图要经本脚本读取后输出 —— 既保住目录安全，又能看到图。
 * 必须后台登录才可访问，杜绝未授权浏览插件目录结构。
 */
require __DIR__ . '/_init.php';
fly_admin_require('manage_plugins');

$slug = fly_plugin_safe_name((string)($_GET['p'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    exit;
}
$file = fly_plugin_dir($slug) . '/screenshot.png';
if (!is_file($file) || !fly_plugin_in_root($file)) {
    // 没有截图就返回一张 1x1 透明 PNG，避免后台出现破图
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=3600');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
