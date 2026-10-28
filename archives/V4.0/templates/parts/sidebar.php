<?php
/** 侧边栏部件：自包含查询（最新文章 + 分类 + 搜索） */
$__latest = BasePost::all(array('limit' => 5));
$__cats = fly_db()->all(
    "SELECT category, COUNT(*) AS n FROM posts
     WHERE category <> '' AND is_draft = 0
       AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
     GROUP BY category ORDER BY n DESC, category ASC"
);
?>
<aside class="side-col">
  <?php fly_do_action('fly_before_sidebar'); ?>
  <div class="side-box">
    <h3>搜索</h3>
    <form class="side-search" method="get" action="<?= fly_e(fly_url('home')) ?>">
      <input name="q" value="<?= fly_e($q ?? '') ?>" placeholder="搜索文章…">
      <button type="submit">搜索</button>
    </form>
  </div>
  <div class="side-box">
    <h3>最新文章</h3>
    <ul class="side-list">
      <?php foreach ($__latest as $p): ?>
        <li><a href="<?= fly_e(fly_url('post', array('id' => $p['id']))) ?>"><?= fly_e($p['title']) ?></a></li>
      <?php endforeach; ?>
      <?php if (!$__latest): ?><li style="color:#9ca3af">暂无文章</li><?php endif; ?>
    </ul>
  </div>
  <div class="side-box">
    <h3>分类目录</h3>
    <ul class="side-list">
      <?php foreach ($__cats as $c): ?>
        <li><a href="<?= fly_e(fly_url('category', array('cat' => $c['category']))) ?>"><?= fly_e($c['category']) ?></a> <span style="color:#9ca3af">(<?= (int)$c['n'] ?>)</span></li>
      <?php endforeach; ?>
      <?php if (!$__cats): ?><li style="color:#9ca3af">暂无分类</li><?php endif; ?>
    </ul>
  </div>
  <?php fly_do_action('fly_after_sidebar'); ?>
</aside>
