<?php
/**
 * Plugin Name: 在线编辑 CSS
 * Version: 1.0.0
 * Description: 后台直接编辑自定义 CSS（存数据库，无需改文件）
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：在线编辑自定义 CSS（存 options，不动源文件）
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_css'] = array('自定义 CSS', 'plugins.php?admin_page=online_css_editor', 'manage_settings', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'online_css_editor') { return; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['plg_op'] ?? '') === 'save_css') {
        fly_plugin_set_opt('online-css-editor', 'css', (string)($_POST['css'] ?? ''));
        echo '<div class="msg msg-ok" style="margin:10px 0">自定义 CSS 已保存</div>';
    }
    $css = (string)fly_plugin_opt('online-css-editor', 'css', '');
    echo '<form method="post" style="margin:12px 0"><input type="hidden" name="plg_op" value="save_css">'
       . '<textarea name="css" style="width:100%;min-height:260px;font-family:monospace">' . fly_e($css) . '</textarea>'
       . '<p style="margin-top:8px"><button class="btn" type="submit">保存</button></p>'
       . '<p class="hint">这段 CSS 会追加在主题样式之后，优先级最高。写错只会影响外观，不会让站点打不开。</p></form>';
});
fly_add_action('fly_head', function () {
    if (fly_plugin_opt('online-css-editor', 'enable', '1') !== '1') { return; }
    $css = (string)fly_plugin_opt('online-css-editor', 'css', '');
    if ($css !== '') { echo '<style>' . $css . '</style>'; }
});
