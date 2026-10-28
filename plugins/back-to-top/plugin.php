<?php
/**
 * Plugin Name: 回到顶部按钮
 * Version: 1.0.0
 * Description: 滚动后右下角出现回到顶部按钮
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('back-to-top', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var btn = document.createElement('button');
  btn.textContent = '↑'; btn.className='fly-backtop';
  btn.style.cssText='position:fixed;right:18px;bottom:24px;width:42px;height:42px;border-radius:50%;'+
    'border:1px solid var(--border);background:var(--surface);cursor:pointer;display:none;z-index:60;font-size:18px';
  btn.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
  document.body.appendChild(btn);
  window.addEventListener('scroll', function(){
    btn.style.display = window.pageYOffset > 400 ? 'block' : 'none';
  });
})();
FLYJS . '</script>';
});
