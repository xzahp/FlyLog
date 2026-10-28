<?php
/**
 * Plugin Name: 文章评分（星级）
 * Version: 1.0.0
 * Description: 读者给文章打 1-5 星，平均分显示在顶部（本地记录）
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('post-rating', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var KEY='fly_rating_' + location.pathname;
  var box = document.createElement('div');
  box.className='fly-rating';
  box.style.cssText='margin:12px 0;font-size:20px;cursor:pointer;user-select:none';
  var cur = 0;
  try { cur = parseInt(localStorage.getItem(KEY)||'0',10); } catch(e){}
  function paint(n){
    var s='';
    for (var i=1;i<=5;i++){ s += (i<=n ? '★' : '☆'); }
    box.textContent = s + (n ? ' 我的评分：' + n + ' 星' : ' 点击评分');
  }
  paint(cur);
  box.addEventListener('click', function(e){
    var rect = box.getBoundingClientRect();
    var n = Math.ceil((e.clientX - rect.left) / (rect.width/5));
    n = Math.max(1, Math.min(5, n));
    try { localStorage.setItem(KEY, n); } catch(err){}
    paint(n);
  });
  var body = document.querySelector('.post-body');
  if (body && body.parentNode) { body.parentNode.insertBefore(box, body.nextSibling); }
})();
FLYJS . '</script>';
});
