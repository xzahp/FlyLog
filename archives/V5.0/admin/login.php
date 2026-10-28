<?php
/**
 * FlyLog V2.0 后台登录（IP+数据库级防爆破 + 登录日志 + 角色）
 */
require __DIR__ . '/../config.php';

if (!empty($_SESSION['fly_admin_id'])) {
    fly_redirect('index.php');
}

$db = fly_db();
$now = date('Y-m-d H:i:s');
$ip = fly_client_ip();
$err = '';

// ---------- IP 级防爆破 ----------
$row = $db->table('login_attempts')->where('ip', $ip)->one();
$fails = $row ? (int)$row['fails'] : 0;
$lockUntil = $row && $row['lock_until'] ? strtotime($row['lock_until']) : 0;
$locked = ($lockUntil > time());
if ($lockUntil > 0 && $lockUntil <= time()) {
    // 锁定期满，重置
    if ($row) {
        $db->update('login_attempts', array('fails' => 0, 'lock_until' => null), 'ip = ?', array($ip));
    }
    $fails = 0;
    $locked = false;
}

/** 记录登录日志（IP 脱敏后存储） */
$fly_log_login = function ($username, $success) use ($db, $ip) {
    $db->insert('admin_logs', array(
        'username' => $username, 'ip' => fly_mask_ip($ip), 'success' => $success ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
    ));
};

if ($locked) {
    $remain = $lockUntil - time();
    $remainTxt = $remain >= 3600 ? ((int)ceil($remain / 3600) . ' 小时') : ((int)ceil($remain / 60) . ' 分钟');
    $err = '密码连续输错次数过多，IP 已临时锁定，请 ' . $remainTxt . '后再试。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $err = '请输入账号和密码。';
    } else {
        // SQL 注入特征直接按失败处理（PDO 预处理是主防御，此处为增强 + 计入防爆破）
        $sqli = fly_detect_sqli($username);
        $admin = $sqli ? null : $db->table('admins')->where('username', $username)->one();
        $passed = false;
        if ($admin) {
            $passed = fly_password_verify($password, $admin['password']);
            if (!$passed && $admin['password'] === $password) {
                $passed = true; // 兼容旧版明文
            }
            // 成功后顺手把旧哈希升级为 Argon2id
            if ($passed && fly_password_needs_rehash($admin['password'])) {
                $db->update('admins', array('password' => fly_password_hash($password)), 'id = ?', array((int)$admin['id']));
            }
        }
        if ($passed) {
            session_regenerate_id(true);
            $_SESSION['fly_admin_id'] = (int)$admin['id'];
            $_SESSION['fly_admin_name'] = $admin['username'];
            $_SESSION['fly_admin_role'] = $admin['role'] ?: 'admin';
            // 清除该 IP 失败计数
            if ($row) {
                $db->update('login_attempts', array('fails' => 0, 'lock_until' => null), 'ip = ?', array($ip));
            }
            $fly_log_login($username, true);
            fly_redirect('index.php');
        }
        $err = '账号或密码不正确。';
        $fly_log_login($username, false);
        // 累加失败次数，满 5 次锁 1 小时
        $newFails = $fails + 1;
        $lock = null;
        if ($newFails >= 5) {
            $lock = date('Y-m-d H:i:s', time() + 3600);
            $newFails = 0;
        }
        if ($row) {
            $db->update('login_attempts', array('fails' => $newFails, 'lock_until' => $lock), 'ip = ?', array($ip));
        } else {
            $db->insert('login_attempts', array('ip' => $ip, 'fails' => $newFails, 'lock_until' => $lock, 'updated_at' => date('Y-m-d H:i:s')));
        }
    }
}

fly_admin_head_login();
function fly_admin_head_login()
{
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<link rel="stylesheet" href="<?= fly_e(fly_base_url() . 'assets/app.css') ?>">
</head>
<body>
    <?php
}
?>
<div class="center-card">
  <h1>🚁 FlyLog 后台</h1>
  <p class="sub">请登录以管理你的博客</p>
  <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <?= fly_csrf_field() ?>
    <label for="username">账号</label>
    <input id="username" name="username" value="<?= fly_e($_POST['username'] ?? '') ?>" required autofocus>
    <label for="password">密码</label>
    <input id="password" name="password" type="password" required>
    <button class="btn" type="submit">登 录</button>
  </form>
  <p class="hint" style="text-align:center;margin-top:14px">连续输错 5 次，本 IP 将被锁定 5 分钟。</p>
</div>
</body>
</html>
