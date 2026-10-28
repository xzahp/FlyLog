<?php
/**
 * Plugin Name: 脚注样式
 * Version: 1.0.0
 * Description: 脚注区块与上标链接的排版样式
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
    if (fly_plugin_opt('footnotes-style', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-footnotes{margin-top:26px;padding-top:14px;border-top:1px dashed var(--border);font-size:14px;color:var(--text-2)}
.fly-footnotes ol{padding-left:20px}
.fly-fn a{text-decoration:none;padding:0 2px}
FLYCSS . '</style>';
});
