<?php
/**
 * Plugin Name: 侧边栏组件开关
 * Version: 1.0.0
 * Description: 按需开启/关闭热门文章、标签云等侧栏组件
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('sidebar-widgets-toggle', 'enable', '1') !== '1') { return; }
    $hide = (string)fly_plugin_opt('sidebar-widgets-toggle', 'hide', '');
    if ($hide === '') { return; }
    $map = array('热门文章' => '.side-box:has(.hot-list)', '标签云' => '.tag-cloud',
                 '最新评论' => '.side-box.fly-w-recent-comments', '相关文章' => '.fly-w-related-posts');
    $css = '';
    foreach (array_map('trim', explode(',', $hide)) as $k) {
        if (isset($map[$k])) { $css .= $map[$k] . '{display:none !important}'; }
    }
    if ($css !== '') { echo '<style>' . $css . '</style>'; }
});
