<?php
/**
 * Plugin Name: 评论盖楼折叠
 * Version: 1.0.0
 * Description: 多级评论自动折叠，点击展开
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
    if (fly_plugin_opt('comment-collapse', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var items = document.querySelectorAll('.comment-item .children');
  for (var i=0;i<items.length;i++){
    (function(box){
      if (box.children.length <= 2) return;
      var btn = document.createElement('button');
      btn.className='fly-fold-btn'; btn.type='button';
      btn.textContent='展开 ' + box.children.length + ' 条回复';
      btn.style.cssText='background:none;border:none;color:var(--brand);cursor:pointer;font-size:13px;padding:4px 0';
      box.style.display='none';
      box.parentNode.insertBefore(btn, box);
      btn.addEventListener('click', function(){
        var on = box.style.display !== 'none';
        box.style.display = on ? 'none' : '';
        btn.textContent = on ? ('展开 ' + box.children.length + ' 条回复') : '收起回复';
      });
    })(items[i]);
  }
})();
FLYJS . '</script>';
});
