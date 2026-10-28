<?php
/**
 * Plugin Name: 悬浮目录 TOC
 * Version: 1.0.0
 * Description: 调整悬浮目录的宽度、位置与高亮样式
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
    if (fly_plugin_opt('floating-toc', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.toc{position:sticky;top:16px;max-height:70vh;overflow:auto;border-left:2px solid var(--border);padding-left:10px}
.toc .toc-title{font-weight:700;margin-bottom:6px}
.toc ul li.lvl-3{padding-left:12px;font-size:13px}
.toc a.active{color:var(--brand);font-weight:600}
FLYCSS . '</style>';
});
