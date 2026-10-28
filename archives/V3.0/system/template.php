<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 模板引擎 + 模板标签
 *  - fly_render()：渲染 templates/ 下的 PHP 模板文件（extract 变量 + 输出缓冲）
 *  - fly_tpl_*  ：可复用的模板标签函数（SEO 元信息 / 面包屑 / 文章项 / 分页）
 */

/** 渲染一个模板文件，返回 HTML 字符串 */
function fly_render($template, $vars = array())
{
    $file = $template;
    if (substr($file, -4) !== '.php') {
        $file .= '.php';
    }
    $path = FLY_TPL . '/' . ltrim($file, '/');
    if (!is_file($path)) {
        return '<!-- 模板不存在: ' . fly_e($file) . ' -->';
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $path;
    return ob_get_clean();
}

/** 输出 SEO 元信息（TDK + Open Graph + JSON-LD 结构化数据） */
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
    // Open Graph
    $out .= '<meta property="og:title" content="' . fly_e($meta['title']) . '">' . "\n";
    $out .= '<meta property="og:description" content="' . fly_e($meta['description']) . '">' . "\n";
    $out .= '<meta property="og:type" content="' . fly_e($meta['type']) . '">' . "\n";
    $out .= '<meta property="og:url" content="' . fly_e($meta['url']) . '">' . "\n";
    $out .= '<meta property="og:site_name" content="' . fly_e(fly_site_name()) . '">' . "\n";
    // JSON-LD 结构化数据
    if (!empty($meta['jsonld'])) {
        $out .= '<script type="application/ld+json">' . json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    return $out;
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

/** 输出单篇文章摘要项（列表页复用） */
function fly_tpl_post_item($row, $highlight = '')
{
    $summary = ($row['summary'] !== '' && $row['summary'] !== null)
        ? $row['summary'] : fly_excerpt($row['content'] ?? '', 120);
    $title = fly_e($row['title']);
    if ($highlight !== '') {
        $title = fly_highlight($title, $highlight);
    }
    $pin = !empty($row['is_pinned']) ? '<span class="pin" title="置顶">📌 置顶</span>' : '';
    $cat = ($row['category'] !== '' && $row['category'] !== null)
        ? ' · <a href="' . fly_e(fly_url('category', array('cat' => $row['category']))) . '">' . fly_e($row['category']) . '</a>'
        : '';
    return '<article class="post-item">'
        . '<h2><a href="' . fly_e(fly_url('post', array('id' => $row['id']))) . '">' . $title . '</a>' . $pin . '</h2>'
        . '<p class="meta">' . fly_e($row['created_at']) . $cat . ' · 阅读 ' . (int)($row['views'] ?? 0) . '</p>'
        . '<p class="summary">' . fly_e($summary) . '</p>'
        . '</article>';
}

/** 搜索命中高亮：先转义再用 <mark> 包裹（防 XSS） */
function fly_highlight($escapedHtml, $keyword)
{
    $kw = trim((string)$keyword);
    if ($kw === '') {
        return $escapedHtml;
    }
    $kwEsc = fly_e($kw);       // 关键词也转义，避免注入 mark 属性
    $kwEsc = preg_quote($kwEsc, '/');
    return preg_replace('/(' . $kwEsc . ')/iu', '<mark>$1</mark>', $escapedHtml);
}
