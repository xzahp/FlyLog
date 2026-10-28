<?php
/**
 * FlyLog RSS 订阅（V1.1 新增）
 * 输出 RSS 2.0：最新 10 篇文章（标题 / 链接 / 摘要 / 发布时间）
 * 伪静态地址 /feed 由 .htaccess 转换；阅读器订阅即自动获取更新
 */
require_once __DIR__ . '/config.php';

// 数据库连接就绪后再声明 RSS 内容类型（DB 故障时友好提示页不会被错标为 RSS）
header('Content-Type: application/rss+xml; charset=utf-8');

// 站点基础地址（用于拼绝对链接）
$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
    $scheme = 'https';
}
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$base = $scheme . '://' . $host . '/';

$res = $conn->query("SELECT `id`, `title`, `summary`, `content`, `created_at` FROM `posts` ORDER BY `id` DESC LIMIT 10");
$items = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
}

$now_rfc = date('r');
// 注意：XML 声明必须用 echo 输出——主机若开启 short_open_tag，裸 XML 声明行会被误判为 PHP 短标签而 500；
// 且注释里切勿出现 PHP 结束标记字样，否则会提前闭合 PHP 模式
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
<title>FlyLog</title>
<link><?= fly_e($base) ?></link>
<description>FlyLog - 轻量博客最新文章</description>
<lastBuildDate><?= $now_rfc ?></lastBuildDate>
<language>zh-CN</language>
<?php foreach ($items as $it): ?>
<?php
$desc = ($it['summary'] !== '') ? $it['summary'] : fly_excerpt($it['content'] ?? '', 200);
?>
<item>
<title><?= fly_e($it['title']) ?></title>
<link><?= fly_e($base . 'post/' . (int)$it['id'] . '.html') ?></link>
<guid isPermaLink="true"><?= fly_e($base . 'post/' . (int)$it['id'] . '.html') ?></guid>
<description><?= fly_e($desc) ?></description>
<pubDate><?= date('r', strtotime($it['created_at'])) ?></pubDate>
</item>
<?php endforeach; ?>
</channel>
</rss>
