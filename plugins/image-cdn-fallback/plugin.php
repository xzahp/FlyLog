<?php
/**
 * Plugin Name: 图片 CDN 回退
 * Version: 1.0.0
 * Description: 本地图片加载失败时回退到备用地址
 * Author: FlyLog
 * Category: 媒体
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('image-cdn-fallback', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var alt = (window.FLY_IMG_FALLBACK || '');
  if (!alt) return;
  var imgs = document.querySelectorAll('.post-body img');
  for (var i=0;i<imgs.length;i++){
    imgs[i].addEventListener('error', function(){
      if (this.getAttribute('data-fallback')) return;
      this.setAttribute('data-fallback','1');
      this.src = alt + this.getAttribute('src').split('/').pop();
    });
  }
})();
FLYJS . '</script>';
});
