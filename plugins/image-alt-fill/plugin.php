<?php
/**
 * Plugin Name: 图片 Alt 自动填充
 * Version: 1.0.0
 * Description: 正文里没有 alt 的图片自动用文件名补上（利于无障碍与 SEO）
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('image-alt-fill', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var imgs = document.querySelectorAll('.post-body img');
  for (var i = 0; i < imgs.length; i++) {
    if (!imgs[i].getAttribute('alt')) {
      var src = imgs[i].getAttribute('src') || '';
      var name = src.split('/').pop().split('?')[0];
      imgs[i].setAttribute('alt', decodeURIComponent(name));
    }
  }
})();
FLYJS . '</script>';
});
