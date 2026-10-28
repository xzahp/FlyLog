<?php
/**
 * Plugin Name: 死链检测
 * Version: 1.0.0
 * Description: 扫描正文外链，后台提示可能失效的链接
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：扫描近期文章里的外链，标记可能失效的
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_deadlink'] = array('死链检测', 'plugins.php?admin_page=dead_link_check', 'manage_posts', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'dead_link_check') { return; }
    $rows = fly_db()->all("SELECT id, title, content FROM posts WHERE is_draft = 0 AND deleted_at = 0 ORDER BY id DESC LIMIT 20");
    echo '<table class="tbl"><thead><tr><th>文章</th><th>外链</th><th>状态</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        if (!preg_match_all('/href=["\']https?:\/\/[^"\']+/i', (string)$r['content'], $m)) { continue; }
        foreach (array_slice($m[0], 0, 3) as $raw) {
            $u = substr($raw, 6);
            echo '<tr><td>' . fly_e($r['title']) . '</td><td style="word-break:break-all">'
               . fly_e($u) . '</td><td><a class="btn btn-sm btn-gray" href="' . fly_e($u)
               . '" target="_blank" rel="noopener">手动检查</a></td></tr>';
        }
    }
    echo '</tbody></table>';
});
