<?php
/**
 * Plugin Name: 视频容器样式
 * Version: 1.0.0
 * Description: 配合视频自适应插件的容器圆角与阴影
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
    if (fly_plugin_opt('video-responsive-style', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-video{border-radius:10px;overflow:hidden;background:#000;box-shadow:0 6px 18px rgba(0,0,0,.12)}
FLYCSS . '</style>';
});
