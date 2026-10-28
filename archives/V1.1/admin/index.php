<?php
/**
 * FlyLog 后台首页 - 仪表盘（V1.1 重做）
 * session 验证登录，未登录跳转 login.php
 * 功能：站点统计卡片、快捷入口、最近文章、待审核留言预览
 * 说明：V1.1 起文章管理迁至 posts.php，留言管理迁至 comments.php
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

// ---------- 统计数据 ----------
function fly_count($conn, $sql)
{
    $res = $conn->query($sql);
    return ($res && $res->num_rows === 1) ? (int)$res->fetch_assoc()['c'] : 0;
}
$post_count = fly_count($conn, "SELECT COUNT(*) AS c FROM `posts`");
$comment_count = fly_count($conn, "SELECT COUNT(*) AS c FROM `comments`");
$pending_count = fly_count($conn, "SELECT COUNT(*) AS c FROM `comments` WHERE `status` = 0");
$cat_count = fly_count($conn, "SELECT COUNT(DISTINCT `category`) AS c FROM `posts` WHERE `category` <> ''");
$view_count = fly_count($conn, "SELECT COALESCE(SUM(`views`), 0) AS c FROM `posts`");
$fail_24h = fly_count($conn, "SELECT COUNT(*) AS c FROM `admin_logs` WHERE `success` = 0 AND `created_at` >= DATE_SUB(NOW(), INTERVAL 1 DAY)");

// 最近文章 5 篇
$recent = array();
$rres = $conn->query("SELECT `id`, `title`, `created_at` FROM `posts` ORDER BY `id` DESC LIMIT 5");
if ($rres) {
    while ($r = $rres->fetch_assoc()) {
        $recent[] = $r;
    }
}
$recent_html = '';
foreach ($recent as $r) {
    $recent_html .= '<tr><td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td><a href="post.php?id=' . (int)$r['id'] . '">' . fly_e($r['title']) . '</a></td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td></tr>';
}
if ($recent_html === '') {
    $recent_html = '<tr><td colspan="3" class="empty">还没有文章，去写第一篇吧。</td></tr>';
}

// 待审核留言预览（最多 5 条）
$pend = array();
$pres = $conn->query(
    "SELECT c.`id`, c.`author`, c.`content`, c.`created_at`, p.`title` AS post_title
     FROM `comments` c LEFT JOIN `posts` p ON p.`id` = c.`post_id`
     WHERE c.`status` = 0 ORDER BY c.`id` DESC LIMIT 5"
);
if ($pres) {
    while ($r = $pres->fetch_assoc()) {
        $pend[] = $r;
    }
}
$pend_html = '';
foreach ($pend as $r) {
    $pend_html .= '<tr><td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td><b>' . fly_e($r['author']) . '</b> <span class="c-post">@ '
        . ($r['post_title'] !== null ? fly_e($r['post_title']) : '文章已删除') . '</span><br>'
        . '<span class="c-text">' . fly_e(fly_excerpt($r['content'], 60)) . '</span></td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td></tr>';
}
if ($pend_html === '') {
    $pend_html = '<tr><td colspan="3" class="empty">没有待审核的留言，很干净。</td></tr>';
}

$flash = isset($_GET['saved']) ? '<div class="msg msg-ok">保存成功</div>' : '';
$admin_name = fly_e($_SESSION['fly_admin_name'] ?? 'admin');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>仪表盘 - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:960px;margin:0 auto;padding:24px 20px 40px}
.nav{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:13.5px;border-bottom:1px solid #eee;padding-bottom:10px}
.nav a{color:#666;padding:3px 8px;border-radius:4px}
.nav a:hover{color:#06c;background:#f5f9ff}
.nav a.on{color:#06c;font-weight:600;background:#eef6ff}
.nav .badge{display:inline-block;min-width:18px;padding:0 5px;margin-left:3px;background:#c33;color:#fff;border-radius:9px;font-size:11px;text-align:center;line-height:17px}
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.topbar .t{font-size:20px;font-weight:700;color:#222}
.topbar .ops{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.who{color:#999;font-size:13px;margin-right:4px}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:#004a99;color:#fff}
.btn-gray{background:#888}
.btn-gray:hover{background:#666}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:26px}
.card{border:1px solid #eee;border-radius:8px;padding:16px 18px;background:#fcfcfd}
.card .num{font-size:26px;font-weight:700;color:#222;line-height:1.2}
.card .lbl{font-size:12.5px;color:#999;margin-top:2px}
.card.warn .num{color:#c33}
.card.ok .num{color:#2a7a2a}
.entries{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:30px}
.entry{display:block;border:1px solid #eee;border-radius:8px;padding:14px 16px;color:#333;background:#fff}
.entry:hover{border-color:#06c;background:#f8fbff;color:#06c}
.entry .et{font-size:15px;font-weight:600}
.entry .ed{font-size:12.5px;color:#999;margin-top:3px}
.panel{margin-bottom:28px}
.panel h3{font-size:15px;color:#222;padding-bottom:8px;border-bottom:2px solid #f0f0f0;margin-bottom:4px;display:flex;justify-content:space-between;align-items:baseline}
.panel h3 .more{font-size:12.5px;font-weight:400;color:#999}
.table-scroll{overflow-x:auto}
table.admin{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.admin th,table.admin td{padding:9px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
table.admin th{color:#888;font-weight:600;font-size:13px;background:#fafafa;border-bottom:2px solid #eee}
table.admin tr:hover td{background:#fafcff}
.col-id{width:56px;color:#999}
.col-time{width:150px;color:#999;font-size:13px;white-space:nowrap}
.c-post{font-size:12.5px;color:#999}
.c-text{font-size:13px;color:#666}
.empty{color:#888;padding:20px 0;text-align:center}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}.topbar .t{font-size:18px}.card .num{font-size:22px}}
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <a href="index.php" class="on">仪表盘</a>
    <a href="posts.php">文章管理</a>
    <a href="comments.php">留言审核<?= $pending_count > 0 ? '<span class="badge">' . $pending_count . '</span>' : '' ?></a>
    <a href="logs.php">登录日志</a>
    <a href="password.php">修改密码</a>
  </nav>
  <div class="topbar">
    <span class="t">仪表盘</span>
    <div class="ops">
      <span class="who">你好，<?= $admin_name ?></span>
      <a class="btn" href="post.php">写新文章</a>
      <a class="btn btn-gray" href="../index.php" target="_blank">查看前台</a>
      <a class="btn btn-gray" href="logout.php">退出登录</a>
    </div>
  </div>
  <?= $flash ?>
  <div class="cards">
    <div class="card"><div class="num"><?= $post_count ?></div><div class="lbl">文章总数</div></div>
    <div class="card<?= $pending_count > 0 ? ' warn' : '' ?>"><div class="num"><?= $pending_count ?></div><div class="lbl">待审核留言</div></div>
    <div class="card"><div class="num"><?= $comment_count ?></div><div class="lbl">留言总数</div></div>
    <div class="card"><div class="num"><?= $cat_count ?></div><div class="lbl">分类数</div></div>
    <div class="card"><div class="num"><?= $view_count ?></div><div class="lbl">总阅读量</div></div>
    <div class="card<?= $fail_24h > 0 ? ' warn' : ' ok' ?>"><div class="num"><?= $fail_24h ?></div><div class="lbl">近 24h 登录失败</div></div>
  </div>
  <div class="entries">
    <a class="entry" href="post.php"><div class="et">✏️ 写新文章</div><div class="ed">发布一篇新博客</div></a>
    <a class="entry" href="posts.php"><div class="et">📄 文章管理</div><div class="ed">编辑 / 删除 / 搜索文章</div></a>
    <a class="entry" href="comments.php"><div class="et">💬 留言审核</div><div class="ed"><?= $pending_count > 0 ? $pending_count . ' 条留言等你放行' : '暂无待审核留言' ?></div></a>
    <a class="entry" href="logs.php"><div class="et">🛡️ 登录日志</div><div class="ed">看看谁来试过密码</div></a>
    <a class="entry" href="password.php"><div class="et">🔑 修改密码</div><div class="ed">定期更换更安全</div></a>
  </div>
  <div class="panel">
    <h3>最近文章 <span class="more"><a href="posts.php">进入文章管理 &raquo;</a></span></h3>
    <div class="table-scroll">
      <table class="admin">
        <thead><tr><th class="col-id">ID</th><th>标题</th><th class="col-time">发布时间</th></tr></thead>
        <tbody><?= $recent_html ?></tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <h3>待审核留言 <span class="more"><a href="comments.php?filter=pending">去处理 &raquo;</a></span></h3>
    <div class="table-scroll">
      <table class="admin">
        <thead><tr><th class="col-id">ID</th><th>留言</th><th class="col-time">时间</th></tr></thead>
        <tbody><?= $pend_html ?></tbody>
      </table>
    </div>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
