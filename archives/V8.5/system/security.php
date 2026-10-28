<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V3.0 安全助手总集（第一批：安全加固）
 * 由 bootstrap 加载；提供 HTTPS 强制、安全响应头、SQL 注入特征、日志脱敏、
 * Argon2id 密码哈希等。依赖 functions/cache/template 已加载。
 */

/** 强制 HTTPS（仅当 config 的 site.url 为 https 时启用；本地/CLI 不跳转）
 *  #7(P2) X-Forwarded-Proto 仅在显式配置 trust_proxy 时采信；
 *  #8(P2) 跳转目标 Host 做白名单校验，防 Host 头注入/开放重定向。 */
function fly_force_https()
{
    if (PHP_SAPI === 'cli') { return; }
    $url = (string)fly_config('site.url', '');
    if (stripos($url, 'https://') !== 0) { return; }
    $trustProxy = (bool)fly_config('site.trust_proxy', false);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($trustProxy && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'))
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    if (!$isHttps) {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        // Host 白名单：只允许合法 host[:port]，且与配置站点域一致（防 Host 头注入）
        $cfgHost = strtolower((string)parse_url($url, PHP_URL_HOST));
        $reqHost = strtolower(preg_replace('/:\d+$/', '', $host));
        if ($host === '' || !preg_match('#^[a-z0-9.\-]+(:\d+)?$#i', $host)
            || ($cfgHost !== '' && $reqHost !== $cfgHost)) {
            $host = $cfgHost !== '' ? $cfgHost : 'localhost';
        }
        $to = 'https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $to, true, 301);
        exit;
    }
}

/** 输出安全响应头
 *  #11(P3/功能bug) CSP 放行 cdnjs（highlight.js CDN 代码高亮，此前被自家 CSP 拦死导致高亮全线失效）；
 *  #9(P3) 补 HSTS（HTTPS 站点才发，避免本地 HTTP 开发被浏览器钉死）。 */
function fly_security_headers()
{
    if (headers_sent()) { return; }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: https:; font-src 'self' data:; "
        . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

/** SQL 注入特征检测（用于登录账号等非内容字段；正文内容已由 PDO 预处理保证安全）
 *  #10(P3) 收紧特征，减少对合法输入（如含 /* 的用户名）的误伤。 */
function fly_detect_sqli($input)
{
    $s = strtolower((string)$input);
    if ($s === '') { return false; }
    $pats = array(
        'union select', 'union all select', 'sleep(', 'benchmark(',
        'information_schema', ' or 1=1', "' or '", '" or "', 'xp_cmdshell',
        'load_file(', 'into outfile', 'into dumpfile',
    );
    foreach ($pats as $p) {
        if (strpos($s, $p) !== false) { return true; }
    }
    return false;
}

/** 日志脱敏：IP 末段打码（IPv4 掩最后一节；IPv6 按 16 字节掩最后 4 字节，兼容 :: 压缩写法） */
function fly_mask_ip($ip)
{
    $ip = (string)$ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        $p[3] = '*';
        return implode('.', $p);
    }
    if (strpos($ip, ':') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = (string)@inet_pton($ip);
        if (strlen($bin) === 16) {
            $bin[13] = '*'; $bin[14] = '*'; $bin[15] = '*';
            $parts = array();
            for ($i = 0; $i < 16; $i += 2) {
                $parts[] = dechex((ord($bin[$i]) << 8) | ord($bin[$i + 1]));
            }
            return implode(':', $parts) . '/*';
        }
    }
    return $ip;
}

/**
 * 密码哈希：优先 Argon2id（PHP7.2+，更安全），不可用或失败则回退 bcrypt。
 * 历史 bcrypt 哈希仍可被 password_verify 校验，平滑升级。
 * #11(P3) 显式指定 Argon2id 成本参数，行为可预期、可按主机调优。
 */
function fly_password_hash($pw)
{
    if (defined('PASSWORD_ARGON2ID')) {
        $opts = array(
            'memory_cost' => 65536,  // 64 MiB
            'time_cost'   => 4,
            'threads'     => 2,
        );
        $cfg = fly_config('security.argon2', array());
        if (is_array($cfg)) {
            if (isset($cfg['memory_cost'])) { $opts['memory_cost'] = (int)$cfg['memory_cost']; }
            if (isset($cfg['time_cost']))   { $opts['time_cost'] = (int)$cfg['time_cost']; }
            if (isset($cfg['threads']))     { $opts['threads'] = (int)$cfg['threads']; }
        }
        $h = @password_hash($pw, PASSWORD_ARGON2ID, $opts);
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
        // #12(P3) debug 详情只回显消息本身；文件路径与行号只进 logsdir，不暴露给访客
        echo '<pre style="background:#f6f6f6;padding:12px;border-radius:6px;overflow:auto;font-size:13px">'
            . fly_e($e->getMessage()) . '</pre>';
    }
    echo '<p><a href="' . fly_e(fly_url('home')) . '">&laquo; 返回首页</a></p></body></html>';
}
