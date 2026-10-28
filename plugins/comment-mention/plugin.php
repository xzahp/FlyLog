<?php
/**
 * Plugin Name: 评论 @ 提醒
 * Version: 1.0.0
 * Description: 评论里 @某人 时高亮并提示（本地记录最近被 @ 的人）
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('comment-mention', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var txt = document.getElementById('content');
  if (!txt) return;
  txt.addEventListener('input', function(){
    var m = txt.value.match(/@([^\s@:：,，]{1,20})/g);
    var tip = document.getElementById('mention-tip');
    if (!m) { if (tip) tip.textContent=''; return; }
    if (!tip) {
      tip = document.createElement('p'); tip.id='mention-tip';
      tip.style.cssText='font-size:12.5px;color:var(--brand);margin:4px 0 0';
      txt.parentNode.insertBefore(tip, txt.nextSibling);
    }
    tip.textContent = '将提醒：' + m.join('、');
  });
})();
FLYJS . '</script>';
});
