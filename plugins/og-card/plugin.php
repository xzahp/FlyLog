<?php
/**
 * Plugin Name: OG 卡片生成
 * Version: 1.0.0
 * Description: 补全分享卡片所需的 og:site_name / og:locale 等元信息
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('og-card', 'enable', '1') !== '1') { return; }
    echo '<meta property="og:site_name" content="' . fly_e(fly_config('site.name', 'FlyLog')) . '">';
    echo '<meta property="og:locale" content="zh_CN">';
});
