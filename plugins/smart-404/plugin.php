<?php
/**
 * Plugin Name: 404 智能推荐
 * Version: 1.0.0
 * Description: 404 页面显示热门文章推荐，减少跳出
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 404 页面推荐热门文章
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('smart-404', 'enable', '1') !== '1') { return; }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== '404.php') { return; }
    $GLOBALS['FLY_404_HOT'] = fly_db()->all(
        "SELECT id, title FROM posts WHERE is_draft = 0 AND deleted_at = 0 ORDER BY views DESC, id DESC LIMIT 5"
    );
});
fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('smart-404', 'enable', '1') !== '1') { return; }
    $rows = $GLOBALS['FLY_404_HOT'] ?? array();
    if (!$rows) { return; }
    $html = '<div style="max-width:520px;margin:20px auto"><h3>也许你感兴趣</h3><ul>';
    foreach ($rows as $r) {
        $html .= '<li><a href="' . fly_e(fly_url('post', array('id' => (int)$r['id']))) . '">' . fly_e($r['title']) . '</a></li>';
    }
    $html .= '</ul></div>';
    echo $html;
});
