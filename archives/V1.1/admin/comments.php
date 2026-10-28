<?php
/**
 * FlyLog 后台 - 留言审核（V1.1 新增页面）
 * 功能：留言列表（按 全部 / 待审核 / 已通过 筛选）、通过 / 取消通过 / 删除、
 *       一键通过所有待审核留言
 * 说明：V1.1 起前台留言默认进入待审核，博主通过后才对外显示
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

// ---------- 操作处理 ----------
$op = isset($_POST['op']) ? $_POST['op'] : '';
$cid = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $op !== '') {
    if ($op === 'approve_all') {
        $conn->query("UPDATE `comments` SET `status` = 1 WHERE `status` = 0");
        header('Location: comments.php?ok=' . urlencode('所有待审核留言已通过'));
        exit;
    }
    if ($cid > 0) {
        if ($op === 'approve') {
            $conn->query("UPDATE `comments` SET `status` = 1 WHERE `id` = $cid");
            header('Location: comments.php?ok=' . urlencode('留言已通过'));
            exit;
        } elseif ($op === 'unapprove') {
            $conn->query("UPDATE `comments` SET `status` = 0 WHERE `id` = $cid");
            header('Location: comments.php?ok=' . urlencode('留言已设为待审核'));
            exit;
        } elseif ($op === 'delete') {
            $conn->query("DELETE FROM `comments` WHERE `id` = $cid");
            header('Location: comments.php?ok=' . urlencode('留言已删除'));
            exit;
        }
    }
    header('Location: comments.php');
    exit;
}

// ---------- 筛选与列表 ----------
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if (!in_array($filter, array('all', 'pending', 'approved'), true)) {
    $filter = 'all';
}
$where = '';
if ($filter === 'pending') { $where = 'WHERE c.`status` = 0'; }
elseif ($filter === 'approved') { $where = 'WHERE c.`status` = 1'; }

// 待审核总数（用于导航角标与“一键通过”按钮状态）
$pres = $conn->query("SELECT COUNT(*) AS c FROM `comments` WHERE `status` = 0");
$pending_count = ($pres) ? (int)$pres->fetch_assoc()['c'] : 0;

// LEFT JOIN：文章可能已被删除，留言仍要能管理
$res = $conn->query(
    "SELECT c.`id`, c.`author`, c.`content`, c.`status`, c.`created_at`, c.`post_id`, p.`title` AS post_title
     FROM `comments` c LEFT JOIN `posts` p ON p.`id` = c.`post_id`
     $where ORDER BY c.`id` DESC LIMIT 200"
);
$rows = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$flash = isset($_GET['ok']) ? '<div class="msg msg-ok">' . fly_e($_GET['ok']) . '</div>' : '';

$list = '';
foreach ($rows as $r) {
    $post_cell = ($r['post_title'] !== null)
        ? '<a href="../post.php?id=' . (int)$r['post_id'] . '" target="_blank">' . fly_e($r['post_title']) . '</a>'
        : '<span style="color:#ccc">文章已删除</span>';
    $status_cell = ((int)$r['status'] === 1)
        ? '<span class="tag tag-ok">已通过</span>'
        : '<span class="tag tag-wait">待审核</span>';
    $list .= '<tr>'
        . '<td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td><b>' . fly_e($r['author']) . '</b><br><span class="c-post">' . $post_cell . '</span></td>'
        . '<td class="col-body">' . nl2br(fly_e($r['content'])) . '</td>'
        . '<td class="col-status">' . $status_cell . '</td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td>'
        . '<td class="col-acts">'
        . ((int)$r['status'] === 1
            ? '<form class="inline-form" method="post" action="comments.php"><input type="hidden" name="comment_id" value="' . (int)$r['id'] . '"><input type="hidden" name="op" value="unapprove"><button type="submit" class="btn btn-gray btn-sm">撤下</button></form> '
            : '<form class="inline-form" method="post" action="comments.php"><input type="hidden" name="comment_id" value="' . (int)$r['id'] . '"><input type="hidden" name="op" value="approve"><button type="submit" class="btn btn-sm">通过</button></form> ')
        . '<form class="inline-form" method="post" action="comments.php" onsubmit="return confirm(\'确定删除这条留言？\');">'
        . '<input type="hidden" name="comment_id" value="' . (int)$r['id'] . '"><input type="hidden" name="op" value="delete">'
        . '<button type="submit" class="btn btn-red btn-sm">删除</button></form>'
        . '</td></tr>';
}
if ($list === '') {
    $list = '<tr><td colspan="6" class="empty">'
        . ($filter === 'pending' ? '太棒了，没有待审核的留言。' : '还没有留言。')
        . '</td></tr>';
}

$filter_all = ($filter === 'all') ? ' class="on"' : '';
$filter_pending = ($filter === 'pending') ? ' class="on"' : '';
$filter_approved = ($filter === 'approved') ? ' class="on"' : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>留言审核 - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:1000px;margin:0 auto;padding:24px 20px 40px}
.nav{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:13.5px;border-bottom:1px solid #eee;padding-bottom:10px}
.nav a{color:#666;padding:3px 8px;border-radius:4px}
.nav a:hover{color:#06c;background:#f5f9ff}
.nav a.on{color:#06c;font-weight:600;background:#eef6ff}
.nav .badge{display:inline-block;min-width:18px;padding:0 5px;margin-left:3px;background:#c33;color:#fff;border-radius:9px;font-size:11px;text-align:center;line-height:17px}
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:18px}
.topbar .t{font-size:20px;font-weight:700;color:#222}
.topbar .ops{display:flex;gap:8px;flex-wrap:wrap}
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
.filters{margin-bottom:14px;font-size:13.5px;color:#666}
.filters a{color:#666;margin-right:12px;padding:3px 10px;border:1px solid #ddd;border-radius:20px}
.filters a.on{color:#06c;border-color:#06c;background:#eef6ff;font-weight:600}
.table-scroll{overflow-x:auto}
table.admin{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.admin th,table.admin td{padding:10px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
table.admin th{color:#888;font-weight:600;font-size:13px;background:#fafafa;border-bottom:2px solid #eee}
table.admin tr:hover td{background:#fafcff}
.col-id{width:56px;color:#999}
.col-body{min-width:220px;color:#444;font-size:13.5px}
.col-status{width:76px}
.col-time{width:150px;color:#999;font-size:13px;white-space:nowrap}
.col-acts{width:170px;white-space:nowrap}
.c-post{font-size:12.5px;color:#999}
.inline-form{display:inline}
.tag{display:inline-block;padding:2px 9px;border-radius:12px;font-size:12px}
.tag-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.tag-wait{background:#fff8ec;border:1px solid #f0dfc0;color:#b57a1a}
.empty{color:#888;padding:30px 0;text-align:center}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}.topbar .t{font-size:18px}}
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <a href="index.php">仪表盘</a>
    <a href="posts.php">文章管理</a>
    <a href="comments.php" class="on">留言审核<?= $pending_count > 0 ? '<span class="badge">' . $pending_count . '</span>' : '' ?></a>
    <a href="logs.php">登录日志</a>
    <a href="password.php">修改密码</a>
  </nav>
  <div class="topbar">
    <span class="t">留言审核</span>
    <div class="ops">
      <?php if ($pending_count > 0): ?>
      <form class="inline-form" method="post" action="comments.php" onsubmit="return confirm('确定一次性通过全部 <?= $pending_count ?> 条待审核留言？');">
        <input type="hidden" name="op" value="approve_all">
        <button type="submit" class="btn btn-gray">一键通过全部待审核（<?= $pending_count ?>）</button>
      </form>
      <?php endif; ?>
      <a class="btn btn-gray" href="../index.php" target="_blank">查看前台</a>
    </div>
  </div>
  <?= $flash ?>
  <div class="filters">
    <a href="comments.php?filter=all"<?= $filter_all ?>>全部</a
    ><a href="comments.php?filter=pending"<?= $filter_pending ?>>待审核（<?= $pending_count ?>）</a
    ><a href="comments.php?filter=approved"<?= $filter_approved ?>>已通过</a>
  </div>
  <div class="table-scroll">
    <table class="admin">
      <thead>
        <tr><th class="col-id">ID</th><th>留言者 / 文章</th><th>内容</th><th class="col-status">状态</th><th class="col-time">时间</th><th class="col-acts">操作</th></tr>
      </thead>
      <tbody><?= $list ?></tbody>
    </table>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
