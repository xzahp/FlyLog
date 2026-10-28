<?php
/**
 * Plugin Name: 热门标签
 * Version: 1.0.0
 * Description: 侧边栏显示文章数最多的前 10 个标签
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
    if (fly_plugin_opt('hot-tags', 'enable', '1') !== '1') { return; }
    if (!function_exists('fly_tag_all')) { return; }
    $tags = fly_tag_all();
    if (!$tags) { return; }
    usort($tags, function ($a, $b) { return (int)($b['count'] ?? 0) <=> (int)($a['count'] ?? 0); });
    $tags = array_slice($tags, 0, 10);
    $html = '<div class="side-box"><h3>热门标签</h3><div class="tag-cloud">';
    foreach ($tags as $t) {
        $html .= '<a href="' . fly_e(fly_url('tag', array('slug' => $t['slug']))) . '">#' . fly_e($t['name']) . '</a>';
    }
    echo $html . '</div></div>';
});
