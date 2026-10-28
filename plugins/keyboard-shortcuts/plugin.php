<?php
/**
 * Plugin Name: 键盘快捷键
 * Version: 1.0.0
 * Description: 按 / 聚焦搜索，按 Esc 清空
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
    if (fly_plugin_opt('keyboard-shortcuts', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  document.addEventListener('keydown', function(e){
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') {
      if (e.key === 'Escape') { e.target.value=''; e.target.blur(); }
      return;
    }
    if (e.key === '/') {
      var q = document.querySelector('input[name="q"]');
      if (q) { e.preventDefault(); q.focus(); }
    }
  });
})();
FLYJS . '</script>';
});
