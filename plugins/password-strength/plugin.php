<?php
/**
 * Plugin Name: 密码强度提示
 * Version: 1.0.0
 * Description: 修改密码时显示红/黄/绿强度条
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('password-strength', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var pwd = document.querySelector('input[name="new_password"], input[name="password"]');
  if (!pwd) return;
  var bar = document.createElement('div');
  bar.style.cssText='height:6px;border-radius:3px;margin-top:6px;background:#e5e7eb;overflow:hidden';
  var fill = document.createElement('div');
  fill.style.cssText='height:100%;width:0;transition:width .2s,background .2s';
  bar.appendChild(fill);
  pwd.parentNode.appendChild(bar);
  pwd.addEventListener('input', function(){
    var v = pwd.value, score = 0;
    if (v.length >= 6) score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var pct = [0, 20, 40, 60, 80, 100][Math.min(score,5)];
    var color = score <= 2 ? '#ef4444' : (score === 3 ? '#f59e0b' : '#10b981');
    fill.style.width = pct + '%'; fill.style.background = color;
  });
})();
FLYJS . '</script>';
});
