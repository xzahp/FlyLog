<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V10.0 原生主题引擎（Theme Engine）
 * ------------------------------------------------------------------
 * 纯原生 PHP，无模板编译层（不引入 Twig/Blade 之类），主题就是普通 PHP 模板：
 *
 *   主题目录：themes/<主题名>/
 *     index.php    首页模板（可选）
 *     post.php     文章页模板（可选）
 *     header.php   页头（可选）
 *     footer.php   页脚（可选）
 *     style.css    主题样式（可选，自动在默认样式之后加载，方便覆盖）
 *     screenshot.png  后台缩略图（可选）
 *
 * 解析顺序（核心原则：主题优先、缺文件回退）：
 *   themes/<当前主题>/<模板>.php  →  templates/<模板>.php
 *
 * 这样即使一个主题只写了 style.css、一个模板都没提供，站点也照常跑（完全兼容 V9.0）。
 * 主题模板里可以直接用核心函数：fly_render('parts/head')、fly_url()、fly_e() ...
 */

// =====================================================================
// 一、目录与当前主题
// =====================================================================

function fly_theme_root()
{
    return defined('FLY_THEMES') ? FLY_THEMES : (FLY_ROOT . '/themes');
}

/** 主题名白名单（同插件，杜绝 ../ 穿越） */
function fly_theme_safe_name($name)
{
    $name = (string)$name;
    if ($name === '' || strlen($name) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
        return '';
    }
    return $name;
}

/**
 * 当前启用的主题名。存 options 表（key: theme.active）。
 * 主题目录不存在时返回 ''，表示「用默认模板」——保证老站/坏主题不会白屏。
 */
function fly_theme_current()
{
    $name = (string)fly_opt('theme.active', '');
    $name = fly_theme_safe_name($name);
    if ($name === '' || !is_dir(fly_theme_root() . '/' . $name)) {
        return '';
    }
    return $name;
}

/** 切换主题（写入 options） */
function fly_theme_set($name)
{
    $name = fly_theme_safe_name($name);
    if ($name === '' || !is_dir(fly_theme_root() . '/' . $name)) {
        return false;
    }
    fly_set_option('theme.active', $name);
    if (function_exists('fly_audit')) {
        fly_audit('theme_switch', 'theme', $name, '切换主题 ' . $name);
    }
    return true;
}

/** 恢复默认模板（不启用任何主题） */
function fly_theme_reset()
{
    fly_set_option('theme.active', '');
    return true;
}

// =====================================================================
// 二、主题信息（头信息写在 style.css 顶部注释里）
// =====================================================================

/** 解析 style.css 顶部的主题头：Theme Name / Version / Author / Description */
function fly_theme_header($dir)
{
    $def = array(
        'name'        => basename($dir),
        'version'     => '1.0.0',
        'author'      => 'FlyLog',
        'description' => '',
    );
    $css = $dir . '/style.css';
    if (!is_file($css)) {
        return $def;
    }
    $src = (string)@file_get_contents($css, false, null, 0, 4096);
    if (!preg_match('#/\*.*?\*/#s', $src, $m)) {
        return $def;
    }
    $map = array(
        'theme name'  => 'name',
        'version'     => 'version',
        'author'      => 'author',
        'description' => 'description',
    );
    foreach (preg_split('/\r\n|\n|\r/', $m[0]) as $line) {
        $line = trim($line, " \t\r\n*/\x0B");
        if (strpos($line, ':') === false) {
            continue;
        }
        list($k, $v) = explode(':', $line, 2);
        $k = strtolower(trim($k));
        $v = trim($v);
        if (isset($map[$k]) && $v !== '') {
            $def[$map[$k]] = $v;
        }
    }
    return $def;
}

/** 单个主题的完整信息 */
function fly_theme_info($name)
{
    $name = fly_theme_safe_name($name);
    if ($name === '') {
        return null;
    }
    $dir = fly_theme_root() . '/' . $name;
    if (!is_dir($dir)) {
        return null;
    }
    $head = fly_theme_header($dir);
    $head['slug']       = $name;
    $head['dir']        = $dir;
    $head['has_style']  = is_file($dir . '/style.css');
    $head['screenshot'] = is_file($dir . '/screenshot.png')
        ? fly_base_url() . '/themes/' . rawurlencode($name) . '/screenshot.png'
        : '';
    $head['templates']  = array();
    foreach (array('index', 'post', 'header', 'footer') as $t) {
        $head['templates'][$t] = is_file($dir . '/' . $t . '.php');
    }
    $head['is_active']  = (fly_theme_current() === $name);
    return $head;
}

/**
 * 扫描 themes/ 目录。
 * 注意：函数名故意叫 fly_theme_scan 而不是 fly_theme_list ——
 * fly_theme_list 是 V8.0「配色主题」（auto/light/dark/aurora/sunset）的清单，
 * 两者同名会直接 fatal，V10.0 必须避开。
 */
function fly_theme_scan()
{
    $root = fly_theme_root();
    $list = array();
    if (!is_dir($root)) {
        return $list;
    }
    foreach (glob($root . '/*', GLOB_ONLYDIR) as $d) {
        $info = fly_theme_info(basename($d));
        if ($info) {
            $list[] = $info;
        }
    }
    usort($list, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $list;
}

// =====================================================================
// 三、模板解析（主题优先 + 回退）
// =====================================================================

/**
 * 解析模板绝对路径：先主题后默认。
 * @param string $file  相对名，如 'index'、'post'、'parts/head'
 * @return string|null  找不到返回 null
 */
function fly_tpl_resolve($file)
{
    $file = ltrim((string)$file, '/');
    if (substr($file, -4) !== '.php') {
        $file .= '.php';
    }
    // 只允许 字母数字 _ - / . ，挡掉 ../ 与绝对路径
    if (!preg_match('#^[A-Za-z0-9_./-]+$#', $file) || strpos($file, '..') !== false) {
        return null;
    }
    $cur = fly_theme_current();
    $candidates = array();
    if ($cur !== '') {
        $candidates[] = fly_theme_root() . '/' . $cur . '/' . $file;
    }
    $candidates[] = FLY_TPL . '/' . $file;

    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            continue;
        }
        // containment：必须落在 themes/<当前主题>/ 或 templates/ 内
        $ok = false;
        if ($cur !== '') {
            $troot = realpath(fly_theme_root() . '/' . $cur);
            if ($troot !== false && strpos($real, $troot . DIRECTORY_SEPARATOR) === 0) {
                $ok = true;
            }
        }
        $tplRoot = realpath(FLY_TPL);
        if (!$ok && $tplRoot !== false && strpos($real, $tplRoot . DIRECTORY_SEPARATOR) === 0) {
            $ok = true;
        }
        if ($ok) {
            return $real;
        }
    }
    return null;
}

/**
 * 取主题专属模板（只在主题目录里找，不回退）。
 * 路由文件用它判断「要不要把整个页面交给主题接管」。
 */
function fly_theme_path($tpl)
{
    $cur = fly_theme_current();
    if ($cur === '') {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $tpl)) {
        return null;
    }
    $path = fly_theme_root() . '/' . $cur . '/' . $tpl . '.php';
    $real = realpath($path);
    $troot = realpath(fly_theme_root() . '/' . $cur);
    if ($real === false || $troot === false || !is_file($real)) {
        return null;
    }
    if (strpos($real, $troot . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/**
 * 渲染模板（主题优先版）：所有老代码调 fly_render 的地方自动获得主题能力。
 * 签名与 V9.0 完全一致，属于原地增强，不破坏兼容。
 */
function fly_render_theme($template, $vars = array())
{
    $path = fly_tpl_resolve($template);
    if ($path === null) {
        return '<!-- 模板不存在: ' . fly_e((string)$template) . ' -->';
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $path;
    return ob_get_clean();
}

/** 主题 style.css 的 URL（带内容 MD5 版本号，绕 CDN 缓存） */
function fly_theme_style_url()
{
    $cur = fly_theme_current();
    if ($cur === '') {
        return '';
    }
    $css = fly_theme_root() . '/' . $cur . '/style.css';
    if (!is_file($css)) {
        return '';
    }
    return fly_base_url() . '/themes/' . rawurlencode($cur) . '/style.css?v=' . substr(md5_file($css), 0, 8);
}

// =====================================================================
// 四、后台在线编辑 style.css
// =====================================================================

/** 读主题 style.css 原文 */
function fly_theme_read_style($name)
{
    $name = fly_theme_safe_name($name);
    if ($name === '') {
        return false;
    }
    $css = fly_theme_root() . '/' . $name . '/style.css';
    if (!is_file($css)) {
        return '';
    }
    return (string)@file_get_contents($css);
}

/** 写主题 style.css（自带 containment + 备份一份 .bak） */
function fly_theme_write_style($name, $content)
{
    $name = fly_theme_safe_name($name);
    if ($name === '') {
        return false;
    }
    $dir = realpath(fly_theme_root() . '/' . $name);
    if ($dir === false) {
        return false;
    }
    $css = $dir . '/style.css';
    if (is_file($css)) {
        @copy($css, $css . '.bak');   // 改坏了一键能找回
    }
    $ok = @file_put_contents($css, $content) !== false;
    if ($ok && function_exists('fly_audit')) {
        fly_audit('theme_css_edit', 'theme', $name, '在线编辑主题 style.css');
    }
    return $ok;
}
