<?php
/**
 * Plugin Name: 代码高亮主题切换
 * Version: 1.0.0
 * Description: 暗色模式自动切到 GitHub-Dark 高亮主题
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
    if (fly_plugin_opt('code-highlight-theme', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  function sync(){
    var dark = document.documentElement.getAttribute('data-theme') === 'dark'
      || (document.documentElement.getAttribute('data-theme') === 'auto'
          && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var link = document.getElementById('fly-hl-style');
    if (!link) return;
    var target = dark ? 'highlight-github-dark.min.css' : 'highlight-github.min.css';
    if (link.getAttribute('href').indexOf(target) === -1) {
      link.setAttribute('href', link.getAttribute('href').replace(/highlight-github[^/]*\.min\.css/, target));
    }
  }
  sync();
  new MutationObserver(sync).observe(document.documentElement, {attributes:true, attributeFilter:['data-theme']});
})();
FLYJS . '</script>';
});
