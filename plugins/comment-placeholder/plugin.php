<?php
/**
 * Plugin Name: 评论框提示语
 * Version: 1.0.0
 * Description: 自定义评论框 placeholder
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('comment-placeholder', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var ta = document.getElementById('content');
  if (!ta) return;
  var custom = window.FLY_COMMENT_PLACEHOLDER || '';
  if (custom) { ta.setAttribute('placeholder', custom); }
})();
FLYJS . '</script>';
});
