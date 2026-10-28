<?php
/**
 * 主题页头（header.php）
 * 主题自定义外壳：站点名 + 导航 + 副标题横幅。
 * 用核心函数 fly_url()/fly_config()/fly_e()，与默认模板解耦但能力一致。
 */
$__siteName = fly_config('site.name', 'FlyLog');
$__desc     = fly_config('site.desc', '');
?>
<header class="th-head">
  <div class="th-head-inner">
    <a class="th-logo" href="<?= fly_e(fly_url('home')) ?>">
      <?= fly_e($__siteName) ?>
    </a>
    <nav class="th-nav">
      <a href="<?= fly_e(fly_url('home')) ?>">首页</a>
      <a href="<?= fly_e(fly_base_url()) ?>archive.php">归档</a>
      <a href="<?= fly_e(fly_base_url()) ?>feed.php">订阅</a>
      <?php foreach (fly_cat_all() as $__c): ?>
        <a href="<?= fly_e(fly_cat_url($__c)) ?>"><?= fly_e($__c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= fly_e(fly_base_url()) ?>admin/">后台</a>
    </nav>
  </div>
  <?php if ($__desc !== ''): ?>
    <p class="th-desc"><?= fly_e($__desc) ?></p>
  <?php endif; ?>
</header>
<div class="th-wrap">
