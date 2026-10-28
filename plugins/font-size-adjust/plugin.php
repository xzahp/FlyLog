<?php
/**
 * Plugin Name: 字体大小调节
 * Version: 1.0.0
 * Description: 把正文基准字号放大一档，并让调整即时生效
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
    if (fly_plugin_opt('font-size-adjust', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body{font-size:calc(var(--fly-fs,16px) + 1px);line-height:1.85}
FLYCSS . '</style>';
});
