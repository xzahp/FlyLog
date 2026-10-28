<?php
/**
 * FlyLog 后台 - 修改密码（V1.1 新增页面）
 * 功能：验证旧密码 -> 设置新密码（至少 8 位、两次输入一致）
 * 说明：密码一律 password_hash 哈希存储，不明文落库
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_id = (int)$_SESSION['fly_admin_id'];
$err = '';
$ok_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');

    if ($old === '' || $new === '' || $new2 === '') {
        $err = '三项都要填写。';
    } elseif (strlen($new) < 8) {
        $err = '新密码至少 8 位，为了安全请设长一点。';
    } elseif ($new !== $new2) {
        $err = '两次输入的新密码不一致。';
    } else {
        $res = $conn->query("SELECT `password` FROM `admins` WHERE `id` = $admin_id LIMIT 1");
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            // 兼容旧版明文密码（明文比对通过也允许改密）
            $old_ok = password_verify($old, $row['password']) || ($row['password'] === $old);
            if (!$old_ok) {
                $err = '旧密码不正确。';
            } elseif ($new === $old) {
                $err = '新密码不能和旧密码一样。';
            } else {
                $hash = mysqli_real_escape_string($conn, password_hash($new, PASSWORD_DEFAULT));
                $conn->query("UPDATE `admins` SET `password` = '$hash' WHERE `id` = $admin_id");
                $ok_msg = '密码修改成功！下次登录请使用新密码。';
            }
        } else {
            $err = '账号异常，请重新登录后再试。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>修改密码 - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:560px;margin:0 auto;padding:24px 20px 40px}
.nav{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:13.5px;border-bottom:1px solid #eee;padding-bottom:10px}
.nav a{color:#666;padding:3px 8px;border-radius:4px}
.nav a:hover{color:#06c;background:#f5f9ff}
.nav a.on{color:#06c;font-weight:600;background:#eef6ff}
.topbar{margin-bottom:18px}
.topbar .t{font-size:20px;font-weight:700;color:#222}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:#004a99;color:#fff}
.btn-gray{background:#888}
.btn-gray:hover{background:#666}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.form-box label{display:block;font-size:13.5px;color:#555;margin:14px 0 5px;font-weight:600}
.form-box input{width:100%;padding:9px 11px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit;background:#fff;color:#333}
.form-box input:focus{outline:none;border-color:#06c}
.tip{color:#999;font-size:12.5px;margin-top:4px}
.form-ops{margin-top:22px;display:flex;gap:10px;align-items:center}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}}
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <a href="index.php">仪表盘</a>
    <a href="posts.php">文章管理</a>
    <a href="comments.php">留言审核</a>
    <a href="logs.php">登录日志</a>
    <a href="password.php" class="on">修改密码</a>
  </nav>
  <div class="topbar"><span class="t">修改密码</span></div>
  <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
  <?php if ($ok_msg !== ''): ?><div class="msg msg-ok"><?= fly_e($ok_msg) ?></div><?php endif; ?>
  <form class="form-box" method="post" action="password.php">
    <label for="old_password">当前密码</label>
    <input id="old_password" name="old_password" type="password" maxlength="100" placeholder="请输入现在使用的密码" required>
    <label for="new_password">新密码</label>
    <input id="new_password" name="new_password" type="password" maxlength="100" placeholder="至少 8 位" required>
    <p class="tip">建议用「大小写字母 + 数字 + 符号」组合，长度越长越安全。</p>
    <label for="new_password2">确认新密码</label>
    <input id="new_password2" name="new_password2" type="password" maxlength="100" placeholder="再输一遍新密码" required>
    <div class="form-ops">
      <button class="btn" type="submit">保存新密码</button>
      <a class="btn btn-gray" href="index.php">返回仪表盘</a>
    </div>
  </form>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
