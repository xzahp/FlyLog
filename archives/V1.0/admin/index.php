<?php
/**
 * FlyLog 后台首页
 * session 验证登录，未登录跳转 login.php
 * 功能：文章列表（编辑 / 删除）、写新文章入口
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

// 删除文章（连同该文章下的评论）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id > 0) {
        $conn->query("DELETE FROM `comments` WHERE `post_id` = $del_id");
        $conn->query("DELETE FROM `posts` WHERE `id` = $del_id");
    }
    header('Location: index.php');
    exit;
}

$res = $conn->query("SELECT `id`, `title`, `category`, `created_at` FROM `posts` ORDER BY `id` DESC");
$rows = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$flash = isset($_GET['saved']) ? '<div class="msg msg-ok">保存成功</div>' : '';

$list = '';
foreach ($rows as $r) {
    $cat = ($r['category'] !== '') ? fly_e($r['category']) : '<span style="color:#ccc">未分类</span>';
    $list .= '<tr>'
        . '<td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td><a href="../post.php?id=' . (int)$r['id'] . '" target="_blank">' . fly_e($r['title']) . '</a></td>'
        . '<td class="col-cat">' . $cat . '</td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td>'
        . '<td class="col-acts">'
        . '<a class="btn btn-gray btn-sm" href="post.php?id=' . (int)$r['id'] . '">编辑</a> '
        . '<form class="inline-form" method="post" action="index.php" onsubmit="return confirm(\'确定删除这篇文章？相关评论也会一起删除。\');">'
        . '<input type="hidden" name="delete_id" value="' . (int)$r['id'] . '">'
        . '<button type="submit" class="btn btn-red btn-sm">删除</button>'
        . '</form>'
        . '</td></tr>';
}
if ($list === '') {
    $list = '<tr><td colspan="5" class="empty">还没有文章，点击右上角“写新文章”开始吧。</td></tr>';
}

$admin_name = fly_e($_SESSION['fly_admin_name'] ?? 'admin');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文章管理 - FlyLog 后台</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:960px;margin:0 auto;padding:24px 20px 40px}
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
.table-scroll{overflow-x:auto}
table.admin{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.admin th,table.admin td{padding:10px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
table.admin th{color:#888;font-weight:600;font-size:13px;background:#fafafa;border-bottom:2px solid #eee}
table.admin tr:hover td{background:#fafcff}
.col-id{width:56px;color:#999}
.col-time{width:150px;color:#999;font-size:13px;white-space:nowrap}
.col-cat{width:110px;color:#666}
.col-acts{width:130px;white-space:nowrap}
.inline-form{display:inline}
.empty{color:#888;padding:30px 0;text-align:center}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}.topbar .t{font-size:18px}}
</style>
</head>
<body>
<div class="wrap">
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
  <div class="table-scroll">
    <table class="admin">
      <thead>
        <tr><th class="col-id">ID</th><th>标题</th><th class="col-cat">分类</th><th class="col-time">时间</th><th class="col-acts">操作</th></tr>
      </thead>
      <tbody><?= $list ?></tbody>
    </table>
  </div>
  <footer class="site-foot">Powered by FlyLog · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
