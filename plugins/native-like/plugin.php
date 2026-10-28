<?php
/**
 * Plugin Name: 原生点赞
 * Version: 1.0.0
 * Description: 基于 IP+文章ID 去重的点赞，可取消（核心已内置，本插件定制按钮样式）
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
    if (fly_plugin_opt('native-like', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-act-btn{border-radius:999px;padding:6px 14px;border:1px solid var(--border);background:var(--surface);cursor:pointer}
.fly-act-btn.active{border-color:var(--brand);color:var(--brand);background:rgba(31,111,235,.08)}
.fly-act-btn:hover{transform:translateY(-1px)}
FLYCSS . '</style>';
});
