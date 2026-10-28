<?php
/**
 * Plugin Name: 阅读时间预估
 * Version: 1.0.0
 * Description: 在正文开头显示「本文约 X 字，阅读约 Y 分钟」
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
    if (fly_plugin_opt('reading-time', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var body = document.querySelector('.post-body');
  if (!body) return;
  var text = body.innerText || body.textContent || '';
  var chars = text.replace(/\s/g,'').length;
  if (!chars) return;
  var min = Math.max(1, Math.round(chars / 400));
  var tip = document.createElement('p');
  tip.className = 'read-time-tip';
  tip.style.cssText = 'color:var(--text-2);font-size:13px;margin:0 0 12px';
  tip.textContent = '本文约 ' + chars + ' 字，阅读约 ' + min + ' 分钟';
  body.parentNode.insertBefore(tip, body);
})();
FLYJS . '</script>';
});
