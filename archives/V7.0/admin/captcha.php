<?php
/**
 * FlyLog V6.5 · 后台登录图形验证码（SVG，不依赖 GD）
 * ------------------------------------------------------------------
 * 选 SVG 而不是 GD 的原因：大量虚拟主机（含本机）没有启用 GD，
 * 而 SVG 是纯文本，任何环境都能出图，且矢量在小屏上不会糊。
 * 会话里只存答案与时间戳（10 分钟过期），输出强制不缓存。
 */
require __DIR__ . '/../config.php';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noimageindex');
header('X-Content-Type-Options: nosniff');

// 字符集：剔除 0/O、1/I/L、2/Z、8/B 等易混淆字符
$charset = 'ACDEFGHJKMNPQRTUVWXY34679';
$len = 4;
$code = '';
$maxIdx = strlen($charset) - 1;
for ($i = 0; $i < $len; $i++) {
    $code .= $charset[mt_rand(0, $maxIdx)];
}

$_SESSION['fly_captcha_code'] = $code;
$_SESSION['fly_captcha_ts']   = time();

$w = 120;
$h = 42;

/** 颜色池（品牌深色系，保证对比度） */
$colors = array('#0A2A5E', '#16407E', '#1F6FEB', '#1857C4', '#0F3C74');

$svg = '<' . '?xml version="1.0" encoding="UTF-8"?>'
     . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="图形验证码">'
     . '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="#F7F9FC"/>';

// 干扰线
for ($i = 0; $i < 4; $i++) {
    $x1 = mt_rand(0, $w);
    $y1 = mt_rand(2, $h - 2);
    $x2 = mt_rand(0, $w);
    $y2 = mt_rand(2, $h - 2);
    $c  = $colors[mt_rand(0, count($colors) - 1)];
    $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2
          . '" stroke="' . $c . '" stroke-width="1" stroke-opacity="0.18"/>';
}

// 干扰点
for ($i = 0; $i < 26; $i++) {
    $cx = mt_rand(0, $w);
    $cy = mt_rand(0, $h);
    $c  = $colors[mt_rand(0, count($colors) - 1)];
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="1" fill="' . $c . '" fill-opacity="0.28"/>';
}

// 字符（逐个随机旋转 + 轻微上下浮动）
$step = (int)floor(($w - 16) / $len);
for ($i = 0; $i < $len; $i++) {
    $ch = $code[$i];
    $x  = 10 + $i * $step;
    $y  = 28 + mt_rand(-3, 3);
    $rot = mt_rand(-16, 16);
    $c   = $colors[mt_rand(0, count($colors) - 1)];
    $svg .= '<text x="' . $x . '" y="' . $y . '" font-family="Verdana,DejaVu Sans,Geneva,sans-serif"'
          . ' font-size="23" font-weight="bold" fill="' . $c . '"'
          . ' transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')">' . $ch . '</text>';
}

$svg .= '<rect x="0.5" y="0.5" width="' . ($w - 1) . '" height="' . ($h - 1) . '" fill="none" stroke="#DCDFE6"/>';
$svg .= '</svg>';

echo $svg;
exit;
