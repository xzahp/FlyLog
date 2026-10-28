<?php
/**
 * FlyLog V4.0 自定义 404 页面（.htaccess ErrorDocument 404 指向此处）
 */
require __DIR__ . '/config.php';

http_response_code(404);
$meta = array('title' => '页面未找到 - ' . fly_config('site.name', 'FlyLog'));
echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => array(
    array('name' => '首页', 'url' => fly_url('home')),
    array('name' => '404'),
)));
?>
<div class="wrap">
  <div class="post-full" style="text-align:center;padding:46px 28px">
    <h1 style="font-size:60px;margin-bottom:8px;color:var(--brand)">404</h1>
    <p class="summary">抱歉，你访问的页面不存在或已被移动。</p>
    <p style="margin-top:22px">
      <a class="btn" href="<?= fly_e(fly_url('home')) ?>">返回首页</a>
      <a class="btn btn-gray" href="<?= fly_e(fly_url('archive')) ?>">浏览归档</a>
    </p>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
