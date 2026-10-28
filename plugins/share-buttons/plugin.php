<?php
/**
 * Plugin Name: 分享按钮
 * Version: 1.0.0
 * Description: 一键复制链接 / 分享到微博、QQ、微信
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
    if (fly_plugin_opt('share-buttons', 'enable', '1') !== '1') { return; }
    echo '<script>' . <<<'FLYJS'
(function(){
  var box = document.createElement('div');
  box.className='fly-share';
  box.style.cssText='display:flex;gap:8px;align-items:center;margin:14px 0;font-size:13px';
  var url = encodeURIComponent(location.href);
  var title = encodeURIComponent(document.title);
  var items = [
    ['复制链接', 'javascript:void(0)', function(){
      if (navigator.clipboard) { navigator.clipboard.writeText(location.href); alert('链接已复制'); }
    }],
    ['微博', 'http://service.weibo.com/share/share.php?url=' + url + '&title=' + title, null],
    ['QQ空间', 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' + url + '&title=' + title, null]
  ];
  items.forEach(function(it){
    var a = document.createElement('a');
    a.textContent = it[0]; a.href = it[1];
    a.style.cssText='padding:4px 10px;border:1px solid var(--border);border-radius:999px;text-decoration:none';
    if (it[2]) { a.addEventListener('click', function(e){ e.preventDefault(); it[2](); }); }
    else { a.target='_blank'; a.rel='noopener'; }
    box.appendChild(a);
  });
  var body = document.querySelector('.post-body');
  if (body && body.parentNode) { body.parentNode.insertBefore(box, body.nextSibling); }
})();
FLYJS . '</script>';
});
