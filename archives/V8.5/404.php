<?php
/**
 * FlyLog V5.0 自定义 404 页面（.htaccess ErrorDocument 404 指向此处）
 * #208 后台可自定义标题与正文（page_404.title / page_404.body），留空用下方默认
 */
require __DIR__ . '/config.php';

http_response_code(404);
$p404Title = (string)fly_opt('page_404.title', '');
$p404Body  = (string)fly_opt('page_404.body', '');
if ($p404Title === '') { $p404Title = '页面走丢了'; }
if ($p404Body === '') {
    $p404Body = '抱歉，你访问的' . fly_term('post') . '不存在或已被移动。';
}
$meta = array('title' => $p404Title . ' - ' . fly_config('site.name', 'FlyLog'));
echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => array(
    array('name' => '首页', 'url' => fly_url('home')),
    array('name' => '404'),
)));
?>
<div class="wrap">
  <div class="post-full" style="text-align:center;padding:46px 28px">
    <h1 style="font-size:60px;margin-bottom:8px;color:var(--brand)">404</h1>
    <p class="summary"><?= $p404Body /* 后台自定义内容，允许简单 HTML */ ?></p>
    <p style="margin-top:22px">
      <a class="btn" href="<?= fly_e(fly_url('home')) ?>">返回首页</a>
      <a class="btn btn-gray" href="<?= fly_e(fly_url('archive')) ?>">浏览<?= fly_e(fly_term('archive')) ?></a>
    </p>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
