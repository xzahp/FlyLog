<?php
/**
 * FlyLog V2.0 RSS 订阅（RSS 2.0，最新 10 篇已发布文章）
 */
require __DIR__ . '/config.php';

if (fly_static_serve('feed.xml')) {
    exit;
}

$db = fly_db();
$base = fly_base_url();
$posts = BasePost::all(array('limit' => 10));
header('Content-Type: application/rss+xml; charset=utf-8');

// XML 声明用 echo 输出，避免短标签主机把裸声明当 PHP 解析
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
<title><?= fly_e(fly_config('site.name', 'FlyLog')) ?></title>
<link><?= fly_e($base) ?></link>
<description><?= fly_e(fly_config('site.desc', '')) ?></description>
<language>zh-CN</language>
<lastBuildDate><?= date('r') ?></lastBuildDate>
<?php foreach ($posts as $it): ?>
<?php $link = fly_e($base . 'post/' . (int)$it['id'] . '.html'); ?>
<item>
<title><?= fly_e($it['title']) ?></title>
<link><?= $link ?></link>
<guid isPermaLink="true"><?= $link ?></guid>
<description><?= fly_e(($it['summary'] !== '' && $it['summary'] !== null) ? $it['summary'] : fly_excerpt($it['content'] ?? '', 200)) ?></description>
<pubDate><?= date('r', strtotime((string)$it['created_at'])) ?></pubDate>
</item>
<?php endforeach; ?>
</channel>
</rss>
