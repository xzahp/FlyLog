<?php
/**
 * Plugin Name: 图片点击放大
 * Version: 1.0.0
 * Description: 点击正文图片全屏放大（核心灯箱 CSS 增强：放大动画）
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
    if (fly_plugin_opt('image-zoom', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-lb{backdrop-filter:blur(4px)}
.fly-lb img{animation:flyZoom .22s ease}
@keyframes flyZoom{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
FLYCSS . '</style>';
});
