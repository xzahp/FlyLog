<?php
/**
 * Plugin Name: 视频自适应 16:9
 * Version: 1.0.0
 * Description: 嵌入视频（iframe/video）自动按 16:9 缩放
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('video-responsive', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  function wrap(){
    var nodes = document.querySelectorAll('.post-body iframe, .post-body video');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (el.parentNode && el.parentNode.className === 'fly-video') continue;
      var box = document.createElement('div');
      box.className = 'fly-video';
      box.style.cssText = 'position:relative;width:100%;padding-top:56.25%;margin:14px 0';
      el.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0';
      el.parentNode.insertBefore(box, el);
      box.appendChild(el);
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', wrap); } else { wrap(); }
})();
FLYJS . '</script>';
});
