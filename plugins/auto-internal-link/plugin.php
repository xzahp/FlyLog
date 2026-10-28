<?php
/**
 * Plugin Name: 自动内链解析
 * Version: 1.0.0
 * Description: 给自动生成的内链加虚线下划线，提示可点击
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
    if (fly_plugin_opt('auto-internal-link', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body a.fly-internal-link{border-bottom:1px dashed var(--brand);text-decoration:none}
FLYCSS . '</style>';
});
