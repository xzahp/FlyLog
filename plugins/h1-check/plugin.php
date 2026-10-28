<?php
/**
 * Plugin Name: H1 标签检查
 * Version: 1.0.0
 * Description: 检查每篇文章是否只有一个 H1，多则高亮提示
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
    if (fly_plugin_opt('h1-check', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var h1 = document.querySelectorAll('.post-full h1');
  if (h1.length > 1) {
    for (var i = 1; i < h1.length; i++) {
      h1[i].style.outline = '2px dashed #ef4444';
      h1[i].title = 'H1 过多，建议改为 H2/H3';
    }
  }
})();
FLYJS . '</script>';
});
