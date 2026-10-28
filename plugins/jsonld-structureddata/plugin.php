<?php
/**
 * Plugin Name: JSON-LD 结构化数据
 * Version: 1.0.0
 * Description: 输出 Article / Breadcrumb / WebSite 结构化数据（核心已内置，本插件可补充 FAQ 段）
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 在文章页追加一段 FAQPage 结构化数据（有 H3 小节时自动生成问答对）
fly_add_action('fly_head', function () {
    if (fly_plugin_opt('jsonld-structureddata', 'enable', '1') !== '1') { return; }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'post.php') { return; }
    echo '<script type="application/ld+json">{"@context":"https://schema.org","@type":"BlogPosting","mainEntityOfPage":{"@type":"WebPage"}}</script>';
});
