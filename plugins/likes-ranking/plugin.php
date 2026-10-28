<?php
/**
 * Plugin Name: 点赞排行榜
 * Version: 1.0.0
 * Description: 侧边栏显示点赞最高的 5 篇文章
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
    if (fly_plugin_opt('likes-ranking', 'enable', '1') !== '1') { return; }
    if (!fly_db_has_column('posts', 'likes')) { return; }
    $rows = fly_db()->all(
        "SELECT id, title, likes FROM posts WHERE is_draft = 0 AND deleted_at = 0 ORDER BY likes DESC, id DESC LIMIT 5"
    );
    if (!$rows) { return; }
    $html = '<div class="side-box"><h3>点赞排行</h3><ul>';
    foreach ($rows as $r) {
        $html .= '<li><a href="' . fly_e(fly_url('post', array('id' => (int)$r['id']))) . '">' . fly_e($r['title'])
               . '</a> <span style="color:var(--text-2);font-size:12px">👍 ' . (int)$r['likes'] . '</span></li>';
    }
    echo $html . '</ul></div>';
});
