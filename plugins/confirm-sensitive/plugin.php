<?php
/**
 * Plugin Name: 敏感操作二次确认
 * Version: 1.0.0
 * Description: 删除文章/清空数据前要求二次输入确认
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('confirm-sensitive', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var forms = document.querySelectorAll('form');
  for (var i=0;i<forms.length;i++){
    (function(f){
      var op = f.querySelector('input[name="op"]');
      if (!op) return;
      if (/delete|purge|truncate|reset/i.test(op.value)) {
        f.addEventListener('submit', function(e){
          if (!confirm('这是敏感操作：确定继续？该动作不可撤销。')) { e.preventDefault(); }
        });
      }
    })(forms[i]);
  }
})();
FLYJS . '</script>';
});
