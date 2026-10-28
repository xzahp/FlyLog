<?php
/**
 * Plugin Name: 拖拽上传
 * Version: 1.0.0
 * Description: 编辑器区域支持拖拽图片直接上传
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
    if (fly_plugin_opt('drag-upload', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var ta = document.querySelector('textarea[name="content"]');
  if (!ta) return;
  ta.addEventListener('dragover', function(e){ e.preventDefault(); ta.style.outline='2px dashed var(--brand)'; });
  ta.addEventListener('dragleave', function(){ ta.style.outline=''; });
  ta.addEventListener('drop', function(e){
    e.preventDefault(); ta.style.outline='';
    var files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;
    for (var i=0;i<files.length;i++){
      var fd = new FormData(); fd.append('file', files[i]);
      fetch((window.FLY_BASE_URL||'') + 'api/upload.php', {method:'POST', body: fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d && d.url) { ta.value += '\n![图片](' + d.url + ')\n'; }
        }).catch(function(){});
    }
  });
})();
FLYJS . '</script>';
});
