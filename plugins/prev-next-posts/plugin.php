<?php
/**
 * Plugin Name: 上一篇/下一篇
 * Version: 1.0.0
 * Description: 文章底部显示相邻文章导航
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 文章底部「上一篇 / 下一篇」
fly_add_action('before_front_render', function ($page) {
    if ($page !== 'post' || fly_plugin_opt('prev-next-posts', 'enable', '1') !== '1') { return; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { return; }
    $prev = fly_db()->one(
        "SELECT id, title FROM posts WHERE id < ? AND is_draft = 0 AND deleted_at = 0 ORDER BY id DESC LIMIT 1", array($id)
    );
    $next = fly_db()->one(
        "SELECT id, title FROM posts WHERE id > ? AND is_draft = 0 AND deleted_at = 0 ORDER BY id ASC LIMIT 1", array($id)
    );
    if (!$prev && !$next) { return; }
    $html = '<nav class="prev-next-posts" style="display:flex;justify-content:space-between;gap:14px;margin:18px 0">';
    $html .= $prev
        ? '<a href="' . fly_e(fly_url('post', array('id' => (int)$prev['id']))) . '">&laquo; ' . fly_e($prev['title']) . '</a>'
        : '<span></span>';
    $html .= $next
        ? '<a href="' . fly_e(fly_url('post', array('id' => (int)$next['id']))) . '">' . fly_e($next['title']) . ' &raquo;</a>'
        : '<span></span>';
    $html .= '</nav>';
    // 插到文章末尾（DOM 就绪后）
    echo '<script>(function(){var h=' . json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
       . ';function ins(){var a=document.querySelector(".post-full");'
       . 'if(a&&a.parentNode){var d=document.createElement("div");d.innerHTML=h;a.parentNode.insertBefore(d,a.nextSibling);}}'
       . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",ins);}else{ins();}})();</script>';
});
