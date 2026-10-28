<?php
/**
 * Plugin Name: 代码雨彩蛋
 * Version: 1.0.0
 * Description: 连续按 ↑↑↓↓ 触发代码雨彩蛋
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
    if (fly_plugin_opt('code-rain-egg', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var seq = [], want = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown'];
  document.addEventListener('keydown', function(e){
    seq.push(e.key);
    if (seq.length > 4) { seq.shift(); }
    if (seq.join(',') === want.join(',')) {
      seq = [];
      var cv = document.createElement('canvas');
      cv.style.cssText='position:fixed;inset:0;z-index:9998;pointer-events:none;opacity:.82';
      document.body.appendChild(cv);
      var ctx = cv.getContext('2d');
      cv.width = innerWidth; cv.height = innerHeight;
      var cols = Math.floor(cv.width / 14), drops = [];
      for (var i=0;i<cols;i++) { drops[i] = Math.random() * cv.height; }
      var chars = '01ｱｲｳｴｵｶｷｸFlyLog'.split('');
      var timer = setInterval(function(){
        ctx.fillStyle = 'rgba(0,0,0,.08)'; ctx.fillRect(0,0,cv.width,cv.height);
        ctx.fillStyle = '#22d3ee'; ctx.font = '14px monospace';
        for (var j=0;j<drops.length;j++){
          ctx.fillText(chars[Math.floor(Math.random()*chars.length)], j*14, drops[j]);
          drops[j] = (drops[j] > cv.height && Math.random() > .975) ? 0 : drops[j] + 14;
        }
      }, 55);
      setTimeout(function(){ clearInterval(timer); cv.remove(); }, 6000);
    }
  });
})();
FLYJS . '</script>';
});
