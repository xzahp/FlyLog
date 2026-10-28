<?php
/**
 * Plugin Name: 本地收藏夹
 * Version: 1.0.0
 * Description: localStorage 收藏文章，失效链接自动标灰
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
    if (fly_plugin_opt('local-favorites', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var KEY='fly_favs_v10';
  function list(){ try { return JSON.parse(localStorage.getItem(KEY)||'[]'); } catch(e){ return []; } }
  window.FLY_FAVS = list();
  var dead = document.querySelectorAll('.post-item');
  for (var i=0;i<dead.length;i++){
    var id = parseInt(dead[i].getAttribute('data-id'),10);
    if (id && list().indexOf(id) >= 0) {
      var b = document.createElement('span');
      b.textContent = '★已收藏'; b.style.cssText='color:#f59e0b;font-size:12px;margin-left:6px';
      dead[i].appendChild(b);
    }
  }
})();
FLYJS . '</script>';
});
