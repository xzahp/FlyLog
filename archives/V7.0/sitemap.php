<?php
/**
 * FlyLog V4.5 Sitemap（XML，伪静态 /sitemap.xml）
 * #151 增强：URL 走 fly_url 规则（自定义伪静态自动适配）、补分类页、
 *   lastmod 用最新文章时间、全量上限 5000 条保护。
 */
require __DIR__ . '/config.php';

$db = fly_db();
$base = rtrim(fly_base_url(), '/');
/** V7.0：sitemap 规范要求 loc 必须是绝对地址，这里统一把 fly_url 的相对路径拼成绝对 */
$abs = function ($u) use ($base) {
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    return $base . '/' . ltrim($u, '/');
};
$posts = BasePost::all(array('limit' => 5000));
// V7.0：分类页改从分类树出（老的字符串分类作为兜底，避免升级期丢入口）
$cats = fly_cat_tree();
if (!$cats) {
    $cats = $db->all(
        "SELECT DISTINCT category AS name FROM posts
         WHERE category <> '' AND deleted_at = 0 AND is_draft = 0
           AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
         ORDER BY category ASC LIMIT 1000"
    );
}
$tags = fly_tag_all(200);
$pages = fly_page_all(true);
$lastMod = '';
if ($posts) {
    $lastMod = date('Y-m-d', strtotime((string)$posts[0]['created_at']));
}
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= fly_e($base . '/') ?></loc><?= $lastMod !== '' ? '<lastmod>' . fly_e($lastMod) . '</lastmod>' : '' ?><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= fly_e($abs(fly_url('archive'))) ?></loc><?= $lastMod !== '' ? '<lastmod>' . fly_e($lastMod) . '</lastmod>' : '' ?><changefreq>daily</changefreq><priority>0.6</priority></url>
  <?php foreach ($cats as $c): ?>
  <?php $loc = isset($c['id']) && (int)$c['id'] > 0 ? $abs(fly_cat_url($c)) : $abs(fly_url('category', array('cat' => $c['name']))); ?>
  <url><loc><?= fly_e($loc) ?></loc><changefreq>weekly</changefreq><priority>0.5</priority></url>
  <?php endforeach; ?>
  <?php foreach ($tags as $t): ?>
  <url><loc><?= fly_e($abs(fly_url('tag', array('tag' => $t['slug'])))) ?></loc><changefreq>weekly</changefreq><priority>0.4</priority></url>
  <?php endforeach; ?>
  <?php foreach ($pages as $pg): ?>
  <url><loc><?= fly_e($abs(fly_url('single', array('slug' => $pg['slug'])))) ?></loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
  <?php endforeach; ?>
  <?php foreach ($posts as $p): ?>
  <url>
    <loc><?= fly_e($abs(fly_url('post', array('id' => (int)$p['id'])))) ?></loc>
    <lastmod><?= fly_e(date('Y-m-d', strtotime((string)$p['created_at']))) ?></lastmod>
    <changefreq>weekly</changefreq><priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
