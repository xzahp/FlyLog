<?php
/**
 * Plugin Name: 文章导出 Markdown
 * Version: 1.0.0
 * Description: 后台一键把文章下载为 .md 文件
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：把文章导出为 .md
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_expmd'] = array('导出 MD', 'plugins.php?admin_page=export_markdown', 'manage_posts', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'export_markdown') { return; }
    if (isset($_GET['dl'])) {
        $id = (int)$_GET['dl'];
        $p = fly_db()->one('SELECT title, content FROM posts WHERE id = ?', array($id));
        if ($p) {
            header('Content-Type: text/markdown; charset=utf-8');
            header('Content-Disposition: attachment; filename="post-' . $id . '.md"');
            echo '# ' . $p['title'] . "\n\n" . strip_tags((string)$p['content']);
            exit;
        }
    }
    $rows = fly_db()->all("SELECT id, title FROM posts WHERE deleted_at = 0 ORDER BY id DESC LIMIT 50");
    echo '<table class="tbl"><thead><tr><th>文章</th><th>操作</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . fly_e($r['title']) . '</td><td><a class="btn btn-sm" href="?admin_page=export_markdown&dl='
           . (int)$r['id'] . '">下载 .md</a></td></tr>';
    }
    echo '</tbody></table>';
});
