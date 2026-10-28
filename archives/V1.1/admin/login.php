<?php
/**
 * FlyLog 后台登录
 * 验证账号密码，成功写入 session；支持 password_verify 与旧版明文密码
 * V1.01：新增登录防爆破（连错 5 次锁 5 分钟）；会话 cookie 加固（HttpOnly + SameSite）
 * V1.1 ：防爆破升级为 IP + 数据库级（login_attempts 表，清 cookie 也无法绕过）；
 *         新增登录日志（admin_logs 表，成功/失败均记录，后台可查）
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

$ip = fly_client_ip();
$ip_sql = mysqli_real_escape_string($conn, $ip);
$now = time();

/**
 * V1.1 辅助：写一条登录日志
 */
function fly_write_login_log($conn, $username, $ip, $success)
{
    $u = mysqli_real_escape_string($conn, substr((string)$username, 0, 100));
    $i = mysqli_real_escape_string($conn, substr((string)$ip, 0, 45));
    $s = $success ? 1 : 0;
    $conn->query("INSERT INTO `admin_logs` (`username`, `ip`, `success`) VALUES ('$u', '$i', $s)");
}

// ---------- V1.1 登录防爆破：同一 IP 连错 5 次，锁定 5 分钟（数据库记录） ----------
$guard = array('fails' => 0, 'lock_until' => 0);
$gres = $conn->query("SELECT `fails`, `lock_until` FROM `login_attempts` WHERE `ip` = '$ip_sql' LIMIT 1");
if ($gres && $gres->num_rows === 1) {
    $g = $gres->fetch_assoc();
    $guard['fails'] = (int)$g['fails'];
    $guard['lock_until'] = ($g['lock_until'] !== null) ? (int)strtotime($g['lock_until']) : 0;
}
// 锁定期满：自动清零重新计数
if ($guard['lock_until'] > 0 && $guard['lock_until'] <= $now) {
    $conn->query("UPDATE `login_attempts` SET `fails` = 0, `lock_until` = NULL WHERE `ip` = '$ip_sql'");
    $guard = array('fails' => 0, 'lock_until' => 0);
}
$locked = ($guard['lock_until'] > $now);
$wait_min = $locked ? (int)ceil(($guard['lock_until'] - $now) / 60) : 0;

$err = '';
if ($locked) {
    $err = '密码连续输错次数过多，本机 IP 已临时锁定，请 ' . $wait_min . ' 分钟后再试。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = '请输入账号和密码。';
        fly_write_login_log($conn, $username, $ip, false);
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
                // 登录成功：清除该 IP 的失败记录 + 写日志 + 加固会话
                $conn->query("DELETE FROM `login_attempts` WHERE `ip` = '$ip_sql'");
                fly_write_login_log($conn, $row['username'], $ip, true);
                session_regenerate_id(true); // 防会话固定攻击
                $_SESSION['fly_admin_id'] = (int)$row['id'];
                $_SESSION['fly_admin_name'] = $row['username'];
                header('Location: index.php');
                exit;
            }
        }
        // 登录失败：失败计数 +1（UPSERT），达到 5 次锁定 5 分钟，并写日志
        $err = '账号或密码不正确。';
        fly_write_login_log($conn, $username, $ip, false);
        $conn->query(
            "INSERT INTO `login_attempts` (`ip`, `fails`, `lock_until`) VALUES ('$ip_sql', 1, NULL)
             ON DUPLICATE KEY UPDATE `fails` = `fails` + 1"
        );
        $gres2 = $conn->query("SELECT `fails` FROM `login_attempts` WHERE `ip` = '$ip_sql' LIMIT 1");
        $fails_now = ($gres2 && $gres2->num_rows === 1) ? (int)$gres2->fetch_assoc()['fails'] : 0;
        if ($fails_now >= 5) {
            $lock_esc = mysqli_real_escape_string($conn, date('Y-m-d H:i:s', $now + 300));
            $conn->query("UPDATE `login_attempts` SET `lock_until` = '$lock_esc' WHERE `ip` = '$ip_sql'");
            $err = '密码连续输错次数过多，本机 IP 已临时锁定 5 分钟，请稍后再试。';
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
