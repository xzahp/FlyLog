<?php
/**
 * FlyLog V3.0 附件受控输出
 * datadir 已整体防直闯；附件通过本脚本受控读取输出。
 * 安全：防盗链（Referer 白名单）+ nosniff + 非图片强制下载（防浏览器内联解析 HTML/SVG 等）。
 */
require __DIR__ . '/config.php';

$f = basename((string)($_GET['f'] ?? '')); // basename 防目录穿越
if ($f === '' || !is_file(FLY_DATA . '/uploads/' . $f)) {
    http_response_code(404);
    exit('404 Not Found');
}

// 防盗链：Referer 非空且域名与本站不同 → 拒绝（允许空 Referer 的直接访问/隐私模式）
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
if ($ref !== '') {
    $refHost = parse_url($ref, PHP_URL_HOST);
    $siteHost = parse_url(fly_base_url(), PHP_URL_HOST);
    if ($refHost && $siteHost && strcasecmp($refHost, $siteHost) !== 0) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$path = FLY_DATA . '/uploads/' . $f;
$mime = function_exists('mime_content_type') ? mime_content_type($path) : false;
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$isImage = (strncmp((string)$mime, 'image/', 6) === 0)
    && in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true);
$orig = (string)(fly_db()->value("SELECT name FROM attachments WHERE path = ?", array($f)) ?: $f);

header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
if ($isImage) {
    header('Content-Disposition: inline; filename="' . fly_e($orig) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . fly_e($orig) . '"');
}
readfile($path);
