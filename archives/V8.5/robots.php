<?php
/**
 * FlyLog V2.0 robots.txt（伪静态 /robots.txt）
 */
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$base = fly_base_url();
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /install.php\n";
echo "Disallow: /api/\n";
echo "Allow: /\n\n";
echo "Sitemap: " . $base . "sitemap.xml\n";
