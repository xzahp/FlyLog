<?php
/**
 * Plugin Name: PDF 在线预览
 * Version: 1.0.0
 * Description: 文章内 PDF 链接用浏览器原生预览打开
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
    if (fly_plugin_opt('pdf-preview', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var links = document.querySelectorAll('.post-body a[href$=".pdf"], .post-body a[href*=".pdf?"]');
  for (var i=0;i<links.length;i++){
    links[i].setAttribute('target','_blank');
    links[i].setAttribute('rel','noopener');
    links[i].textContent += ' （PDF 预览）';
  }
})();
FLYJS . '</script>';
});
