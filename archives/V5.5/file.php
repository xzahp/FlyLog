<?php
/**
 * FlyLog V3.51 附件受控输出
 * datadir 已整体防直闯；附件通过本脚本受控读取输出。
 * 安全：防盗链（Referer 白名单）+ nosniff + 非图片强制下载（防浏览器内联解析 HTML/SVG 等）。
 * #71 断点续传：支持 Range 请求（206 Partial Content / 416 Range Not Satisfiable）。
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

$size = filesize($path);
$disposition = $isImage ? 'inline' : 'attachment';

header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
// #14(P1) 头值场景必须剥离 CR/LF，否则附件名含 \r\n 可注入任意响应头（如 X-Evil）
$origSafe = str_replace(array("\r", "\n"), '', (string)$orig);
header('Content-Disposition: ' . $disposition . '; filename="' . fly_e($origSafe) . '"');
header('Accept-Ranges: bytes');

// ---------- #71 Range 断点续传 ----------
$range = (string)($_SERVER['HTTP_RANGE'] ?? '');
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
    $start = $m[1] === '' ? null : (int)$m[1];
    $end = $m[2] === '' ? null : (int)$m[2];
    if ($start === null && $end !== null) {
        // 后缀范围：bytes=-500 表示最后 500 字节
        $start = max(0, $size - $end);
        $end = $size - 1;
    } elseif ($start === null) {
        $start = 0; $end = $size - 1;
    } else {
        if ($end === null || $end >= $size) { $end = $size - 1; }
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $length = $end - $start + 1;
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);
    $fp = fopen($path, 'rb');
    if ($fp) {
        fseek($fp, $start);
        $remain = $length;
        while ($remain > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remain));
            if ($chunk === '') { break; }
            echo $chunk;
            $remain -= strlen($chunk);
        }
        fclose($fp);
    }
    exit;
}

header('Content-Length: ' . $size);
readfile($path);
