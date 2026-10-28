<?php
/**
 * Plugin Name: 最新评论侧边栏
 * Version: 1.0.0
 * Description: 侧边栏显示最近 5 条评论（含昵称与摘录，点击直达锚点）
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 侧边栏「最新评论」：取最近 5 条已通过评论
fly_add_action('fly_after_sidebar', function () {
    if (fly_plugin_opt('recent-comments', 'enable', '1') !== '1') { return; }
    if (!fly_db_has_table('comments')) { return; }
    $rows = fly_db()->all(
        "SELECT c.id, c.author, c.content, c.post_id, p.title
         FROM comments c LEFT JOIN posts p ON p.id = c.post_id
         WHERE c.status = 1 ORDER BY c.id DESC LIMIT 5"
    );
    if (!$rows) { return; }
    $html = '<div class="side-box fly-w-recent-comments"><h3>最新留言</h3><ul class="cmt-list">';
    foreach ($rows as $r) {
        $link = fly_url('post', array('id' => (int)$r['post_id'])) . '#comment-' . (int)$r['id'];
        $html .= '<li><b>' . fly_e($r['author']) . '</b>：'
               . '<a href="' . fly_e($link) . '">' . fly_e(fly_excerpt($r['content'], 28)) . '</a></li>';
    }
    $html .= '</ul></div>';
    echo $html;
});
