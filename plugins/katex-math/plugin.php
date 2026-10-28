<?php
/**
 * Plugin Name: 数学公式渲染（KaTeX）
 * Version: 1.0.0
 * Description: 渲染 LaTeX 公式：$行内$ 与 $$块级$$（本地 KaTeX，无 CDN）
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
    if (fly_plugin_opt('katex-math', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var body = document.querySelector('.post-body');
  if (!body) return;
  var txt = body.textContent || '';
  if (txt.indexOf('$') === -1) return;
  function load(href, cb){
    var s = document.createElement('script'); s.src = href; s.onload = cb; document.head.appendChild(s);
  }
  var base = (window.FLY_BASE_URL || '') + '/assets/katex/';
  var l = document.createElement('link'); l.rel='stylesheet'; l.href = base + 'katex.min.css';
  document.head.appendChild(l);
  load(base + 'katex.min.js', function(){
    load(base + 'auto-render.min.js', function(){
      if (window.renderMathInElement) {
        renderMathInElement(body, {delimiters:[
          {left:'$$', right:'$$', display:true},
          {left:'$', right:'$', display:false}
        ], throwOnError:false});
      }
    });
  });
})();
FLYJS . '</script>';
});
