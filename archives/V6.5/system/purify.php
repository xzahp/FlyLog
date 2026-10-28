<?php
/**
 * FlyLog V6.0 · HTML 白名单净化器
 * ---------------------------------------------------------------
 * 用途：前台输出正文、后台自定义 HTML（如 404 正文）前做白名单过滤，
 *       阻断存储型 XSS 与 CSP 绕过型注入。
 *
 * 设计约束（和整套系统一致）：
 *   · 纯原生实现，不依赖 HTMLPurifier，也不依赖 DOM/mbstring 扩展
 *     —— 虚拟主机经常缺扩展。
 *   · PHP 5.6+ 可运行（不用 ??/<=>, 不 catch Throwable）。
 *   · 只做"减法"：白名单标签 + 白名单属性，其余一律丢弃。
 *
 * 已知取舍：不做标签闭合修复（浏览器容错足够），不做 CSS 属性级过滤
 * （style 属性整体禁用，避免 expression()/url(javascript:) 一类的历史绕过）。
 */
defined('FLY_BOOTSTRAPPED') or exit;

/** 白名单标签 */
function fly_purify_tags($allowEmbed = false)
{
    $tags = array(
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark',
        'small', 'sub', 'sup', 'span', 'div', 'section', 'article', 'header',
        'footer', 'main', 'aside', 'nav', 'figure', 'figcaption',
        'blockquote', 'pre', 'code', 'kbd', 'samp', 'var', 'q', 'cite', 'abbr', 'time',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
    );
    if ($allowEmbed) {
        $tags[] = 'iframe';
    }
    return $tags;
}

/** 各标签允许的属性（通用属性自动附加） */
function fly_purify_attrs($tag, $allowEmbed = false)
{
    $map = array(
        'a'       => array('href', 'target', 'rel', 'hreflang'),
        'img'     => array('src', 'alt', 'width', 'height', 'loading', 'decoding'),
        'th'      => array('colspan', 'rowspan', 'scope', 'align'),
        'td'      => array('colspan', 'rowspan', 'align'),
        'col'     => array('span'),
        'ol'      => array('start', 'type'),
        'li'      => array('value'),
        'time'    => array('datetime'),
        'iframe'  => array('src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'sandbox', 'loading'),
    );
    $allow = array('id', 'class', 'title', 'lang', 'dir');
    if (isset($map[$tag])) {
        $allow = array_merge($allow, $map[$tag]);
    }
    return $allow;
}

/** URL 协议白名单；相对路径、锚点、mailto/tel、图片 base64 放行 */
function fly_purify_url_ok($url, $isImg = false)
{
    $u = trim((string)$url);
    if ($u === '') {
        return false;
    }
    // 先解实体，堵住 java&#115;cript: 之类的编码绕过
    $decoded = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    // 去掉所有空白与控制字符（"java script:"、"jav\tascript:" 都归位）
    $t = strtolower(preg_replace('/[\x00-\x20\x7f]/', '', $decoded));
    if ($t === '') {
        return false;
    }
    $c = substr($t, 0, 1);
    if ($c === '/' || $c === '#' || $c === '.' || $c === '?') {
        return true;                                   // 相对路径 / 锚点 / 查询串
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#', $t)) {
        return true;                                   // 无协议 = 相对路径
    }
    if (preg_match('#^(https?|mailto|tel):#', $t)) {
        return true;
    }
    if ($isImg && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $t)) {
        return true;                                   // 内联图片（明确不含 svg，避免 SVG XSS）
    }
    return false;                                      // javascript: / vbscript: / data:text/html 等一律拒绝
}

/** 解析开始标签里的属性串，返回 name => value */
function fly_purify_parse_attrs($s)
{
    $out = array();
    if (trim((string)$s) === '') {
        return $out;
    }
    $re = '/([a-zA-Z_:][\-a-zA-Z0-9_:.]*)\s*(?:=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/s';
    if (preg_match_all($re, $s, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            $name = strtolower($m[1]);
            $val = '';
            if (isset($m[3]) && $m[3] !== '') {
                $val = $m[3];
            } elseif (isset($m[4]) && $m[4] !== '') {
                $val = $m[4];
            } elseif (isset($m[5]) && $m[5] !== '') {
                $val = $m[5];
            }
            $out[$name] = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
        }
    }
    return $out;
}

/**
 * 净化 HTML
 * @param string $html
 * @param bool   $allowEmbed 是否允许 iframe（后台开关，默认关闭）
 * @return string
 */
function fly_html_purify($html, $allowEmbed = false)
{
    $html = (string)$html;
    if ($html === '') {
        return '';
    }

    // 1) 去掉 HTML 注释（条件注释常被用来绕过过滤器）
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // 2) 连内容一起删除的标签：循环执行，堵住 <scr<script>ipt> 嵌套绕过
    $killBlock = array('script', 'style', 'noscript', 'template', 'xmp', 'plaintext', 'xml');
    for ($round = 0; $round < 3; $round++) {
        $before = $html;
        foreach ($killBlock as $t) {
            $html = preg_replace('#<\s*' . $t . '\b[^>]*>(.*?)<\s*/\s*' . $t . '\s*>#is', '', $html);
            $html = preg_replace('#<\s*' . $t . '\b[^>]*/\s*>#is', '', $html);
            $html = preg_replace('#<\s*' . $t . '\b[^>]*>#is', '', $html);
            $html = preg_replace('#<\s*/\s*' . $t . '\s*>#is', '', $html);
        }
        if ($html === $before) {
            break;
        }
    }

    // 3) 去掉标签但保留内容的标签（表单控件 / 矢量图 / 媒体 / 嵌套框架）
    $strip = array('object', 'embed', 'applet', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'svg', 'math', 'frameset', 'frame', 'audio', 'video',
        'source', 'track', 'canvas', 'base', 'meta', 'link', 'title');
    if (!$allowEmbed) {
        $strip[] = 'iframe';
    }
    foreach ($strip as $t) {
        $html = preg_replace('#<\s*' . $t . '\b[^>]*>#is', '', $html);
        $html = preg_replace('#<\s*/\s*' . $t . '\s*>#is', '', $html);
    }

    // 4) 开始标签：白名单 + 属性过滤
    $tags = fly_purify_tags($allowEmbed);
    $html = preg_replace_callback(
        '#<\s*([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^>]*)?)\s*/?\s*>#s',
        function ($m) use ($tags, $allowEmbed) {
            $tag = strtolower($m[1]);
            if (!in_array($tag, $tags, true)) {
                return '';                               // 非白名单标签：只删标签，保留文字
            }
            $attrs = fly_purify_parse_attrs(isset($m[2]) ? $m[2] : '');
            $allow = fly_purify_attrs($tag, $allowEmbed);
            $out = '';
            foreach ($attrs as $name => $val) {
                $ln = strtolower($name);
                if (strpos($ln, 'on') === 0) { continue; }                     // 一切事件属性
                if ($ln === 'style' || $ln === 'xmlns' || $ln === 'formaction') { continue; }
                if (strpos($ln, 'xlink:') === 0) { continue; }
                $ok = in_array($ln, $allow, true)
                    || strpos($ln, 'data-') === 0
                    || strpos($ln, 'aria-') === 0;
                if (!$ok) { continue; }
                if (($ln === 'href' || $ln === 'src') && !fly_purify_url_ok($val, $tag === 'img')) {
                    continue;                                                  // 危险协议
                }
                $out .= ' ' . $ln . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
            }
            // 5) 安全加固：新窗口打开的链接补 noopener；允许的 iframe 强制沙箱
            if ($tag === 'a' && strpos($out, 'target=') !== false && strpos($out, 'rel=') === false) {
                $out .= ' rel="nofollow ugc noopener"';
            }
            if ($tag === 'iframe' && $allowEmbed && strpos($out, 'sandbox=') === false) {
                $out .= ' sandbox="allow-scripts allow-same-origin"';
            }
            $void = in_array($tag, array('br', 'hr', 'img', 'col'), true);
            return '<' . $tag . $out . ($void ? ' />' : '>');
        },
        $html
    );

    // 6) 结束标签：非白名单的一并去掉，避免留下悬空的 </script>
    $html = preg_replace_callback(
        '#<\s*/\s*([a-zA-Z][a-zA-Z0-9]*)\s*>#s',
        function ($m) use ($tags) {
            $tag = strtolower($m[1]);
            return in_array($tag, $tags, true) ? '</' . $tag . '>' : '';
        },
        $html
    );

    return $html;
}
