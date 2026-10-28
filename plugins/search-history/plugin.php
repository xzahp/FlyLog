<?php
/**
 * Plugin Name: 搜索历史记录
 * Version: 1.0.0
 * Description: localStorage 记录最近 5 次搜索词
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
    if (fly_plugin_opt('search-history', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var input = document.querySelector('input[name="q"]');
  if (!input) return;
  var KEY = 'fly_search_history';
  function hist(){ try { return JSON.parse(localStorage.getItem(KEY)||'[]'); } catch(e){ return []; } }
  var box = document.createElement('div');
  box.style.cssText='font-size:12.5px;color:var(--text-2);margin-top:6px';
  function paint(){
    var h = hist();
    if (!h.length) { box.textContent=''; return; }
    box.innerHTML = '最近搜索：' + h.map(function(w,i){
      return '<a href="?q=' + encodeURIComponent(w) + '" style="margin-right:8px">' + w + '</a>';
    }).join('');
  }
  paint();
  input.parentNode.appendChild(box);
  input.form && input.form.addEventListener('submit', function(){
    var v = input.value.trim(); if (!v) return;
    var h = hist(); h.unshift(v); h = h.slice(0,5);
    try { localStorage.setItem(KEY, JSON.stringify(h)); } catch(e){}
  });
})();
FLYJS . '</script>';
});
