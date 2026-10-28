<?php
/**
 * Plugin Name: 停留时间统计
 * Version: 1.0.0
 * Description: 记录访客平均停留时长
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
    if (fly_plugin_opt('avg-stay-time', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var t0 = Date.now();
  window.addEventListener('beforeunload', function(){
    var sec = Math.round((Date.now() - t0) / 1000);
    if (sec >= 3 && navigator.sendBeacon) {
      navigator.sendBeacon((window.FLY_BASE_URL||'') + 'api/beacon.php?stay=' + sec);
    }
  });
})();
FLYJS . '</script>';
});
