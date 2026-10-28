<?php
/**
 * FlyLog 前台首页
 * 分页展示文章列表（每页 10 篇：标题、时间、摘要），点击进入详情
 * 附带支持分类过滤：index.php?cat=分类名
 */
require_once __DIR__ . '/config.php';

// ---------- 数据 ----------
$cat = isset($_GET['cat']) ? mysqli_real_escape_string($conn, trim($_GET['cat'])) : '';
$where = ($cat !== '') ? "WHERE `category` = '$cat'" : '';

$limit = 10; // 每页 10 篇
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

$tres = $conn->query("SELECT COUNT(*) AS c FROM `posts` $where");
if (!$tres) {
    die('数据库查询失败：' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8')
        . '<br>如果是首次使用，请先访问 <a href="install.php">install.php</a> 完成安装。');
}
$total = (int)$tres->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

$res = $conn->query("SELECT `id`, `title`, `summary`, `category`, `created_at` FROM `posts` $where ORDER BY `id` DESC LIMIT $limit OFFSET $offset");
if (!$res) {
    die('数据库查询失败：' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8')
        . '<br>如果是首次使用，请先访问 <a href="install.php">install.php</a> 完成安装。');
}

// ---------- 文章列表 HTML ----------
$items = '';
while ($row = $res->fetch_assoc()) {
    $summary = ($row['summary'] !== '') ? $row['summary'] : fly_excerpt($row['content'], 120);
    $cat_link = ($row['category'] !== '')
        ? ' · <a href="index.php?cat=' . urlencode($row['category']) . '">' . fly_e($row['category']) . '</a>'
        : '';
    $items .= '<article class="post-item">'
        . '<h2><a href="post/' . (int)$row['id'] . '.html">' . fly_e($row['title']) . '</a></h2>'
        . '<p class="meta">' . fly_e($row['created_at']) . $cat_link . '</p>'
        . '<p class="summary">' . fly_e($summary) . '</p>'
        . '</article>';
}
if ($items === '') {
    $items = '<p class="empty">这里还没有文章。'
        . ($cat !== '' ? '<br><a href="index.php">查看全部文章</a>' : '')
        . '</p>';
}

// ---------- 分页 HTML ----------
$pager = '';
if ($total_pages > 1) {
    $qs = ($cat !== '') ? 'cat=' . urlencode($cat) . '&' : '';
    $pager = '<nav class="pager">';
    $pager .= ($page > 1)
        ? '<a href="index.php?' . $qs . 'page=' . ($page - 1) . '">&laquo; 上一页</a>'
        : '<span class="dis">&laquo; 上一页</span>';
    for ($i = 1; $i <= $total_pages; $i++) {
        $pager .= ($i == $page)
            ? '<span class="cur">' . $i . '</span>'
            : '<a href="index.php?' . $qs . 'page=' . $i . '">' . $i . '</a>';
    }
    $pager .= ($page < $total_pages)
        ? '<a href="index.php?' . $qs . 'page=' . ($page + 1) . '">下一页 &raquo;</a>'
        : '<span class="dis">下一页 &raquo;</span>';
    $pager .= '</nav>';
}

$page_title = ($cat !== '') ? ('分类：' . fly_e($cat) . ' - FlyLog') : 'FlyLog - 轻量博客';
$head_note = ($cat !== '') ? ('当前分类：' . fly_e($cat)) : '致敬 Z-Blog 的轻量博客';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?></title>
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
.post-item{padding:16px 0;border-bottom:1px solid #f0f0f0}
.post-item h2{font-size:19px;line-height:1.45;font-weight:600}
.post-item h2 a{color:#222}
.post-item h2 a:hover{color:#06c}
.meta{color:#999;font-size:12.5px;margin:6px 0}
.meta a{color:#999}
.meta a:hover{color:#06c}
.summary{color:#555;font-size:14px}
.empty{color:#888;padding:30px 0;text-align:center}
.pager{margin:28px 0 6px}
.pager a,.pager span{display:inline-block;min-width:32px;text-align:center;padding:5px 10px;margin:0 5px 6px 0;border:1px solid #ddd;border-radius:4px;color:#444;font-size:13.5px;background:#fff}
.pager a:hover{border-color:#06c;color:#06c}
.pager .cur{background:#06c;border-color:#06c;color:#fff}
.pager .dis{color:#bbb;background:#fafafa;border-color:#eee}
.site-foot{margin-top:44px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.layout{flex-direction:column;gap:26px}.side-col{width:100%}.wrap{padding:16px 14px 30px}.site-title{font-size:21px}.post-item h2{font-size:17px}.pager a,.pager span{padding:5px 8px;min-width:28px}}
</style>
</head>
<body>
<div class="wrap">
  <header class="site-head">
    <a class="site-title" href="index.php">FlyLog</a>
    <span class="site-sub"><?= $head_note ?> · <a href="admin/login.php">后台管理</a></span>
  </header>
  <div class="layout">
    <div class="main-col">
      <?= $items ?>
      <?= $pager ?>
    </div>
    <?php include __DIR__ . '/sidebar.php'; ?>
  </div>
  <footer class="site-foot">Powered by FlyLog · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
