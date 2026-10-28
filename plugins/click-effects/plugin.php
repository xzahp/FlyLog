<?php
/**
 * Plugin Name: 鼠标点击特效
 * Version: 1.0.0
 * Description: 点击页面出现小星星（可在设置里关闭）
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
    if (fly_plugin_opt('click-effects', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  if (localStorage.getItem('fly_click_fx') === 'off') return;
  document.addEventListener('click', function(e){
    for (var i=0;i<6;i++){
      var s = document.createElement('span');
      s.textContent = '✦';
      s.style.cssText = 'position:fixed;left:' + e.clientX + 'px;top:' + e.clientY + 'px;'+
        'pointer-events:none;color:#22d3ee;font-size:' + (10+Math.random()*10) + 'px;z-index:9999;transition:all .6s ease-out';
      document.body.appendChild(s);
      (function(el, dx, dy){
        requestAnimationFrame(function(){
          el.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(.2)';
          el.style.opacity = '0';
        });
        setTimeout(function(){ el.remove(); }, 620);
      })(s, (Math.random()-0.5)*90, -30 - Math.random()*50);
    }
  });
})();
FLYJS . '</script>';
});
