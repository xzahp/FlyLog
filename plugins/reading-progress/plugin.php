<?php
/**
 * Plugin Name: 阅读进度条
 * Version: 1.0.0
 * Description: 定制顶部阅读进度条的高度与配色（核心已内置进度条，本插件负责外观微调）
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('reading-progress', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-progress{height:4px !important;background:linear-gradient(90deg,#1f6feb,#22d3ee) !important}
FLYCSS . '</style>';
});
