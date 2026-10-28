<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 URL 路由 + 三种模式（静态 / 伪静态 / 动态）
 *  - fly_mode()：当前模式（来自配置 site.mode）
 *  - fly_url($type,$params)：按模式 + 可配置 URL 规则模板生成链接
 *  - 静态模式：fly_static_serve() 命中即输出预生成 HTML（数据库连接降为 0）
 */

/** 当前运行模式 */
function fly_mode()
{
    $m = fly_config('site.mode', 'pseudo');
    return in_array($m, array('static', 'pseudo', 'dynamic'), true) ? $m : 'pseudo';
}

/** URL 规则模板（可在 config.php 的 url_rules 覆盖） */
function fly_url_rules()
{
    $defaults = array(
        'home'     => '/',
        'post'     => '/post/{id}.html',
        'page'     => '/page/{page}',
        'archive'  => '/archive',
        'category' => '/?cat={cat}',
        'search'   => '/?q={q}',
        'feed'     => '/feed',
        'api'      => '/api',
    );
    $cfg = fly_config('url_rules', array());
    return array_merge($defaults, is_array($cfg) ? $cfg : array());
}

/** 按模式与规则生成 URL */
function fly_url($type, $params = array())
{
    $mode = fly_mode();
    $rules = fly_url_rules();
    $rule = $rules[$type] ?? '/';

    if ($mode === 'dynamic') {
        // 动态模式：全部落到 .php 查询串
        switch ($type) {
            case 'post':     return 'post.php?id=' . (int)$params['id'];
            case 'page':     return 'index.php?page=' . (int)$params['page'];
            case 'category': return 'index.php?cat=' . rawurlencode($params['cat']);
            case 'search':   return 'index.php?q=' . rawurlencode($params['q']);
            case 'archive':  return 'archive.php';
            case 'feed':     return 'feed.php';
            case 'api':      return 'api/index.php';
            default:         return 'index.php';
        }
    }

    if ($mode === 'static') {
        // 静态模式：指向预生成快照
        switch ($type) {
            case 'post':     return 'static/post-' . (int)$params['id'] . '.html';
            case 'page':     return 'static/home-' . (int)$params['page'] . '.html';
            case 'category': return 'static/cat-' . rawurlencode($params['cat']) . '.html';
            case 'search':   return 'index.php?q=' . rawurlencode($params['q']); // 搜索保持动态
            case 'archive':  return 'static/archive.html';
            case 'feed':     return 'static/feed.xml';
            default:         return 'static/home-1.html';
        }
    }

    // 伪静态模式：套用规则模板
    $url = $rule;
    foreach ($params as $k => $v) {
        $url = str_replace('{' . $k . '}', rawurlencode($v), $url);
    }
    return $url;
}

/** 静态快照目录 */
function fly_static_dir()
{
    $d = rtrim(FLY_CACHE, '/\\') . '/static';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    return $d;
}

/** 静态模式：命中且新鲜则直接输出并结束（不连数据库） */
function fly_static_serve($file, $maxAge = 3600)
{
    if (defined('FLY_BUILDING') || defined('FLY_CLI')) {
        return false; // 构建/CLI 期间不读快照，强制渲染
    }
    if (fly_mode() !== 'static') {
        return false;
    }
    $path = fly_static_dir() . '/' . $file;
    if (!is_file($path)) {
        return false;
    }
    if ((time() - filemtime($path)) > $maxAge) {
        return false; // 过期，走动态重新生成
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'xml') {
        header('Content-Type: application/rss+xml; charset=utf-8');
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }
    header('X-FlyLog-Mode: static');
    readfile($path);
    return true;
}

/** 生成/覆盖一个静态快照 */
function fly_static_build($file, $html)
{
    $path = fly_static_dir() . '/' . $file;
    @file_put_contents($path, $html, LOCK_EX);
    return $path;
}
