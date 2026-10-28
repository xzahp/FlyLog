<?php
/**
 * Plugin Name: 音频播放器
 * Version: 1.0.0
 * Description: 文章内音频链接渲染为原生播放器
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
    if (fly_plugin_opt('audio-player', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var links = document.querySelectorAll('.post-body a[href$=".mp3"], .post-body a[href$=".m4a"], .post-body a[href$=".ogg"]');
  for (var i=0;i<links.length;i++){
    var a = document.createElement('audio');
    a.controls = true; a.src = links[i].getAttribute('href');
    a.style.cssText='width:100%;margin:8px 0';
    links[i].parentNode.insertBefore(a, links[i]);
    links[i].style.display='none';
  }
})();
FLYJS . '</script>';
});
