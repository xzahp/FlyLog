<?php
/**
 * Plugin Name: 粘贴上传
 * Version: 1.0.0
 * Description: 从剪贴板粘贴图片自动上传并插入
 * Author: FlyLog
 * Category: 媒体
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('paste-upload', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var ta = document.querySelector('textarea[name="content"]');
  if (!ta) return;
  ta.addEventListener('paste', function(e){
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (var i=0;i<items.length;i++){
      if (items[i].type.indexOf('image') === 0) {
        var f = items[i].getAsFile();
        var fd = new FormData(); fd.append('file', f);
        fetch((window.FLY_BASE_URL||'') + 'api/upload.php', {method:'POST', body: fd, credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(d){ if (d && d.url) { ta.value += '\n![图片](' + d.url + ')\n'; } })
          .catch(function(){});
        e.preventDefault();
      }
    }
  });
})();
FLYJS . '</script>';
});
