<?php
/** 侧边栏部件：自包含查询（最新文章 + 分类树 + 标签云 + 搜索） */
$__latest = BasePost::all(array('limit' => 5));

// V7.0 分类树：一次 GROUP BY 拿各分类直属文章数，再按子树累加出「含子分类」的计数
$__raw = array();
try {
    foreach (fly_db()->all(
        "SELECT category_id, COUNT(*) AS n FROM posts
         WHERE deleted_at = 0 AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
         GROUP BY category_id"
    ) as $r) {
        $__raw[(int)$r['category_id']] = (int)$r['n'];
    }
} catch (Exception $e) {
    $__raw = array();
}
$__cats = array();
foreach (fly_cat_tree() as $c) {
    $n = 0;
    foreach (fly_cat_subtree_ids((int)$c['id']) as $sid) {
        $n += isset($__raw[$sid]) ? $__raw[$sid] : 0;
    }
    $c['n'] = $n;
    $__cats[] = $c;
}

// 兼容：分类表还是空（老库刚升级、迁移尚未跑完）时，回落旧的「字符串分类」统计
if (!$__cats) {
    try {
        $__cats = fly_db()->all(
            "SELECT category AS name, category AS slug, 0 AS id, 0 AS level, '' AS indent, COUNT(*) AS n
             FROM posts WHERE category <> '' AND deleted_at = 0 AND is_draft = 0
               AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
             GROUP BY category ORDER BY n DESC, category ASC"
        );
    } catch (Exception $e) {
        $__cats = array();
    }
}
$__tags = fly_tag_cloud(30);
?>
<aside class="side-col">
  <?php fly_do_action('fly_before_sidebar'); ?>
  <div class="side-box">
    <h3><?= fly_e(fly_term('search')) ?></h3>
    <form class="side-search" method="get" action="<?= fly_e(fly_url('home')) ?>" role="search" aria-label="站内搜索">
      <input name="q" value="<?= fly_e($q ?? '') ?>" placeholder="搜索<?= fly_e(fly_term('post')) ?>…" aria-label="搜索关键词">
      <button type="submit"><?= fly_e(fly_term('search')) ?></button>
    </form>
  </div>
  <div class="side-box">
    <h3>最新<?= fly_e(fly_term('post')) ?></h3>
    <ul class="side-list">
      <?php foreach ($__latest as $p): ?>
        <li><a href="<?= fly_e(fly_url('post', array('id' => $p['id']))) ?>"><?= fly_e($p['title']) ?></a></li>
      <?php endforeach; ?>
      <?php if (!$__latest): ?><li style="color:#9ca3af">暂无<?= fly_e(fly_term('post')) ?></li><?php endif; ?>
    </ul>
  </div>

  <?php /* V7.0 第二批：热门文章（阅读 + 点赞综合排序） */ ?>
  <?php $__hot = fly_post_popular(5); ?>
  <?php if ($__hot): ?>
  <div class="side-box">
    <h3>🔥 热门<?= fly_e(fly_term('post')) ?></h3>
    <ul class="side-list">
      <?php foreach ($__hot as $h): ?>
        <li><a href="<?= fly_e(fly_url('post', array('id' => $h['id']))) ?>"><?= fly_e($h['title']) ?></a> <span style="color:#9ca3af;font-size:12px">👁<?= (int)$h['views'] ?> 👍<?= (int)$h['likes'] ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <div class="side-box">
    <h3><?= fly_e(fly_term('category')) ?>目录</h3>
    <ul class="side-list">
      <?php foreach ($__cats as $c): ?>
        <?php
        // 有 id 说明是 V7.0 分类树条目；否则是老字符串分类，走老链接
        $href = ((int)$c['id'] > 0)
            ? fly_cat_url($c)
            : fly_url('category', array('cat' => $c['name']));
        ?>
        <li><a href="<?= fly_e($href) ?>"><?= fly_e($c['indent'] . $c['name']) ?></a> <span style="color:#9ca3af">(<?= (int)$c['n'] ?>)</span></li>
      <?php endforeach; ?>
      <?php if (!$__cats): ?><li style="color:#9ca3af">暂无<?= fly_e(fly_term('category')) ?></li><?php endif; ?>
    </ul>
  </div>
  <?php if ($__tags): ?>
  <div class="side-box">
    <h3>标签云</h3>
    <div class="tag-cloud">
      <?php foreach ($__tags as $t): ?>
        <a class="tag-lv<?= (int)$t['level'] ?>" href="<?= fly_e($t['url']) ?>"><?= fly_e($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php fly_do_action('fly_after_sidebar'); ?>
</aside>
