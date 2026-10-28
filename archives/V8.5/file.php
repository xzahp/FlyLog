<?php
/**
 * FlyLog V3.51 附件受控输出
 * datadir 已整体防直闯；附件通过本脚本受控读取输出。
 * 安全：防盗链（Referer 白名单）+ nosniff + 非图片强制下载（防浏览器内联解析 HTML/SVG 等）。
 * #71 断点续传：支持 Range 请求（206 Partial Content / 416 Range Not Satisfiable）。
 */
// V6.0 #14(P1) 二进制附件绝不能再经过 HTML 压缩的输出缓冲，否则图片 / 压缩包内容会被改写损坏
define('FLY_NO_COMPRESS', true);
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

// ---------- V6.0 #15(P0) 附件归属鉴权 ----------
// 旧实现只判断"文件是否存在"：任何人猜到文件名就能下载草稿 / 未发布文章的附件。
// attachments.post_id 为 V6.0 新增列；老库缺列时降级放行，不因鉴权失败拖垮下载。
$isAdmin = !empty($_SESSION['fly_admin_id']);
try {
    $att = fly_db()->one('SELECT post_id FROM attachments WHERE path = ?', array($f));
    if (!$att) {
        $att = fly_db()->one('SELECT post_id FROM attachments WHERE path LIKE ?', array('%/' . $f));
    }
    if ($att && (int)$att['post_id'] > 0) {
        $p = fly_db()->one('SELECT is_draft, publish_at, deleted_at FROM posts WHERE id = ?', array((int)$att['post_id']));
        $hidden = true;                       // 查不到归属文章时按"不公开"处理
        if ($p) {
            $draft = isset($p['is_draft']) && (int)$p['is_draft'] === 1;
            $deleted = isset($p['deleted_at']) && (int)$p['deleted_at'] > 0;
            $future = false;
            if (!empty($p['publish_at'])) {
                $ts = strtotime((string)$p['publish_at']);
                $future = ($ts !== false && $ts > time());
            }
            $hidden = $draft || $future || $deleted;
        }
        if ($hidden && !$isAdmin) {
            // 返回 404 而非 403：不向外界暴露"这个文件确实存在"
            http_response_code(404);
            exit('404 Not Found');
        }
    }
} catch (Exception $e) {
    if (function_exists('fly_log')) {
        fly_log('attachment auth skipped: ' . $e->getMessage());
    }
}

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
