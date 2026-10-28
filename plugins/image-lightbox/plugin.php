<?php
/**
 * Plugin Name: 图片灯箱
 * Version: 1.0.0
 * Description: 让正文图片鼠标悬停显示放大镜光标，并给灯箱加圆角阴影
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
    if (fly_plugin_opt('image-lightbox', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body img{cursor:zoom-in;transition:transform .18s ease}
.post-body img:hover{transform:scale(1.01)}
.fly-lb img{border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
FLYCSS . '</style>';
});
