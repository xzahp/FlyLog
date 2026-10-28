<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 系统函数库（无依赖纯助手）
 * 放置不碰数据库/会话的通用helper；DB与会话初始化在 bootstrap.php
 */

/** HTML 转义，防 XSS
 *  #14(P1) 补 ENT_SUBSTITUTE：非法 UTF-8 不再静默返回空串（内容丢失/潜在绕过） */
function fly_e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** HTTP 头值净化：去掉 CR/LF，防响应头注入（file.php 下载文件名等场景用） */
function fly_header_safe($str)
{
    return trim(str_replace(array("\r", "\n", "\0"), '', (string)$str));
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

/** #144 预计阅读时间（中文按字数计，~400 字/分钟，最少 1 分钟） */
function fly_reading_time($content)
{
    $text = preg_replace('/\s+/u', '', strip_tags((string)($content ?? '')));
    $chars = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    $min = (int)ceil($chars / 400);
    return max(1, $min);
}

/** #126 文章目录（TOC）：扫描正文 h2/h3，注入锚点 id 并返回目录数组
 *  返回 array('toc'=>[{level,text,id}], 'content'=>注入 id 后的正文)
 *  原有 id 会被复用，避免覆盖作者手动设置的锚点。 */
function fly_build_toc($content)
{
    if (!is_string($content) || $content === '') {
        return array('toc' => array(), 'content' => $content);
    }
    $toc = array();
    $i = 0;
    $new = preg_replace_callback(
        '/<h([23])(\s[^>]*?)?>(.*?)<\/h\1>/is',
        function ($m) use (&$toc, &$i) {
            $level = (int)$m[1];
            $attrs = isset($m[2]) ? $m[2] : '';
            $inner = $m[3];
            $i++;
            $id = 'sect-' . $i;
            if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $mm)) {
                // #24(P3) 复用作者 id 前做白名单净化（防畸形 id 注入锚点/目录链接）
                $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $mm[1]);
                $id = ($safe !== '') ? $safe : ('sect-' . $i);
            } else {
                $attrs = rtrim($attrs) . ' id="' . $id . '"';
            }
            $text = trim(strip_tags($inner));
            if ($text !== '') {
                $toc[] = array('level' => $level, 'text' => $text, 'id' => $id);
            }
            return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
        },
        $content
    );
    return array('toc' => $toc, 'content' => $new);
}

/** #154 当前页规范 URL（去掉 query 参数，防站内重复内容导致降权） */
function fly_canonical_url()
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    return rtrim(fly_base_url(), '/') . $path;
}

/** 相对路径补全为绝对 URL（OG/JSON-LD 用） */
function fly_abs_url($u)
{
    $u = trim((string)$u);
    if ($u === '') { return ''; }
    if (preg_match('#^https?://#i', $u)) { return $u; }
    if (strpos($u, '//') === 0) { return 'https:' . $u; }
    return rtrim(fly_base_url(), '/') . '/' . ltrim($u, '/');
}

/** 取正文第一张图片地址（OG 封面用） */
function fly_first_image($html)
{
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)$html, $m)) {
        return trim($m[1]);
    }
    return '';
}

/** #161 OG 封面图：文章首图 > 配置 site.og_image > 默认 assets/og-cover.png */
function fly_og_image($meta = array())
{
    if (!empty($meta['first_image'])) { return fly_abs_url($meta['first_image']); }
    $cfg = (string)fly_config('site.og_image', '');
    if ($cfg !== '') { return fly_abs_url($cfg); }
    if (is_file(FLY_ROOT . '/assets/og-cover.png')) {
        return rtrim(fly_base_url(), '/') . '/assets/og-cover.png';
    }
    return '';
}

/** #156+#172 正文渲染前处理：外链自动加 rel="nofollow ugc" + 中英文混排自动补空格
 *  script/style/pre/code/textarea 段原样保护，不动代码与富文本结构。
 *  #17(P2) 删除 $protect 标志：preg_split 捕获的已是完整标签块，逐段交替处理即可，
 *  旧实现的标志逻辑把标签块之后的普通文本也错标为保护，导致外链/空格大面积静默失效。 */
function fly_process_content($html)
{
    $html = (string)$html;
    if ($html === '') { return $html; }
    // V6.0 #8(P0) 输出前先做白名单净化：正文原样输出曾让 script/on* 事件直达访客浏览器（存储型 XSS）。
    // 是否放行 iframe 由后台开关 content.allow_embed 控制，默认关闭。
    $allowEmbed = ((string)fly_opt('content.allow_embed', '')) === '1';
    $html = fly_html_purify($html, $allowEmbed);
    if ($html === '') { return $html; }
    // 保护段只保留 pre/code/textarea：script/style 已在净化阶段连同内容删除，不再需要"保护"
    $parts = preg_split('/(<(?:pre|code|textarea)\b[^>]*>.*?<\/(?:pre|code|textarea)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) { return $html; }
    $out = '';
    foreach ($parts as $p) {
        if (preg_match('/^<(?:pre|code|textarea)\b/i', $p)) {
            $out .= $p;   // 保护段原样输出
            continue;
        }
        // 外链（非同站 http(s) 链接）补 rel="nofollow ugc"；已有 rel 或站内链接不动
        $p = preg_replace_callback('/<a\b([^>]*)>/i', function ($m) {
            $attrs = $m[1];
            if (preg_match('/\brel\s*=/i', $attrs)) { return $m[0]; }
            if (!preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $attrs, $hm)) { return $m[0]; }
            $href = $hm[1];
            if (!preg_match('#^https?://#i', $href)) { return $m[0]; }
            $host = strtolower((string)parse_url($href, PHP_URL_HOST));
            $self = strtolower((string)parse_url(fly_base_url(), PHP_URL_HOST));
            if ($host === '' || ($self !== '' && $host === $self)) { return $m[0]; }
            return '<a' . $attrs . ' rel="nofollow ugc">';
        }, $p);
        // 中英/中日之间补空格（原本无空格才补，不重复加）
        $p = preg_replace('/([\x{4e00}-\x{9fff}\x{3040}-\x{30ff}])([A-Za-z0-9])/u', '$1 $2', $p);
        $p = preg_replace('/([A-Za-z0-9])([\x{4e00}-\x{9fff}\x{3040}-\x{30ff}])/u', '$1 $2', $p);
        $out .= $p;
    }
    return $out;
}

/** #V8.5 文章内链自动解析：把正文里出现的「其它已发布文章标题」自动变成站内超链接。
 *  - 仅在纯文本片段里替换，跳过 <a>/<pre>/<code>/<script>/<style>/<textarea> 内部，避免破坏结构；
 *  - 每篇标题在每段最多替换 1 次，先匹配长标题再匹配短标题，避免短词误伤；
 *  - $excludeId 用于排除当前文章自身标题（防止正文提自己又链接回自己）；
 *  - 已发布且非草稿的文章才参与；同请求内 static 缓存查询结果。 */
function fly_auto_internal_links($html, $excludeId = 0)
{
    $html = (string)$html;
    if ($html === '' || strpos($html, '<') === false) { return $html; }
    static $titles = null;
    if ($titles === null) {
        $titles = array();
        $rows = fly_db()->all("SELECT id, title FROM posts WHERE is_draft = 0 AND deleted_at = 0");
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $t = trim((string)($r['title'] ?? ''));
                if ($t !== '' && fly_len($t) >= 4) { $titles[(int)$r['id']] = $t; }
            }
            uasort($titles, function ($a, $b) { return fly_len($b) - fly_len($a); });
        }
    }
    if (empty($titles)) { return $html; }

    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) { return $html; }
    $out = '';
    $inAnchor = false; $inProtect = false;
    foreach ($parts as $p) {
        if ($p === '') { continue; }
        if ($p[0] === '<') {
            if (preg_match('#^<a\b#i', $p)) { $inAnchor = true; }
            elseif (preg_match('#^</a>#i', $p)) { $inAnchor = false; }
            elseif (preg_match('#^<(script|style|pre|code|textarea)\b#i', $p)) { $inProtect = true; }
            elseif (preg_match('#^</(script|style|pre|code|textarea)>#i', $p)) { $inProtect = false; }
            $out .= $p;
            continue;
        }
        if ($inAnchor || $inProtect) { $out .= $p; continue; }
        foreach ($titles as $pid => $title) {
            if ($pid === (int)$excludeId) { continue; }
            if (strpos($p, $title) === false) { continue; }
            $url = fly_url('post', array('id' => $pid));
            $p = preg_replace('/' . preg_quote($title, '/') . '/u', '<a href="' . fly_e($url) . '">' . fly_e($title) . '</a>', $p, 1);
        }
        $out .= $p;
    }
    return $out;
}

/** #168 压缩 HTML 输出：去 HTML 注释 + 压空白（保护 script/style/pre/textarea）
 *  由 bootstrap 以 ob_start 注册；debug / api / cli / 静态构建场景不启用。
 *  #25(P3) 与 #17 同源：删除 $protect 标志（split 已按完整块分割，标志会错标后续文本）。 */
function fly_compress_html($html)
{
    if (!is_string($html) || $html === '' || strpos($html, '<') === false) { return $html; }
    $parts = preg_split('/(<(?:script|style|pre|textarea)\b[^>]*>.*?<\/(?:script|style|pre|textarea)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) { return $html; }
    $out = '';
    foreach ($parts as $p) {
        if (preg_match('/^<(?:script|style|pre|textarea)\b/i', $p)) {
            $out .= $p;   // 保护段原样输出
            continue;
        }
        $p = preg_replace('/<!--(?!\[if\s).*?-->/s', '', $p);   // 去注释（保留 IE 条件注释）
        $p = preg_replace('/[ \t]+/', ' ', $p);                 // 连续空格/制表符压成一个
        $p = preg_replace('/\s*\n\s*/', "\n", $p);              // 空白行收敛
        $p = preg_replace('/>\s+</', '><', $p);                 // 标签间空白整体删除
        $out .= $p;
    }
    return $out;
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
 *  fly_opt 读可编辑设置（TDK/开关等，仅在渲染期调用）。
 *  #19(P2) 进程内缓存经引用获取，fly_set_option 可即时刷新（同请求先写后读一致）。 */
function &fly_opt_store()
{
    static $opts = null;
    return $opts;
}
function fly_opt($key, $default = null)
{
    $opts = &fly_opt_store();
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
    // #19(P2) 刷新进程内缓存（此前只注释未实现，导致同请求内先写后读拿到旧值）
    $opts = &fly_opt_store();
    if (is_array($opts)) {
        $opts[$key] = (string)$value;
    }
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

/** #213 前台导航菜单项（后台 JSON 配置；未配置返回空数组 = 前台用默认导航）
 *  #04(P2) url 协议白名单：仅放行 http(s)/mailto/相对路径/锚点，
 *  拒绝 javascript:/data:/vbscript: 等（后台配错或被污染时不成全站存储型 XSS）。 */
function fly_nav_menu_items()
{
    static $items = null;
    if ($items !== null) { return $items; }
    $raw = (string)fly_opt('site.nav_menu', '');
    $items = array();
    if ($raw !== '') {
        $arr = json_decode($raw, true);
        if (is_array($arr)) {
            foreach ($arr as $m) {
                if (!is_array($m) || empty($m['name']) || empty($m['url'])) { continue; }
                $url = (string)$m['url'];
                $url = str_replace(array("\r", "\n", "\t"), '', $url);
                $ok = (bool)preg_match('#^(https?://|mailto:|/|\?|#)#i', $url)
                    && !preg_match('#^\s*(javascript|data|vbscript|file):#i', $url);
                if (!$ok) { continue; }   // 危险协议直接丢弃该项
                $items[] = array(
                    'name' => (string)$m['name'],
                    'url' => $url,
                    'new_tab' => !empty($m['new_tab']) ? 1 : 0,
                );
            }
        }
    }
    return $items;
}

/** #201 全站名词自定义：后台可改「文章/留言/分类」等前台可见名词
 *  fly_term('post') 取后台配置，缺省用内置中文名词。 */
function fly_term($key, $default = '')
{
    $map = array(
        'post'     => '文章',
        'comment'  => '留言',
        'category' => '分类',
        'tag'      => '标签',
        'member'   => '会员',
        'archive'  => '归档',
        'search'   => '搜索',
    );
    $def = ($default !== '') ? $default : (isset($map[$key]) ? $map[$key] : $key);
    $v = (string)fly_opt('term.' . $key, '');
    return ($v !== '') ? $v : $def;
}

/** #231 Webhook 通知：新留言/新文章推送到企业微信/钉钉等
 *  未配置开关或地址、发送失败均静默返回，不影响主流程。 */
function fly_notify($title, $text)
{
    if (!fly_feature('webhook', false)) { return false; }
    $url = trim((string)fly_opt('notify.webhook_url', ''));
    if ($url === '' || !preg_match('#^https?://#i', $url)) { return false; }
    // #18(P2) SSRF 防护：拒绝内网/环回/链路本地地址（云元数据 169.254.169.254 等）
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if ($host === '' || $host === 'localhost'
        || (filter_var($ip, FILTER_VALIDATE_IP)
            && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
        return false;
    }
    $payload = array(
        'msgtype'  => 'markdown',
        'markdown' => array('title' => (string)$title, 'text' => (string)$text),
    );
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!function_exists('curl_init')) { return false; }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,   // #18(P2) 恢复证书校验，防中间人窃取通知内容
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300);
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

/** 当前站点基础 URL（可被配置覆盖；自动识别时对 Host 做合法性校验）
 *  #15(P2) 不再无条件采信 HTTP_HOST：畸形 Host 回落配置值/SERVER_NAME，
 *  避免 canonical/OG/webhook 链接被 Host 头污染。 */
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
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('#^[a-z0-9.\-]+(:\d+)?$#i', $host)) {
        $host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
        if ($host === '' || !preg_match('#^[a-z0-9.\-]+$#i', $host)) {
            $host = 'localhost';
        }
    }
    return $scheme . '://' . $host . '/';
}

/** 安全重定向（仅放行站内相对路径，防开放式重定向）
 *  #16(P2) 反斜杠归一化后再做全部检查（/\evil.com 经浏览器归一为 //evil.com 的绕过）；
 *  非 http(s) 协议（javascript:/data: 等）一律拒绝。 */
function fly_redirect($url)
{
    $url = str_replace('\\', '/', (string)$url);           // 反斜杠归一
    $url = str_replace(array("\r", "\n"), '', $url);       // 头注入防护
    // 非 http(s) 协议（javascript:/data:/vbscript: …）一律按相对路径处理不了，直接回落
    if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*):#', $url, $m)
        && !in_array(strtolower($m[1]), array('http', 'https'), true)) {
        $url = 'index.php';
    }
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

/** 输出 JSON 并结束（用于只读 API）
 *  #23(P3) json_encode 失败（非法 UTF-8）时返回 500 错误码，不再输出空 body + 200；
 *  #5(P3) 输出前清掉 HTML 压缩缓冲，保证 JSON 原样吐出。 */
function fly_json($data, $code = 200)
{
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(array('code' => 500, 'message' => 'encoding error'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo $json;
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

/** 校验 CSRF（POST 时调用；带 2 小时过期，失败即终止）
 *  #20(P3) 旧版纯字符串 token（无 ts）一律视为过期，杜绝永不过期的历史会话 token */
function fly_csrf_check()
{
    $token = (string)($_POST['_csrf'] ?? '');
    $cur = $_SESSION['fly_csrf'] ?? '';
    $curT = is_array($cur) ? (string)($cur['t'] ?? '') : '';
    $ts = is_array($cur) ? (int)($cur['ts'] ?? 0) : 0;
    if ($curT === '' || $ts <= 0 || (time() - $ts > 7200) || !hash_equals($curT, $token)) {
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
    // #21(P3) 路径 containment：realpath 必须仍在站点根内，否则回落版本号（防 ../../ 读取探测）
    $real = realpath($file);
    $inRoot = ($real !== false && strpos($real, rtrim(realpath(FLY_ROOT) ?: FLY_ROOT, '/\\') . DIRECTORY_SEPARATOR) === 0);
    $ver = ($inRoot && is_file($real)) ? substr(md5_file($real), 0, 10) : FLY_VERSION;
    return fly_base_url() . $rel . '?v=' . $ver;
}

/**
 * CSS 压缩（#53 Minify）：去注释/多余空白，产物可由 cli asset:minify 生成
 * 到 assets/app.min.css；不存在 min 版时 head 自动回退原版，不影响运行。
 */
function fly_minify_css($css)
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);          // 块注释
    // #22(P3) 行注释剥离前保护 url(...) 内容（协议相对地址 url(//cdn...) 曾被误删整行）
    $css = preg_replace_callback('/url\(([^)]*)\)/i', function ($m) {
        return 'url(' . base64_encode($m[1]) . ')';
    }, $css);
    $css = preg_replace('/(^|[^:])\/\/[^\n]*/', '$1', $css); // 行注释（避开 :// 协议）
    $css = preg_replace_callback('/url\(([^)]*)\)/i', function ($m) {
        $v = base64_decode($m[1]);
        return 'url(' . (is_string($v) ? $v : '') . ')';
    }, $css);
    $css = preg_replace('/\s+/', ' ', $css);                 // 压缩空白
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css); // 去符号周边空白
    return trim($css);
}
