<?php
/**
 * FlyLog 后台 - 登录日志（V1.1 新增页面）
 * 功能：查看最近登录尝试（账号 / IP / 结果 / 时间）、清空日志
 * 说明：V1.1 起每次登录尝试（无论成败）都会记录到 admin_logs 表
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

// 清空日志
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $conn->query("TRUNCATE TABLE `admin_logs`");
    header('Location: logs.php?ok=' . urlencode('登录日志已清空'));
    exit;
}

$res = $conn->query("SELECT `id`, `username`, `ip`, `success`, `created_at` FROM `admin_logs` ORDER BY `id` DESC LIMIT 100");
$rows = array();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$flash = isset($_GET['ok']) ? '<div class="msg msg-ok">' . fly_e($_GET['ok']) . '</div>' : '';

// 统计：近 24 小时失败次数（仪表盘也会用到同样口径）
$fres = $conn->query("SELECT COUNT(*) AS c FROM `admin_logs` WHERE `success` = 0 AND `created_at` >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
$fail_24h = ($fres) ? (int)$fres->fetch_assoc()['c'] : 0;

$list = '';
foreach ($rows as $r) {
    $ok = ((int)$r['success'] === 1);
    $result_cell = $ok
        ? '<span class="tag tag-ok">成功</span>'
        : '<span class="tag tag-bad">失败</span>';
    $list .= '<tr>'
        . '<td class="col-id">' . (int)$r['id'] . '</td>'
        . '<td>' . fly_e($r['username']) . '</td>'
        . '<td class="col-ip">' . fly_e($r['ip']) . '</td>'
        . '<td class="col-status">' . $result_cell . '</td>'
        . '<td class="col-time">' . fly_e($r['created_at']) . '</td>'
        . '</tr>';
}
if ($list === '') {
    $list = '<tr><td colspan="5" class="empty">还没有登录记录。</td></tr>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录日志 - FlyLog 后台</title>
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
.stat-line{color:#888;font-size:13px;margin-bottom:14px}
.table-scroll{overflow-x:auto}
table.admin{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
table.admin th,table.admin td{padding:10px 8px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
table.admin th{color:#888;font-weight:600;font-size:13px;background:#fafafa;border-bottom:2px solid #eee}
table.admin tr:hover td{background:#fafcff}
.col-id{width:56px;color:#999}
.col-ip{width:150px;color:#666;font-family:Consolas,Menlo,monospace;font-size:13px}
.col-status{width:80px}
.col-time{width:170px;color:#999;font-size:13px;white-space:nowrap}
.inline-form{display:inline}
.tag{display:inline-block;padding:2px 9px;border-radius:12px;font-size:12px}
.tag-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.tag-bad{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
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
    <a href="comments.php">留言审核</a>
    <a href="logs.php" class="on">登录日志</a>
    <a href="password.php">修改密码</a>
  </nav>
  <div class="topbar">
    <span class="t">登录日志</span>
    <div class="ops">
      <form class="inline-form" method="post" action="logs.php" onsubmit="return confirm('确定清空全部登录日志？');">
        <input type="hidden" name="clear_logs" value="1">
        <button type="submit" class="btn btn-red btn-sm">清空日志</button>
      </form>
      <a class="btn btn-gray" href="../index.php" target="_blank">查看前台</a>
    </div>
  </div>
  <?= $flash ?>
  <p class="stat-line">仅显示最近 100 条记录 · 近 24 小时登录失败 <b><?= $fail_24h ?></b> 次</p>
  <div class="table-scroll">
    <table class="admin">
      <thead>
        <tr><th class="col-id">ID</th><th>尝试账号</th><th class="col-ip">IP</th><th class="col-status">结果</th><th class="col-time">时间</th></tr>
      </thead>
      <tbody><?= $list ?></tbody>
    </table>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
