<?php
/**
 * FlyLog V2.0 文章归档页（按年月分组 + 全站统计）
 */
require __DIR__ . '/config.php';

if (fly_static_serve('archive.html')) {
    exit;
}

$db = fly_db();
$posts = BasePost::all(); // 已发布，置顶优先+ID 倒序
$groups = array();
foreach ($posts as $p) {
    $ym = substr((string)$p['created_at'], 0, 7); // YYYY-MM
    $groups[$ym][] = $p;
}
krsort($groups); // 最近月份在前

$postCount = count($posts);
$commentCount = (int)$db->value("SELECT COUNT(*) FROM comments WHERE status = 1");
$catCount = (int)$db->value("SELECT COUNT(DISTINCT category) FROM posts WHERE category <> '' AND is_draft = 0");

$meta = array('title' => '文章归档 - ' . fly_config('site.name', 'FlyLog'), 'description' => '全部文章按时间归档');
$bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '文章归档'));

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc));
?>
<div class="wrap">
  <div class="arch-stats">
    <div class="stat"><b><?= $postCount ?></b>篇文章</div>
    <div class="stat"><b><?= $commentCount ?></b>条留言</div>
    <div class="stat"><b><?= $catCount ?></b>个分类</div>
  </div>
  <?php if (!$groups): ?>
    <p class="empty">还没有文章。</p>
  <?php else: foreach ($groups as $ym => $list): ?>
    <div class="arch-group">
      <h3><?= fly_e($ym) ?></h3>
      <ul class="side-list">
        <?php foreach ($list as $p): ?>
          <li>
            <a href="<?= fly_e(fly_url('post', array('id' => $p['id']))) ?>"><?= fly_e($p['title']) ?></a>
            <span style="color:#9ca3af;font-size:12.5px"> · <?= fly_e(substr((string)$p['created_at'], 0, 10)) ?> · 阅读 <?= (int)$p['views'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; endif; ?>
  <p style="margin-top:20px"><a href="<?= fly_e(fly_url('home')) ?>">&laquo; 返回首页</a></p>
</div>
<?php echo fly_render('parts/footer'); ?>
