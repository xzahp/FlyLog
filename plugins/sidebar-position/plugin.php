<?php
/**
 * Plugin Name: 侧边栏位置
 * Version: 1.0.0
 * Description: 侧边栏切换到左侧显示
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
    if (fly_plugin_opt('sidebar-position', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.layout{display:flex;flex-direction:row-reverse;gap:20px}
@media (max-width:900px){.layout{flex-direction:column}}
FLYCSS . '</style>';
});
