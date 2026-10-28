<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V3.0 安全助手总集（第一批：安全加固）
 * 由 bootstrap 加载；提供 HTTPS 强制、安全响应头、SQL 注入特征、日志脱敏、
 * Argon2id 密码哈希等。依赖 functions/cache/template 已加载。
 */

/** 强制 HTTPS（仅当 config 的 site.url 为 https 时启用；本地/CLI 不跳转） */
function fly_force_https()
{
    if (PHP_SAPI === 'cli') { return; }
    $url = (string)fly_config('site.url', '');
    if (stripos($url, 'https://') !== 0) { return; }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    if (!$isHttps) {
        $to = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $to, true, 301);
        exit;
    }
}

/** 输出安全响应头（CSP 允许本站资源 + 内联脚本/样式 + data/https 图片，避免误伤） */
function fly_security_headers()
{
    if (headers_sent()) { return; }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https:; font-src 'self' data:; "
        . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

/** SQL 注入特征检测（用于登录账号等非内容字段；正文内容已由 PDO 预处理保证安全） */
function fly_detect_sqli($input)
{
    $s = strtolower((string)$input);
    if ($s === '') { return false; }
    $pats = array(
        'union select', 'union all select', 'sleep(', 'benchmark(',
        'information_schema', ' or 1=1', "' or '", '" or "', 'xp_cmdshell',
        'load_file(', 'into outfile', 'into dumpfile', ';--', '/*', '*/', '%27', '%20or%20',
    );
    foreach ($pats as $p) {
        if (strpos($s, $p) !== false) { return true; }
    }
    return false;
}

/** 日志脱敏：IP 末段打码（IPv4 掩最后一节，IPv6 掩最后两组） */
function fly_mask_ip($ip)
{
    $ip = (string)$ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        $p[3] = '*';
        return implode('.', $p);
    }
    if (strpos($ip, ':') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $e = explode(':', $ip);
        $n = count($e);
        if ($n >= 2) { $e[$n - 1] = '*'; $e[$n - 2] = '*'; }
        return implode(':', $e);
    }
    return $ip;
}

/**
 * 密码哈希：优先 Argon2id（PHP7.2+，更安全），不可用或失败则回退 bcrypt。
 * 历史 bcrypt 哈希仍可被 password_verify 校验，平滑升级。
 */
function fly_password_hash($pw)
{
    if (defined('PASSWORD_ARGON2ID')) {
        $h = @password_hash($pw, PASSWORD_ARGON2ID);
        if ($h) { return $h; }
    }
    return password_hash($pw, PASSWORD_DEFAULT);
}

/** 校验密码（自动识别算法） */
function fly_password_verify($pw, $hash)
{
    return password_verify($pw, (string)$hash);
}

/** 是否需要升级旧哈希（bcrypt → Argon2id），用于登录成功后顺手重哈希 */
function fly_password_needs_rehash($hash)
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

/** 全局异常处理器：记录日志 + 友好错误页（debug 开才显示详情） */
function fly_exception_handler($e)
{
    fly_log('EXCEPTION: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'exception');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = (bool)fly_config('site.debug', false);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>出错了 - FlyLog</title></head>'
        . '<body style="font-family:sans-serif;padding:40px;line-height:1.8;color:#333">'
        . '<h2>抱歉，页面出错了</h2><p>系统已自动记录本次错误，请稍后重试或联系站长。</p>';
    if ($debug) {
        echo '<pre style="background:#f6f6f6;padding:12px;border-radius:6px;overflow:auto;font-size:13px">'
            . fly_e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
    echo '<p><a href="' . fly_e(fly_url('home')) . '">&laquo; 返回首页</a></p></body></html>';
}
