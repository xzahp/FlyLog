<?php
/**
 * Plugin Name: 文章列表布局
 * Version: 1.0.0
 * Description: 首页列表切换为网格卡片布局
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
    if (fly_plugin_opt('list-layout', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.main-col .post-item{display:inline-block;width:calc(50% - 10px);margin:0 8px 14px 0;vertical-align:top}
@media (max-width:640px){.main-col .post-item{width:100%;margin-right:0}}
FLYCSS . '</style>';
});
