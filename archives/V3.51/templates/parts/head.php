<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= fly_tpl_seo($meta ?? array()) ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<?php /* #66 DNS 预解析：加速同域及外部资源（CDN/图床可在后台配置后追加） */ ?>
<link rel="dns-prefetch" href="//<?= fly_e(fly_site_host()) ?>">
<?php /* #53/#55 优先加载压缩版（cli asset:minify 生成），无则回退原版；版本号=内容MD5 */ ?>
<?php $fly_css = is_file(FLY_ROOT . '/assets/app.min.css') ? 'assets/app.min.css' : 'assets/app.css'; ?>
<link rel="stylesheet" href="<?= fly_e(fly_asset($fly_css)) ?>">
<?php /* #67 异步加载非核心 JS（存在 app.js 才输出，defer 不阻塞渲染） */ ?>
<?php if (is_file(FLY_ROOT . '/assets/app.js')): ?>
<script src="<?= fly_e(fly_asset('assets/app.js')) ?>" defer></script>
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
