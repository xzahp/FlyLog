<?php
/**
 * FlyLog 文章归档页（V1.1 新增）
 * 功能：按年月分组列出全部文章 + 全站统计（文章数 / 留言数 / 分类数）
 * 伪静态地址 /archive 由 .htaccess 转换
 */
require_once __DIR__ . '/config.php';

$res = $conn->query("SELECT `id`, `title`, `category`, `created_at` FROM `posts` ORDER BY `id` DESC");
$posts = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $posts[] = $r;
    }
}

// 按「年-月」分组
$groups = array();
foreach ($posts as $p) {
    $ym = substr((string)$p['created_at'], 0, 7); // 2026-10
    if (!isset($groups[$ym])) {
        $groups[$ym] = array();
    }
    $groups[$ym][] = $p;
}

// 统计
function fly_stat($conn, $sql)
{
    $res = $conn->query($sql);
    return ($res && $res->num_rows === 1) ? (int)$res->fetch_assoc()['c'] : 0;
}
$stat_posts = count($posts);
$stat_comments = fly_stat($conn, "SELECT COUNT(*) AS c FROM `comments` WHERE `status` = 1");
$stat_cats = fly_stat($conn, "SELECT COUNT(DISTINCT `category`) AS c FROM `posts` WHERE `category` <> ''");

// 分组 HTML
$groups_html = '';
if ($groups === array()) {
    $groups_html = '<p class="empty">还没有文章，去后台写第一篇吧。</p>';
} else {
    foreach ($groups as $ym => $items) {
        $groups_html .= '<div class="arc-group"><h3>' . fly_e($ym) . ' <span class="arc-count">（' . count($items) . ' 篇）</span></h3><ul class="arc-list">';
        foreach ($items as $p) {
            $cat_line = ($p['category'] !== '') ? ' <span class="arc-cat">' . fly_e($p['category']) . '</span>' : '';
            $groups_html .= '<li><span class="arc-date">' . fly_e(substr((string)$p['created_at'], 0, 10)) . '</span>'
                . '<a href="post/' . (int)$p['id'] . '.html">' . fly_e($p['title']) . '</a>' . $cat_line . '</li>';
        }
        $groups_html .= '</ul></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文章归档 - FlyLog</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:920px;margin:0 auto;padding:24px 20px 40px}
.site-head{border-bottom:2px solid #eee;padding:18px 0;margin-bottom:26px;display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px}
.site-title{font-size:26px;font-weight:700;color:#222;letter-spacing:.5px}
.site-title:hover{color:#06c}
.site-sub{color:#999;font-size:13px}
.site-sub a{color:#999}
.site-sub a:hover{color:#06c}
.layout{display:flex;gap:36px;align-items:flex-start}
.main-col{flex:1;min-width:0}
.side-col{width:250px;flex-shrink:0}
.side-box{margin-bottom:26px}
.side-box h3{font-size:15px;color:#222;padding-bottom:8px;border-bottom:2px solid #f0f0f0;margin-bottom:8px}
.side-list{list-style:none}
.side-list li{padding:5px 0;font-size:13.5px;border-bottom:1px dashed #f3f3f3}
.side-list li a{color:#444}
.side-list li a:hover{color:#06c}
.side-count{color:#bbb;font-size:12px}
.side-empty{color:#bbb}
.side-search{display:flex;gap:6px}
.side-search input{flex:1;min-width:0;padding:7px 9px;border:1px solid #ddd;border-radius:4px;font-size:13.5px;font-family:inherit;background:#fff;color:#333}
.side-search input:focus{outline:none;border-color:#06c}
.side-search button{padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;font-size:13px}
.side-search button:hover{border-color:#06c}
.page-title{font-size:22px;color:#222;margin-bottom:6px}
.stat-row{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 24px}
.stat-chip{border:1px solid #eee;border-radius:20px;padding:5px 14px;font-size:13px;color:#666;background:#fafafa}
.stat-chip b{color:#222}
.arc-group{margin-bottom:26px}
.arc-group h3{font-size:16px;color:#222;border-bottom:2px solid #f0f0f0;padding-bottom:8px;margin-bottom:6px}
.arc-count{color:#999;font-size:13px;font-weight:400}
.arc-list{list-style:none}
.arc-list li{padding:7px 0;border-bottom:1px dashed #f3f3f3;font-size:14px;display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}
.arc-date{color:#bbb;font-size:12.5px;font-family:Consolas,Menlo,monospace;white-space:nowrap}
.arc-cat{margin-left:6px;font-size:12px;color:#888;background:#f3f6fa;border-radius:10px;padding:1px 8px}
.empty{color:#888;padding:30px 0;text-align:center}
.site-foot{margin-top:44px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.layout{flex-direction:column;gap:26px}.side-col{width:100%}.wrap{padding:16px 14px 30px}.site-title{font-size:21px}}
</style>
</head>
<body>
<div class="wrap">
  <header class="site-head">
    <a class="site-title" href="index.php">FlyLog</a>
    <span class="site-sub"><a href="index.php">首页</a> · <a href="archive.php">归档</a> · <a href="admin/login.php">后台</a></span>
  </header>
  <div class="layout">
    <div class="main-col">
      <h1 class="page-title">文章归档</h1>
      <div class="stat-row">
        <span class="stat-chip">共 <b><?= $stat_posts ?></b> 篇文章</span>
        <span class="stat-chip"><b><?= $stat_comments ?></b> 条留言</span>
        <span class="stat-chip"><b><?= $stat_cats ?></b> 个分类</span>
      </div>
      <?= $groups_html ?>
    </div>
    <?php include __DIR__ . '/sidebar.php'; ?>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
