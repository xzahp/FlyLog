<?php
/**
 * Plugin Name: 关键词密度统计
 * Version: 1.0.0
 * Description: 后台显示文章内关键词出现频率
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：显示最近文章的关键词密度
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_kwd'] = array('关键词密度', 'plugins.php?admin_page=keyword_density', 'manage_posts', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'keyword_density') { return; }
    $rows = fly_db()->all("SELECT id, title, content FROM posts WHERE is_draft = 0 AND deleted_at = 0 ORDER BY id DESC LIMIT 10");
    echo '<table class="tbl"><thead><tr><th>文章</th><th>字数</th><th>高频词 Top5</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $text = trim(strip_tags((string)$r['content']));
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $freq = array();
        for ($i = 0; $i < count($chars) - 1; $i++) {
            if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chars[$i]) && preg_match('/[\x{4e00}-\x{9fa5}]/u', $chars[$i + 1])) {
                $w = $chars[$i] . $chars[$i + 1];
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
        }
        arsort($freq);
        $top = array_slice(array_keys($freq), 0, 5);
        echo '<tr><td>' . fly_e($r['title']) . '</td><td>' . count($chars) . '</td><td>'
           . fly_e(implode('、', $top)) . '</td></tr>';
    }
    echo '</tbody></table>';
});
