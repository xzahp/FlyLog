<?php
/**
 * Plugin Name: 复制防抄袭
 * Version: 1.0.0
 * Description: 复制超过 50 字自动追加原文出处（核心已内置，本插件调节阈值文案）
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('copy-protection', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var THRESHOLD = 50;
  document.addEventListener('copy', function(e){
    var sel = (window.getSelection() || '').toString();
    if (!sel || sel.length < THRESHOLD) return;
    var tail = '\n—— 本文来自『' + (window.FLY_SITE_NAME || document.title) + '』\n原文：' + location.href;
    if (e.clipboardData) { e.clipboardData.setData('text/plain', sel + tail); e.preventDefault(); }
  });
})();
FLYJS . '</script>';
});
