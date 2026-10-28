<?php
/**
 * FlyLog 后台登录
 * 验证账号密码，成功写入 session；支持 password_verify 与旧版明文密码
 * V1.01：新增登录防爆破（连错 5 次锁 5 分钟）；会话 cookie 加固（HttpOnly + SameSite）
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

// 已登录直接进后台
if (isset($_SESSION['fly_admin_id'])) {
    header('Location: index.php');
    exit;
}

// ---------- V1.01 登录防爆破：连续输错 5 次，锁定 5 分钟 ----------
$now = time();
if (!isset($_SESSION['fly_login_guard'])) {
    $_SESSION['fly_login_guard'] = array('fails' => 0, 'lock_until' => 0);
}
if ($_SESSION['fly_login_guard']['lock_until'] > 0 && $_SESSION['fly_login_guard']['lock_until'] <= $now) {
    $_SESSION['fly_login_guard'] = array('fails' => 0, 'lock_until' => 0); // 锁定期满，重新计数
}
$locked = ($_SESSION['fly_login_guard']['lock_until'] > $now);

$err = '';
if ($locked) {
    $err = '密码连续输错次数过多，账号已临时锁定，请 '
        . (int)ceil(($_SESSION['fly_login_guard']['lock_until'] - $now) / 60)
        . ' 分钟后再试。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = '请输入账号和密码。';
    } else {
        $passed = false;
        $res = $conn->query("SELECT `id`, `username`, `password` FROM `admins` WHERE `username` = '$username' LIMIT 1");
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $passed = password_verify($password, $row['password']);
            // 兼容旧版明文密码：比对成功则顺手升级为哈希
            if (!$passed && $row['password'] === $password) {
                $passed = true;
                $new_hash = mysqli_real_escape_string($conn, password_hash($password, PASSWORD_DEFAULT));
                $conn->query("UPDATE `admins` SET `password` = '$new_hash' WHERE `id` = " . (int)$row['id']);
            }
            if ($passed) {
                $_SESSION['fly_login_guard'] = array('fails' => 0, 'lock_until' => 0);
                session_regenerate_id(true); // 防会话固定攻击
                $_SESSION['fly_admin_id'] = (int)$row['id'];
                $_SESSION['fly_admin_name'] = $row['username'];
                header('Location: index.php');
                exit;
            }
        }
        $err = '账号或密码不正确。';
        $_SESSION['fly_login_guard']['fails']++;
        if ($_SESSION['fly_login_guard']['fails'] >= 5) {
            $_SESSION['fly_login_guard']['fails'] = 0;
            $_SESSION['fly_login_guard']['lock_until'] = $now + 300; // 锁定 5 分钟
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.login-card{max-width:360px;margin:70px auto;padding:34px 30px;border:1px solid #eee;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.05)}
.login-card h1{font-size:21px;color:#222;text-align:center;margin-bottom:6px}
.login-card .sub{text-align:center;color:#999;font-size:13px;margin-bottom:20px}
label{display:block;font-size:13.5px;color:#555;margin:12px 0 4px;font-weight:600}
input{width:100%;padding:9px 11px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit;background:#fff;color:#333}
input:focus{outline:none;border-color:#06c}
.btn{display:block;width:100%;margin-top:20px;padding:9px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center}
.btn:hover{background:#004a99}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:14px}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.back{display:block;text-align:center;margin-top:16px;font-size:13px;color:#999}
@media (max-width:768px){.login-card{margin:30px auto;box-shadow:none}}
</style>
</head>
<body>
<div class="login-card">
  <h1>FlyLog 后台</h1>
  <p class="sub">致敬 Z-Blog 的轻量博客 · V<?= FLYLOG_VERSION ?></p>
  <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <label for="username">账号</label>
    <input id="username" name="username" maxlength="100" value="<?= fly_e($_POST['username'] ?? '') ?>" placeholder="admin" required>
    <label for="password">密码</label>
    <input id="password" name="password" type="password" maxlength="100" placeholder="••••••" required>
    <button class="btn" type="submit">登 录</button>
  </form>
  <a class="back" href="../index.php">&laquo; 返回博客首页</a>
</div>
</body>
</html>
