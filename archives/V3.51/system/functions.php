<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 系统函数库（无依赖纯助手）
 * 放置不碰数据库/会话的通用helper；DB与会话初始化在 bootstrap.php
 */

/** HTML 转义，防 XSS */
function fly_e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** 生成摘要：去标签、压缩空白、按字符安全截断 */
function fly_excerpt($text, $len = 120)
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $len, 'UTF-8');
    }
    return substr($text, 0, $len);
}

/** 安全取字符串长度（UTF-8 优先，无 mbstring 退化字节数） */
function fly_len($str)
{
    return function_exists('mb_strlen') ? mb_strlen((string)$str, 'UTF-8') : strlen((string)$str);
}

/** 来访者真实 IP（只认 REMOTE_ADDR，防伪造；按 IPv6 上限 45 截断） */
function fly_client_ip()
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = '0.0.0.0';
    }
    return substr($ip, 0, 45);
}

/** 读取站点配置（来自 config.php 的 $FLY_CONFIG，支持 点号 取值） */
function fly_config($key, $default = null)
{
    global $FLY_CONFIG;
    if (!is_array($FLY_CONFIG)) {
        return $default;
    }
    if (array_key_exists($key, $FLY_CONFIG)) {
        return $FLY_CONFIG[$key];
    }
    // 支持 site.name 形式
    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $val = $FLY_CONFIG;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return $default;
            }
        }
        return $val;
    }
    return $default;
}

/** 读取后台可视化配置（存 options 表；DB 未就绪时回退默认值）。
 *  与 fly_config 分工：fly_config 读 config.php 结构配置（不连库，保证静态模式 DB=0）；
 *  fly_opt 读可编辑设置（TDK/开关等，仅在渲染期调用）。 */
function fly_opt($key, $default = null)
{
    static $opts = null;
    if ($opts === null) {
        $opts = array();
        try {
            $rows = fly_db()->all("SELECT k, v FROM options");
            foreach ($rows as $r) {
                $opts[$r['k']] = $r['v'];
            }
        } catch (Throwable $e) {
            // 库/表未就绪（如首次安装前），静默回退
        }
    }
    return array_key_exists($key, $opts) ? $opts[$key] : $default;
}

/** 写入一条可视化配置到 options 表（存在则更新，否则插入；跨库兼容） */
function fly_set_option($key, $value)
{
    $db = fly_db();
    $n = $db->update('options', array('v' => (string)$value), 'k = ?', array($key));
    if ((int)$n === 0) {
        try {
            $db->insert('options', array('k' => $key, 'v' => (string)$value));
        } catch (Throwable $e) {
            // 已存在（值未变导致 rowCount=0）则忽略
        }
    }
    // 刷新进程内缓存
    return $value;
}

/** 功能开关：优先 options 表，回退 config.php 的 features 配置 */
function fly_feature($key, $default = false)
{
    $v = fly_opt('feature_' . $key, null);
    if ($v === null) {
        $v = fly_config('features.' . $key, $default);
    }
    return $v;
}

/** 站点显示名 / 描述 / 关键词（可被后台设置覆盖） */
function fly_site_name()
{
    return (string)fly_opt('site.name', fly_config('site.name', 'FlyLog'));
}
function fly_site_desc()
{
    return (string)fly_opt('site.desc', fly_config('site.desc', ''));
}
function fly_site_keywords()
{
    return (string)fly_opt('site.keywords', fly_config('site.keywords', ''));
}

/** 当前站点基础 URL（自动识别 https，可被配置覆盖） */
function fly_base_url()
{
    $cfg = fly_opt('site.url', fly_config('site.url', ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/') . '/';
    }
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        $scheme = 'https';
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/';
}

/** 安全重定向（仅放行站内相对路径，防开放式重定向） */
function fly_redirect($url)
{
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url) || strpos($url, '//') === 0) {
        // 绝对地址：仅允许与本站同 host
        $base = parse_url(fly_base_url(), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);
        if ($base && $host && strcasecmp($base, $host) === 0) {
            header('Location: ' . $url);
            exit;
        }
        $url = 'index.php';
    }
    if ($url === '' || $url[0] !== '/') {
        // 相对路径直接跳
        header('Location: ' . $url);
        exit;
    }
    if (strpos($url, '//') !== false || stripos($url, '://') !== false) {
        $url = 'index.php';
    }
    header('Location: ' . $url);
    exit;
}

/** 输出 JSON 并结束（用于只读 API） */
function fly_json($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 生成/读取 CSRF Token（会话级，2 小时过期，过期自动重签） */
function fly_csrf_token()
{
    if (empty($_SESSION['fly_csrf'])
        || (is_array($_SESSION['fly_csrf']) && (time() - ($_SESSION['fly_csrf']['ts'] ?? 0)) > 7200)) {
        $_SESSION['fly_csrf'] = array('t' => bin2hex(random_bytes(16)), 'ts' => time());
    }
    return is_array($_SESSION['fly_csrf']) ? $_SESSION['fly_csrf']['t'] : $_SESSION['fly_csrf'];
}

/** 输出 CSRF 隐藏域（表单用） */
function fly_csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . fly_e(fly_csrf_token()) . '">';
}

/** 校验 CSRF（POST 时调用；带 2 小时过期，失败即终止） */
function fly_csrf_check()
{
    $token = (string)($_POST['_csrf'] ?? '');
    $cur = $_SESSION['fly_csrf'] ?? '';
    $curT = is_array($cur) ? (string)($cur['t'] ?? '') : (string)$cur;
    $ts = is_array($cur) ? (int)($cur['ts'] ?? 0) : 0;
    if ($curT === '' || ($ts > 0 && time() - $ts > 7200) || !hash_equals($curT, $token)) {
        http_response_code(400);
        die('请求已失效或安全检查未通过（CSRF Token 已过期），请返回上一页刷新后重试。');
    }
}

/** 简易分页 HTML（伪静态/动态自适应） */
function fly_pager($page, $totalPages, $urlFor)
{
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav class="pager">';
    $html .= ($page > 1)
        ? '<a href="' . fly_e($urlFor($page - 1)) . '">&laquo; 上一页</a>'
        : '<span class="dis">&laquo; 上一页</span>';
    for ($i = 1; $i <= $totalPages; $i++) {
        $html .= ($i == $page)
            ? '<span class="cur">' . $i . '</span>'
            : '<a href="' . fly_e($urlFor($i)) . '">' . $i . '</a>';
    }
    $html .= ($page < $totalPages)
        ? '<a href="' . fly_e($urlFor($page + 1)) . '">下一页 &raquo;</a>'
        : '<span class="dis">下一页 &raquo;</span>';
    return $html . '</nav>';
}

/** 站点主机名（dns-prefetch / 防盗链白名单用） */
function fly_site_host()
{
    $h = (string)($_SERVER['HTTP_HOST'] ?? '');
    return $h !== '' ? $h : 'localhost';
}

/**
 * 静态资源 URL：按文件内容 MD5 生成版本号（#55 资源版本控制）
 * 文件内容变化 → 版本号变 → 浏览器强制刷新缓存；内容不变则命中强缓存。
 */
function fly_asset($rel)
{
    $rel = ltrim((string)$rel, '/');
    $file = rtrim(FLY_ROOT, '/\\') . '/' . $rel;
    $ver = is_file($file) ? substr(md5_file($file), 0, 10) : FLY_VERSION;
    return fly_base_url() . $rel . '?v=' . $ver;
}

/**
 * CSS 压缩（#53 Minify）：去注释/多余空白，产物可由 cli asset:minify 生成
 * 到 assets/app.min.css；不存在 min 版时 head 自动回退原版，不影响运行。
 */
function fly_minify_css($css)
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);          // 块注释
    $css = preg_replace('/(^|[^:])\/\/[^\n]*/', '$1', $css); // 行注释（避开 :// 协议）
    $css = preg_replace('/\s+/', ' ', $css);                 // 压缩空白
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css); // 去符号周边空白
    return trim($css);
}
