<?php
/**
 * Plugin Name: 外链自动 nofollow
 * Version: 1.0.0
 * Description: 给站外链接加一个外链小图标
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
    if (fly_plugin_opt('external-nofollow', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body a[rel*="nofollow"]::after{content:'↗';font-size:11px;margin-left:2px;opacity:.6}
FLYCSS . '</style>';
});
