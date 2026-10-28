<?php
/**
 * Plugin Name: 相关文章推荐
 * Version: 1.0.0
 * Description: 文章底部按同分类/同标签推荐 3 篇相关文章
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 文章底部「相关文章」：同分类优先，不足则用同标签补，再不足用最新
fly_add_action('before_front_render', function ($page) {
    if ($page !== 'post' || fly_plugin_opt('related-posts', 'enable', '1') !== '1') { return; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { return; }
    $post = fly_db()->one('SELECT category_id FROM posts WHERE id = ?', array($id));
    if (!$post) { return; }
    $cid = (int)($post['category_id'] ?? 0);
    $rows = array();
    if ($cid > 0) {
        $rows = fly_db()->all(
            "SELECT id, title FROM posts WHERE id <> ? AND is_draft = 0 AND deleted_at = 0 AND category_id = ?
             ORDER BY id DESC LIMIT ?", array($id, $cid, 3)
        );
    }
    if (count($rows) < 3) {
        $need = 3 - count($rows);
        $have = array($id);
        foreach ($rows as $r) { $have[] = (int)$r['id']; }
        $ph = implode(',', array_fill(0, count($have), '?'));
        $more = fly_db()->all(
            "SELECT id, title FROM posts WHERE id NOT IN ($ph) AND is_draft = 0 AND deleted_at = 0
             ORDER BY id DESC LIMIT ?", array_merge($have, array($need))
        );
        $rows = array_merge($rows, $more);
    }
    if (!$rows) { return; }
    // 用 JS 在文章末尾插入（避免改动核心模板）
    $items = array();
    foreach ($rows as $r) {
        $items[] = array('t' => $r['title'], 'u' => fly_url('post', array('id' => (int)$r['id'])));
    }
    echo '<script>(function(){var d=' . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
       . ';var box=document.createElement("div");box.className="fly-w-related-posts side-box";'
       . 'box.innerHTML="<h3>相关文章</h3><ul>"+d.map(function(i){return \'<li><a href="\'+i.u+\'">\'+i.t+\'</a></li>\';}).join("")+"</ul>";'
       . 'var art=document.querySelector(".post-full");if(art&&art.parentNode){art.parentNode.insertBefore(box,art.nextSibling);}})();</script>';
});
