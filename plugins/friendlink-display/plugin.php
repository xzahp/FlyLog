<?php
/**
 * Plugin Name: 友情链接展示
 * Version: 1.0.0
 * Description: 侧边栏展示审核通过的友情链接（V9.0 核心已支持，本插件补充样式）
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('friendlink-display', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.link-list{display:flex;flex-direction:column;gap:8px}
.link-item{display:flex;align-items:center;gap:8px;text-decoration:none;padding:6px 8px;border-radius:8px}
.link-item:hover{background:var(--bg)}
.link-initial{width:24px;height:24px;border-radius:6px;background:var(--brand);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:12px}
FLYCSS . '</style>';
});
