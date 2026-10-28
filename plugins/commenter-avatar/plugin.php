<?php
/**
 * Plugin Name: 评论者头像
 * Version: 1.0.0
 * Description: 根据昵称生成稳定的彩色首字母头像（纯本地，无外链）
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
    if (fly_plugin_opt('commenter-avatar', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  function hash(s){ var h=0; for(var i=0;i<s.length;i++){ h=(h*31+s.charCodeAt(i))>>>0; } return h; }
  var names = document.querySelectorAll('.comment-item .c-author');
  for (var i=0;i<names.length;i++){
    var el = names[i];
    if (el.getAttribute('data-avatar')) continue;
    var nm = (el.textContent||'').trim() || '访客';
    var hue = hash(nm) % 360;
    var av = document.createElement('span');
    av.textContent = nm.charAt(0).toUpperCase();
    av.style.cssText = 'display:inline-flex;width:28px;height:28px;border-radius:50%;align-items:center;'+
      'justify-content:center;color:#fff;font-size:13px;margin-right:8px;background:hsl('+hue+',62%,52%)';
    el.parentNode.insertBefore(av, el);
    el.setAttribute('data-avatar','1');
  }
})();
FLYJS . '</script>';
});
