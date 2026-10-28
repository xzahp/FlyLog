<?php
/**
 * Plugin Name: 表格横向滚动
 * Version: 1.0.0
 * Description: 宽表格在移动端自动横向滚动，不撑破排版
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
    if (fly_plugin_opt('table-scroll', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body table{display:block;overflow-x:auto;white-space:nowrap;max-width:100%}
.post-body table td,.post-body table th{min-width:90px}
FLYCSS . '</style>';
});
