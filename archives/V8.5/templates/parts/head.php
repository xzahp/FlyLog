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
<?php /* #241/V8.0 主题：渲染前同步决定 data-theme 防闪白（FOUC）；默认主题取自后台设置，访客选择存 localStorage */ ?>
<script>
(function(){try{
  var def = <?= json_encode((string)fly_opt('theme_default', 'auto')) ?>;
  var t = localStorage.getItem('fly_theme') || def || 'auto';
  if (['light','dark','aurora','sunset','auto'].indexOf(t) < 0) { t = 'auto'; }
  document.documentElement.setAttribute('data-theme', t);
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
<?php /* #V8.0 主题切换器样式（独立文件，独立版本号，不进压缩链路） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/theme.css')): ?>
<link rel="stylesheet" href="<?= fly_e(fly_asset('assets/theme.css')) ?>">
<?php endif; ?>
<?php /* #V8.5 阅读体验样式（独立文件，独立版本号，不进压缩链路） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/read.css')): ?>
<link rel="stylesheet" href="<?= fly_e(fly_asset('assets/read.css')) ?>">
<?php endif; ?>
<?php /* #130/V8.5 代码高亮：highlight.js 本地自托管（加载失败自动降级为纯文本，不影响页面） */ ?>
<link id="fly-hl-style" rel="stylesheet" href="<?= fly_e(fly_asset('assets/highlight-github.min.css')) ?>">
<script src="<?= fly_e(fly_asset('assets/highlight.min.js')) ?>" defer></script>
<?php /* #67 异步加载非核心 JS（存在 app.js 才输出，defer 不阻塞渲染） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/app.js')): ?>
<script src="<?= fly_e(fly_asset('assets/app.js')) ?>" defer></script>
<?php endif; ?>
<?php /* #V8.0 主题切换逻辑（defer，与切换器同域加载） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/theme.js')): ?>
<script src="<?= fly_e(fly_asset('assets/theme.js')) ?>" defer></script>
<?php endif; ?>
<?php /* #V8.5 阅读体验增强（defer，始终加载以便客户端功能可用） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/read.js')): ?>
<script src="<?= fly_e(fly_asset('assets/read.js')) ?>" defer></script>
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
<?php /* #V8.5 暴露站点名给前端（复制防抄袭提示用） */ ?>
<script>window.FLY_SITE_NAME = <?= json_encode((string)fly_site_name()) ?>;</script>
<?php fly_do_action('fly_head'); ?>
</head>
<body>
