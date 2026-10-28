<?php
/**
 * Plugin Name: 草稿自动保存
 * Version: 1.0.0
 * Description: 每 30 秒把编辑器内容存进 localStorage
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('draft-autosave', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var ta = document.querySelector('textarea[name="content"]');
  var ti = document.querySelector('input[name="title"]');
  if (!ta) return;
  var KEY = 'fly_draft_' + location.pathname;
  var saved = null;
  try { saved = localStorage.getItem(KEY); } catch(e){}
  if (saved && !ta.value && confirm('检测到未提交的草稿，是否恢复？')) { ta.value = saved; }
  setInterval(function(){
    try { localStorage.setItem(KEY, ta.value); } catch(e){}
    var tip = document.getElementById('autosave-tip');
    if (!tip) {
      tip = document.createElement('span'); tip.id='autosave-tip';
      tip.style.cssText='font-size:12px;color:#9ca3af;margin-left:8px';
      ta.parentNode.appendChild(tip);
    }
    tip.textContent = '已自动保存 ' + new Date().toLocaleTimeString();
  }, 30000);
})();
FLYJS . '</script>';
});
