<?php
/**
 * Plugin Name: 视频播放器
 * Version: 1.0.0
 * Description: 文章内视频链接渲染为原生播放器
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
    if (fly_plugin_opt('video-player', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var links = document.querySelectorAll('.post-body a[href$=".mp4"], .post-body a[href$=".webm"]');
  for (var i=0;i<links.length;i++){
    var v = document.createElement('video');
    v.controls = true; v.src = links[i].getAttribute('href');
    v.style.cssText='width:100%;border-radius:8px;margin:8px 0';
    links[i].parentNode.insertBefore(v, links[i]);
    links[i].style.display='none';
  }
})();
FLYJS . '</script>';
});
