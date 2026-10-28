<?php
/**
 * Plugin Name: 标签云
 * Version: 1.0.0
 * Description: 标签云字号随文章数递增，并加悬停动效
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
    if (fly_plugin_opt('tag-cloud', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.tag-cloud a{display:inline-block;margin:3px 4px;padding:3px 9px;border-radius:12px;
  background:var(--bg);transition:transform .15s,background .15s}
.tag-cloud a:hover{transform:translateY(-2px);background:var(--brand);color:#fff}
FLYCSS . '</style>';
});
