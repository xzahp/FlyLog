<?php
/**
 * Plugin Name: 访客签到
 * Version: 1.0.0
 * Description: 每日签到得积分（本地记录连续签到天数）
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
    if (fly_plugin_opt('visitor-checkin', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var KEY='fly_checkin';
  var today = new Date().toISOString().slice(0,10);
  var data = {};
  try { data = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch(e){}
  var box = document.createElement('div');
  box.className = 'fly-checkin';
  box.style.cssText='padding:10px 12px;border:1px solid var(--border);border-radius:10px;margin:10px 0;font-size:13px';
  if (data.date === today) {
    box.textContent = '✅ 今日已签到（连续 ' + (data.streak||1) + ' 天）';
  } else {
    var btn = document.createElement('button');
    btn.textContent = '📅 签到'; btn.className='btn btn-sm';
    btn.addEventListener('click', function(){
      var streak = (data.date && (new Date(today) - new Date(data.date)) === 86400000) ? (data.streak||0)+1 : 1;
      data = {date: today, streak: streak};
      try { localStorage.setItem(KEY, JSON.stringify(data)); } catch(e){}
      box.textContent = '✅ 签到成功！连续 ' + streak + ' 天';
    });
    box.appendChild(btn);
  }
  var side = document.querySelector('.side-col') || document.querySelector('.side-box');
  if (side) { side.parentNode.insertBefore(box, side); }
})();
FLYJS . '</script>';
});
