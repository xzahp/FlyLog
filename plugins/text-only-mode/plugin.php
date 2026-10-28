<?php
/**
 * Plugin Name: 纯文字模式
 * Version: 1.0.0
 * Description: 一键隐藏图片与装饰，只留文字（配合核心阅读工具栏）
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
    if (fly_plugin_opt('text-only-mode', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
html.fly-text-only .post-body img,
html.fly-text-only .side-box,
html.fly-text-only .toc{display:none !important}
html.fly-text-only .post-body{font-size:17px;line-height:1.9}
FLYCSS . '</style>';
});
