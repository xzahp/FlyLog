<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= fly_tpl_seo($meta ?? array()) ?>
<?php /* #221 Favicon 自定义：后台填了 URL 就用，否则默认 🚁 */ ?>
<?php $fly_favicon = (string)fly_opt('site.favicon', ''); ?>
<?php if ($fly_favicon !== ''): ?>
<link rel="icon" href="<?= fly_e($fly_favicon) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<?php endif; ?>
<?php /* #241 深色模式：渲染前同步决定主题，防闪白（FOUC）；三态 auto/light/dark 存 localStorage */ ?>
<script>
(function(){try{
  var t = localStorage.getItem('fly_theme') || 'auto';
  var mq = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (t === 'dark' || (t === 'auto' && mq)) { document.documentElement.classList.add('dark'); }
  else if (t === 'light') { document.documentElement.classList.add('light'); }
}catch(e){}})();
</script>
<?php /* #66 DNS 预解析：加速同域及外部资源（CDN/图床可在后台配置后追加） */ ?>
<link rel="dns-prefetch" href="//<?= fly_e(fly_site_host()) ?>">
<?php /* #187 纯文字模式（?text=1）：不加载样式/脚本/图片，极速省流量 */ ?>
<?php $fly_text = isset($_GET['text']) && $_GET['text'] !== '0' && $_GET['text'] !== ''; ?>
<?php if ($fly_text): ?>
<style>img,video,iframe,.side-col{display:none!important}body{background:#fff!important;color:#000!important;font-family:serif}.wrap,.layout{max-width:none!important;padding:12px!important;display:block!important}</style>
<?php else: ?>
<?php /* #53/#55 优先加载压缩版（cli asset:minify 生成），无则回退原版；版本号=内容MD5 */ ?>
<?php $fly_css = is_file(FLY_ROOT . '/assets/app.min.css') ? 'assets/app.min.css' : 'assets/app.css'; ?>
<link rel="stylesheet" href="<?= fly_e(fly_asset($fly_css)) ?>">
<?php /* #130 代码高亮：highlight.js（CDN，加载失败自动降级为纯文本，不影响页面） */ ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" defer></script>
<?php /* #67 异步加载非核心 JS（存在 app.js 才输出，defer 不阻塞渲染） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/app.js')): ?>
<script src="<?= fly_e(fly_asset('assets/app.js')) ?>" defer></script>
<?php endif; ?>
<?php endif; ?>
<?php /* #72 表单防重复提交：提交即置灰，拦截连点 */ ?>
<script>
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f.getAttribute('data-submitted') === '1') { e.preventDefault(); return; }
  f.setAttribute('data-submitted', '1');
  var btns = f.querySelectorAll('button[type=submit],input[type=submit],.btn');
  for (var i = 0; i < btns.length; i++) { btns[i].disabled = true; btns[i].style.opacity = '.6'; }
});
</script>
<?php fly_do_action('fly_head'); ?>
</head>
<body>
