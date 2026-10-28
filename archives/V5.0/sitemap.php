<?php
/**
 * FlyLog V4.5 Sitemap（XML，伪静态 /sitemap.xml）
 * #151 增强：URL 走 fly_url 规则（自定义伪静态自动适配）、补分类页、
 *   lastmod 用最新文章时间、全量上限 5000 条保护。
 */
require __DIR__ . '/config.php';

$db = fly_db();
$base = fly_base_url();
$posts = BasePost::all(array('limit' => 5000));
$cats = $db->all(
    "SELECT DISTINCT category FROM posts
     WHERE category <> '' AND is_draft = 0
       AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
     ORDER BY category ASC LIMIT 1000"
);
$lastMod = '';
if ($posts) {
    $lastMod = date('Y-m-d', strtotime((string)$posts[0]['created_at']));
}
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= fly_e($base) ?></loc><?= $lastMod !== '' ? '<lastmod>' . fly_e($lastMod) . '</lastmod>' : '' ?><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= fly_e(fly_url('archive')) ?></loc><?= $lastMod !== '' ? '<lastmod>' . fly_e($lastMod) . '</lastmod>' : '' ?><changefreq>daily</changefreq><priority>0.6</priority></url>
  <?php foreach ($cats as $c): ?>
  <url><loc><?= fly_e(fly_url('category', array('cat' => $c['category']))) ?></loc><changefreq>weekly</changefreq><priority>0.5</priority></url>
  <?php endforeach; ?>
  <?php foreach ($posts as $p): ?>
  <url>
    <loc><?= fly_e(fly_url('post', array('id' => (int)$p['id']))) ?></loc>
    <lastmod><?= fly_e(date('Y-m-d', strtotime((string)$p['created_at']))) ?></lastmod>
    <changefreq>weekly</changefreq><priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
