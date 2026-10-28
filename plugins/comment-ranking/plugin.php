<?php
/**
 * Plugin Name: 评论排行榜
 * Version: 1.0.0
 * Description: 侧边栏显示评论数最多的 5 篇文章
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


fly_add_action('fly_after_sidebar', function () {
    if (fly_plugin_opt('comment-ranking', 'enable', '1') !== '1') { return; }
    if (!fly_db_has_table('comments')) { return; }
    $rows = fly_db()->all(
        "SELECT p.id, p.title, COUNT(c.id) AS cnt FROM posts p JOIN comments c ON c.post_id = p.id
         WHERE p.is_draft = 0 AND p.deleted_at = 0 AND c.status = 1
         GROUP BY p.id, p.title ORDER BY cnt DESC LIMIT 5"
    );
    if (!$rows) { return; }
    $html = '<div class="side-box"><h3>评论最多</h3><ul>';
    foreach ($rows as $r) {
        $html .= '<li><a href="' . fly_e(fly_url('post', array('id' => (int)$r['id']))) . '">' . fly_e($r['title'])
               . '</a> <span style="color:var(--text-2);font-size:12px">' . (int)$r['cnt'] . ' 条</span></li>';
    }
    echo $html . '</ul></div>';
});
