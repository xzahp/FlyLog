<?php
/**
 * Plugin Name: 每页文章数
 * Version: 1.0.0
 * Description: 设置首页每页显示几篇
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_filter('pre_post_save', function ($data, $id) { return $data; }, 10);   // 占位
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('posts-per-page', 'enable', '0') !== '1') { return; }
    $n = (int)fly_plugin_opt('posts-per-page', 'count', 10);
    if ($n > 0 && $n <= 50 && isset($_GET['page'])) {
        // 通过 GET 覆盖首页分页大小（核心读取 $_GET['limit'] 时生效）
        $_GET['limit'] = $n;
    }
});
