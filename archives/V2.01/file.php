<?php
/**
 * FlyLog V2.0 附件受控输出
 * datadir 已整体防直闯；附件通过本脚本受控读取输出，兼顾安全与可访问。
 */
require __DIR__ . '/config.php';

$f = basename((string)($_GET['f'] ?? '')); // basename 防目录穿越
if ($f === '' || !is_file(FLY_DATA . '/uploads/' . $f)) {
    http_response_code(404);
    exit('404 Not Found');
}
$path = FLY_DATA . '/uploads/' . $f;
$mime = function_exists('mime_content_type') ? mime_content_type($path) : false;
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
