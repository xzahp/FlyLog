<?php
/**
 * FlyLog 后台 - 文章管理（V1.1 新增页面）
 * 功能：文章列表（编辑 / 删除 / 查看）、按标题搜索、写新文章入口
 * 说明：V1.1 起后台首页 admin/index.php 改为仪表盘，文章管理迁至本页
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

// 删除文章（连同该文章下的留言）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id > 0) {
        $conn->query("DELETE FROM `comments` WHERE `post_id` = $del_id");
        $conn->query("DELETE FROM `posts` WHERE `id` = $del_id");
    }
    header('Location: posts.php');
    exit;
}

// V1.1：按标题搜索
$q = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$where = ($q !== '') ? "WHERE `title` LIKE '%$q%'" : '';

$res = $conn->query("SELECT `id`, `title`, `category`, `views`, `created_at` FROM `posts` $where ORDER BY `id` DESC");
$rows = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$flash = isset($_GET['saved']) ? '<div class="msg msg-ok">保存成功</div>' : '';
$flash .= (isset($_GET['deleted']) && $_GET['deleted'] === '1') ? '<div class="msg msg-ok">文章已删除</div>' : '';

$list = '';
foreach ($rows as $r) {
    $cat = ($r['category'] !== '') ? fly_e($r['category']) : '<span style="color:#ccc">未分类</span>';
    $list .= '<tr>'
        . '<td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td><a href="../post.php?id=' . (int)$r['id'] . '" target="_blank">' . fly_e($r['title']) . '</a></td>'
        . '<td class="col-cat">' . $cat . '</td>'
        . '<td class="col-num">' . (int)$r['views'] . '</td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td>'
        . '<td class="col-acts">'
        . '<a class="btn btn-gray btn-sm" href="post.php?id=' . (int)$r['id'] . '">编辑</a> '
        . '<form class="inline-form" method="post" action="posts.php" onsubmit="return confirm(\'确定删除这篇文章？相关留言也会一起删除。\');">'
        . '<input type="hidden" name="delete_id" value="' . (int)$r['id'] . '">'
        . '<button type="submit" class="btn btn-red btn-sm">删除</button>'
        . '</form>'
        . '</td></tr>';
}
if ($list === '') {
    $list = '<tr><td colspan="6" class="empty">'
        . ($q !== '' ? '没有找到标题包含「' . fly_e($q) . '」的文章。<br><a href="posts.php">查看全部文章</a>'
                     : '还没有文章，点击右上角“写新文章”开始吧。')
        . '</td></tr>';
}

$admin_name = fly_e($_SESSION['fly_admin_name'] ?? 'admin');
$search_val = fly_e($q);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文章管理 - FlyLog 后台</title>
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
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:18px}
.topbar .t{font-size:20px;font-weight:700;color:#222}
.topbar .ops{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.who{color:#999;font-size:13px;margin-right:4px}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:#004a99;color:#fff}
.btn-gray{background:#888}
.btn-gray:hover{background:#666}
.btn-red{background:#c33}
.btn-red:hover{background:#a22}
.btn-sm{padding:5px 14px;font-size:13px}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.search-row{margin-bottom:14px;display:flex;gap:8px}
.search-row input{flex:1;max-width:340px;padding:8px 11px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit}
.search-row input:focus{outline:none;border-color:#06c}
.table-scroll{overflow-x:auto}
table.admin{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.admin th,table.admin td{padding:10px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
table.admin th{color:#888;font-weight:600;font-size:13px;background:#fafafa;border-bottom:2px solid #eee}
table.admin tr:hover td{background:#fafcff}
.col-id{width:56px;color:#999}
.col-time{width:150px;color:#999;font-size:13px;white-space:nowrap}
.col-cat{width:110px;color:#666}
.col-num{width:70px;color:#666;white-space:nowrap}
.col-acts{width:130px;white-space:nowrap}
.inline-form{display:inline}
.empty{color:#888;padding:30px 0;text-align:center}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}.topbar .t{font-size:18px}}
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <a href="index.php">仪表盘</a>
    <a href="posts.php" class="on">文章管理</a>
    <a href="comments.php">留言审核</a>
    <a href="logs.php">登录日志</a>
    <a href="password.php">修改密码</a>
  </nav>
  <div class="topbar">
    <span class="t">文章管理</span>
    <div class="ops">
      <span class="who">当前用户：<?= $admin_name ?></span>
      <a class="btn" href="post.php">写新文章</a>
      <a class="btn btn-gray" href="../index.php" target="_blank">查看前台</a>
      <a class="btn btn-gray" href="logout.php">退出登录</a>
    </div>
  </div>
  <?= $flash ?>
  <form class="search-row" method="get" action="posts.php">
    <input type="text" name="q" value="<?= $search_val ?>" placeholder="按标题搜索文章……">
    <button class="btn btn-gray" type="submit">搜索</button>
    <?php if ($q !== ''): ?><a class="btn btn-gray" href="posts.php">重置</a><?php endif; ?>
  </form>
  <div class="table-scroll">
    <table class="admin">
      <thead>
        <tr><th class="col-id">ID</th><th>标题</th><th class="col-cat">分类</th><th class="col-num">阅读</th><th class="col-time">时间</th><th class="col-acts">操作</th></tr>
      </thead>
      <tbody><?= $list ?></tbody>
    </table>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
