<?php
/**
 * Plugin Name: 主题切换器
 * Version: 1.0.0
 * Description: 前台一键切换主题（核心 V10.0 主题引擎）
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('theme-switcher', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.theme-switch{border-radius:999px;border:1px solid var(--border);padding:4px 10px;font-size:13px}
.theme-switch:hover{border-color:var(--brand);color:var(--brand)}
FLYCSS . '</style>';
});
