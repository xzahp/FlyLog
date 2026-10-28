<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 模板引擎 + 模板标签
 *  - fly_render()：渲染 templates/ 下的 PHP 模板文件（extract 变量 + 输出缓冲）
 *  - fly_tpl_*  ：可复用的模板标签函数（SEO 元信息 / 面包屑 / 文章项 / 分页）
 */

/**
 * 渲染一个模板文件，返回 HTML 字符串
 * #56 模板渲染缓存：传入 $cacheKey 时，渲染结果存入 cachedir（带 TTL），
 * 命中即跳过 PHP 解析与执行——适合侧边栏/分类目录等高频片段。
 * 动态内容（登录态/搜索词）请勿传 cacheKey。
 */
function fly_render($template, $vars = array(), $cacheKey = null, $ttl = 300)
{
    $file = $template;
    if (substr($file, -4) !== '.php') {
        $file .= '.php';
    }
    $path = FLY_TPL . '/' . ltrim($file, '/');
    // #46(P1) 模板路径 containment：realpath 必须仍在 templates/ 内，
    // 杜绝「../ 任意 PHP 文件包含 → RCE」（公开 API，插件主题切换等场景真实存在）
    $real = realpath($path);
    $tplRoot = realpath(FLY_TPL);
    if ($real === false || $tplRoot === false || strpos($real, $tplRoot . DIRECTORY_SEPARATOR) !== 0) {
        return '<!-- 非法模板路径 -->';
    }
    if (!is_file($real)) {
        return '<!-- 模板不存在: ' . fly_e($file) . ' -->';
    }
    if ($cacheKey !== null) {
        $ck = 'tpl:' . $cacheKey;
        $hit = fly_cache_get($ck, $ttl);
        if ($hit !== null) {
            return $hit;
        }
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $real;
    $html = ob_get_clean();
    if ($cacheKey !== null) {
        fly_cache_set('tpl:' . $cacheKey, $html, $ttl);
    }
    return $html;
}

/** #154/#161/#153 输出 SEO 元信息（TDK + canonical + OG(含封面) + JSON-LD 多段结构化数据）
 *  $meta 支持键：title/description/keywords/url/type/canonical(可覆盖)/og_image/
 *  first_image(文章首图)/jsonld(单段或[多段])/breadcrumb(自动生成 BreadcrumbList) */
function fly_tpl_seo($meta = array())
{
    $meta = array_merge(array(
        'title' => fly_site_name() . ' - ' . fly_site_desc(),
        'description' => fly_site_desc(),
        'keywords' => fly_site_keywords(),
        'url' => fly_base_url(),
        'type' => 'website',
    ), $meta);
    $out = '<title>' . fly_e($meta['title']) . '</title>' . "\n";
    $out .= '<meta name="description" content="' . fly_e($meta['description']) . '">' . "\n";
    if ($meta['keywords'] !== '') {
        $out .= '<meta name="keywords" content="' . fly_e($meta['keywords']) . '">' . "\n";
    }
    // #154 canonical：页面可传覆盖，缺省按当前请求路径生成
    $canonical = (isset($meta['canonical']) && $meta['canonical'] !== '') ? $meta['canonical'] : fly_canonical_url();
    if ($canonical !== '') {
        $out .= '<link rel="canonical" href="' . fly_e($canonical) . '">' . "\n";
    }
    // Open Graph（分享到微信/QQ 有大图）
    $out .= '<meta property="og:title" content="' . fly_e($meta['title']) . '">' . "\n";
    $out .= '<meta property="og:description" content="' . fly_e($meta['description']) . '">' . "\n";
    $out .= '<meta property="og:type" content="' . fly_e($meta['type']) . '">' . "\n";
    $out .= '<meta property="og:url" content="' . fly_e($meta['url']) . '">' . "\n";
    $out .= '<meta property="og:site_name" content="' . fly_e(fly_site_name()) . '">' . "\n";
    // #161 og:image：文章首图 > 配置封面 > 默认封面
    $ogImage = (isset($meta['og_image']) && $meta['og_image'] !== '') ? $meta['og_image'] : fly_og_image($meta);
    if ($ogImage !== '') {
        $out .= '<meta property="og:image" content="' . fly_e($ogImage) . '">' . "\n";
    }
    // #153 JSON-LD：支持单段数组或数字键多段数组
    if (!empty($meta['jsonld'])) {
        $blocks = isset($meta['jsonld'][0]) ? $meta['jsonld'] : array($meta['jsonld']);
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block)) { continue; }
            if (!isset($block['@context'])) { $block = array_merge(array('@context' => 'https://schema.org'), $block); }
            // #47(P1) JSON_HEX_TAG|AMP|APOS|QUOT：</script> 被转义为 <\/script>，
            // 无法闭合 script 标签 → 断绝存储型/反射型 XSS（此前仅 UNESCAPED_SLASHES 可被闭合）
            $out .= '<script type="application/ld+json">' . json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
        }
    }
    // #174 面包屑结构化数据（传了 breadcrumb 自动生成 BreadcrumbList）
    if (!empty($meta['breadcrumb']) && is_array($meta['breadcrumb'])) {
        $out .= '<script type="application/ld+json">' . json_encode(fly_jsonld_breadcrumb($meta['breadcrumb']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
    }
    return $out;
}

/** #153 生成 BreadcrumbList JSON-LD（$trail = [['name'=>..,'url'=>..], ...]） */
function fly_jsonld_breadcrumb($trail)
{
    $items = array();
    $i = 0;
    foreach ($trail as $item) {
        $i++;
        $entry = array('@type' => 'ListItem', 'position' => $i, 'name' => (string)$item['name']);
        if (!empty($item['url'])) {
            $entry['item'] = (string)$item['url'];
        }
        $items[] = $entry;
    }
    return array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items);
}

/** 输出面包屑导航（$trail = [['name'=>..,'url'=>..], ...]） */
function fly_tpl_breadcrumb($trail)
{
    if (!$trail) {
        return '';
    }
    $out = '<nav class="breadcrumb" aria-label="面包屑">';
    $n = count($trail);
    $i = 0;
    foreach ($trail as $item) {
        $i++;
        $isLast = ($i === $n);
        if ($isLast || empty($item['url'])) {
            $out .= '<span class="bc-cur">' . fly_e($item['name']) . '</span>';
        } else {
            $out .= '<a href="' . fly_e($item['url']) . '">' . fly_e($item['name']) . '</a><span class="bc-sep">/</span>';
        }
    }
    return $out . '</nav>';
}

/** 输出单篇文章摘要项（列表页复用）
 *  #49(P3) 列值统一 ?? 取值，select 指定列时不再触发未定义通知 */
function fly_tpl_post_item($row, $highlight = '')
{
    $summary = (($row['summary'] ?? '') !== '' && $row['summary'] !== null)
        ? $row['summary'] : fly_excerpt($row['content'] ?? '', 120);
    $title = fly_e($row['title'] ?? '');
    if ($highlight !== '') {
        $title = fly_highlight($title, $highlight);
    }
    $pin = !empty($row['is_pinned']) ? '<span class="pin" title="置顶">📌 置顶</span>' : '';
    $cat = (($row['category'] ?? '') !== '' && $row['category'] !== null)
        ? ' · <a href="' . fly_e(fly_url('category', array('cat' => $row['category']))) . '">' . fly_e($row['category']) . '</a>'
        : '';
    return '<article class="post-item">'
        . '<h2><a href="' . fly_e(fly_url('post', array('id' => $row['id'] ?? 0))) . '">' . $title . '</a>' . $pin . '</h2>'
        . '<p class="meta">' . fly_e($row['created_at'] ?? '') . $cat . ' · 阅读 ' . (int)($row['views'] ?? 0) . '</p>'
        . '<p class="summary">' . fly_e($summary) . '</p>'
        . '</article>';
}

/** 搜索命中高亮：先转义再用 <mark> 包裹（防 XSS）
 *  #50(P3) HTML 实体临时占位后再匹配，避免把 &amp; 切成 &<mark>amp</mark>; 显示错乱 */
function fly_highlight($escapedHtml, $keyword)
{
    $kw = trim((string)$keyword);
    if ($kw === '') {
        return $escapedHtml;
    }
    $kwEsc = fly_e($kw);       // 关键词也转义，避免注入 mark 属性
    $kwEsc = preg_quote($kwEsc, '/');
    $placeholders = array();
    $masked = preg_replace_callback('/&(amp|lt|gt|quot|#39|apos|nbsp);/i', function ($m) use (&$placeholders) {
        $ph = "\x01" . count($placeholders) . "\x01";
        $placeholders[$ph] = $m[0];
        return $ph;
    }, $escapedHtml);
    $out = preg_replace('/(' . $kwEsc . ')/iu', '<mark>$1</mark>', $masked);
    if ($placeholders) {
        $out = str_replace(array_keys($placeholders), array_values($placeholders), $out);
    }
    return $out;
}
