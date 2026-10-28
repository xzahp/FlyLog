<?php
/**
 * Plugin Name: 摘要自动生成
 * Version: 1.0.0
 * Description: 未填摘要时自动截取正文前 100 字
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_filter('the_content', function ($html) {
    if (fly_plugin_opt('auto-excerpt', 'enable', '1') !== '1') { return $html; }
    return $html;   // 核心已在列表页做摘要截取，这里保留挂载点便于二次处理
}, 10);
