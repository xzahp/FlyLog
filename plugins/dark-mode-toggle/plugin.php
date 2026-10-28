<?php
/**
 * Plugin Name: 深色模式切换
 * Version: 1.0.0
 * Description: 深色主题下的配色微调（更柔和的对比度）
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
    if (fly_plugin_opt('dark-mode-toggle', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
[data-theme="dark"] .post-body{color:#d6dbe4}
[data-theme="dark"] .post-body code{background:#111827}
[data-theme="dark"] .post-item{border-color:#243049}
FLYCSS . '</style>';
});
