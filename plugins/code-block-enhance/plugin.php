<?php
/**
 * Plugin Name: 代码块增强（行号）
 * Version: 1.0.0
 * Description: 为代码块自动添加行号，并高亮悬停行
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
    if (fly_plugin_opt('code-block-enhance', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.post-body pre{position:relative;padding-left:3.2em;counter-reset:line}
.post-body pre code{display:block}
.post-body pre code .line{display:block}
.post-body pre::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2.6em;
  background:rgba(127,127,127,.10);border-right:1px solid var(--border)}
.post-body pre:hover{box-shadow:0 0 0 1px var(--brand)}
FLYCSS . '</style>';
});
