<?php
/**
 * Plugin Name: Robots.txt 可视化
 * Version: 1.0.0
 * Description: 后台可视化编辑 robots.txt 内容
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：可视化编辑 robots.txt
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_robots'] = array('robots.txt', 'plugins.php?admin_page=robots_editor', 'manage_settings', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'robots_editor') { return; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['plg_op'] ?? '') === 'save_robots') {
        @file_put_contents(FLY_ROOT . '/robots.txt', (string)($_POST['robots'] ?? ''));
        echo '<div class="msg msg-ok" style="margin:10px 0">robots.txt 已保存</div>';
    }
    $cur = is_file(FLY_ROOT . '/robots.txt') ? (string)@file_get_contents(FLY_ROOT . '/robots.txt') : "User-agent: *\nAllow: /";
    echo '<form method="post" style="margin:12px 0"><input type="hidden" name="plg_op" value="save_robots">'
       . '<textarea name="robots" style="width:100%;min-height:200px;font-family:monospace">' . fly_e($cur) . '</textarea>'
       . '<p style="margin-top:8px"><button class="btn" type="submit">保存 robots.txt</button></p></form>';
});
