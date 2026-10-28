<?php
/**
 * Plugin Name: 跳出率统计
 * Version: 1.0.0
 * Description: 只浏览一页就离开的比例
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('bounce-rate', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  if (sessionStorage.getItem('fly_pages')) { return; }
  sessionStorage.setItem('fly_pages', '1');
  window.addEventListener('beforeunload', function(){
    if (sessionStorage.getItem('fly_pages') === '1' && navigator.sendBeacon) {
      navigator.sendBeacon((window.FLY_BASE_URL||'') + 'api/beacon.php?bounce=1');
    }
  });
  window.addEventListener('click', function(){ sessionStorage.setItem('fly_pages', '2'); }, {once:true});
})();
FLYJS . '</script>';
});
