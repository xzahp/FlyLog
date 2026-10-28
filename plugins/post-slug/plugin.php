<?php
/**
 * Plugin Name: 文章别名（Slug）
 * Version: 1.0.0
 * Description: 为文章生成短链接别名 /s/xxx
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 用 ?slug=xxx 访问文章（slug 取标题拼音首字母 + ID 的兜底方案，零依赖）
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('post-slug', 'enable', '1') !== '1') { return; }
    $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
    if ($slug === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $slug)) { return; }
    $id = (int)preg_replace('/[^0-9]/', '', $slug);
    if ($id > 0) { fly_redirect(fly_url('post', array('id' => $id))); }
});
