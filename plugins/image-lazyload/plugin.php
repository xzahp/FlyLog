<?php
/**
 * Plugin Name: 图片懒加载
 * Version: 1.0.0
 * Description: 图片淡入效果，避免懒加载图片突然跳出
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
    if (fly_plugin_opt('image-lazyload', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
img.fly-lazy{opacity:0;transition:opacity .4s ease}
img.fly-lazy.is-loaded{opacity:1}
FLYCSS . '</style>';
});
