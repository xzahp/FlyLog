<?php
/**
 * Plugin Name: 热门文章侧边栏
 * Version: 1.0.0
 * Description: 热门文章列表加序号徽章
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
    if (fly_plugin_opt('popular-posts', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.side-box .hot-list li{display:flex;gap:8px;align-items:baseline}
.side-box .hot-list li::before{content:counter(hot);counter-increment:hot;
  background:var(--brand);color:#fff;border-radius:50%;width:18px;height:18px;
  font-size:11px;display:inline-flex;align-items:center;justify-content:center;flex:none}
.side-box .hot-list{counter-reset:hot}
FLYCSS . '</style>';
});
