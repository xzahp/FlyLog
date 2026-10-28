<?php
/**
 * FlyLog V2.0 Sitemap（XML，伪静态 /sitemap.xml）
 */
require __DIR__ . '/config.php';

$db = fly_db();
$base = fly_base_url();
$posts = BasePost::all();
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= fly_e($base) ?></loc><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= fly_e(fly_url('archive')) ?></loc><changefreq>daily</changefreq><priority>0.6</priority></url>
  <?php foreach ($posts as $p): ?>
  <url>
    <loc><?= fly_e($base . 'post/' . (int)$p['id'] . '.html') ?></loc>
    <lastmod><?= fly_e(date('Y-m-d', strtotime((string)$p['created_at']))) ?></lastmod>
    <changefreq>weekly</changefreq><priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
