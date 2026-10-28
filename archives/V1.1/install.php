<?php
/**
 * FlyLog 一键安装 / 升级脚本
 * 功能：创建数据库 -> 创建/补齐全部数据表 -> （可选）自定义管理员账号密码
 * V1.1 变更：新增 login_attempts（登录防爆破）、admin_logs（登录日志）两张表；
 *            posts 增加 views 阅读量列、comments 增加 status 审核列；
 *            旧版数据库再次运行本文件即可平滑升级（缺表补表、缺列补列，不丢数据）；
 *            安装时支持 POST 自定义管理员账号密码（留空则用默认 admin / 123456）
 * 用法：浏览器访问本文件；看到“安装成功”即可进入后台
 * 注意：安装完成后建议删除本文件；数据库账号请与 config.php 保持一致
 */
header('Content-Type: text/html; charset=utf-8');

// 与 config.php 保持一致
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'flylog');

mysqli_report(MYSQLI_REPORT_OFF);

$steps = array();
$errors = 0;

// 第 1 步：连接 MySQL 服务器（暂不选数据库，库可能还不存在）
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_errno) {
    die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>安装失败 - FlyLog</title></head>'
        . '<body style="font-family:sans-serif;padding:40px;line-height:1.8">'
        . '<h2>无法连接 MySQL 服务器</h2>'
        . '<p>错误码 ' . $conn->connect_errno . '：'
        . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>请确认 MySQL 已启动，且本文件顶部的账号密码正确。</p>'
        . '</body></html>');
}
$conn->set_charset('utf8mb4');
$steps[] = '已连接 MySQL 服务器';

// 第 2 步：选择数据库
// 说明：虚拟主机多由面板预建库、账号只对本库有权限而无全局 CREATE 权限，
//       直接 CREATE DATABASE 会误报 access denied。故先尝试选库，选不上再尝试建库。
if ($conn->select_db(DB_NAME)) {
    $steps[] = '已选择数据库 `' . DB_NAME . '`（已存在）';
} else {
    $ok = $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    if ($ok && $conn->select_db(DB_NAME)) {
        $steps[] = '数据库 `' . DB_NAME . '` 创建成功';
    } else {
        $steps[] = '选择/创建数据库失败：' . $conn->error;
        $errors++;
    }
}

/**
 * V1.1 辅助：检查某表是否已存在某列（用于旧库平滑升级）
 */
function fly_column_exists($conn, $table, $column)
{
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($res && $res->num_rows > 0);
}

// 第 3 步：文章表（V1.1 增加 views 阅读量列）
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `posts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `summary` VARCHAR(500) NOT NULL DEFAULT '',
        `content` MEDIUMTEXT,
        `category` VARCHAR(100) NOT NULL DEFAULT '',
        `views` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_category` (`category`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '文章表 posts 就绪' : ('文章表创建失败：' . $conn->error);
if (!$ok) { $errors++; }
// 旧库升级：补 views 列
if (!fly_column_exists($conn, 'posts', 'views')) {
    $ok = $conn->query("ALTER TABLE `posts` ADD COLUMN `views` INT UNSIGNED NOT NULL DEFAULT 0");
    $steps[] = $ok ? '旧库升级：posts 表已补齐 views 列' : ('补齐 views 列失败：' . $conn->error);
    if (!$ok) { $errors++; }
}

// 第 4 步：留言表（V1.1 增加 status 审核列，0=待审核 1=已通过）
// 注意：列默认值为 1——旧库补列时历史留言自动视为“已通过”，不会凭空消失
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `comments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `post_id` INT UNSIGNED NOT NULL,
        `author` VARCHAR(100) NOT NULL DEFAULT '',
        `content` TEXT,
        `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_post` (`post_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '留言表 comments 就绪' : ('留言表创建失败：' . $conn->error);
if (!$ok) { $errors++; }
// 旧库升级：补 status 列（历史留言默认放行）
if (!fly_column_exists($conn, 'comments', 'status')) {
    $ok = $conn->query("ALTER TABLE `comments` ADD COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT 1");
    $steps[] = $ok ? '旧库升级：comments 表已补齐 status 列（历史留言默认已通过）' : ('补齐 status 列失败：' . $conn->error);
    if (!$ok) { $errors++; }
}

// 第 5 步：管理员表
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '管理员表 admins 就绪' : ('管理员表创建失败：' . $conn->error);
if (!$ok) { $errors++; }

// 第 6 步：登录防爆破表（V1.1 新增，按 IP 记录失败次数与锁定时长）
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `login_attempts` (
        `ip` VARCHAR(45) NOT NULL,
        `fails` INT UNSIGNED NOT NULL DEFAULT 0,
        `lock_until` DATETIME NULL DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '登录防爆破表 login_attempts 就绪' : ('login_attempts 创建失败：' . $conn->error);
if (!$ok) { $errors++; }

// 第 7 步：登录日志表（V1.1 新增，记录每次登录尝试）
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `admin_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL DEFAULT '',
        `ip` VARCHAR(45) NOT NULL DEFAULT '',
        `success` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '登录日志表 admin_logs 就绪' : ('admin_logs 创建失败：' . $conn->error);
if (!$ok) { $errors++; }

// 第 8 步：管理员账号（注册）
// GET 访问：写入默认管理员 admin / 123456（已存在则跳过）
// POST 访问：使用表单里的自定义账号密码（带校验），实现“安装即注册”
$admin_user = 'admin';
$admin_pass = '123456';
$form_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user = trim((string)($_POST['admin_user'] ?? ''));
    $admin_pass = (string)($_POST['admin_pass'] ?? '');
    $admin_pass2 = (string)($_POST['admin_pass2'] ?? '');
    if ($admin_user === '' || $admin_pass === '') {
        $form_err = '账号和密码不能为空（留空请直接点“一键安装”使用默认账号）。';
    } elseif (function_exists('mb_strlen') ? (mb_strlen($admin_user, 'UTF-8') > 100 || mb_strlen($admin_pass, 'UTF-8') > 100)
        : (strlen($admin_user) > 100 || strlen($admin_pass) > 100)) {
        $form_err = '账号和密码长度不能超过 100 字。';
    } elseif (strlen($admin_pass) < 8) {
        $form_err = '密码至少 8 位，请设置一个更安全的密码。';
    } elseif ($admin_pass !== $admin_pass2) {
        $form_err = '两次输入的密码不一致。';
    }
}

if ($form_err !== '') {
    $errors++;
    $steps[] = '管理员注册被拒绝：' . $form_err;
} else {
    $u = mysqli_real_escape_string($conn, $admin_user);
    $chk = $conn->query("SELECT `id` FROM `admins` WHERE `username` = '$u' LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        $steps[] = '管理员 ' . $admin_user . ' 已存在，跳过插入（如需改密码请登录后台「修改密码」）';
    } else {
        $hash = mysqli_real_escape_string($conn, password_hash($admin_pass, PASSWORD_DEFAULT));
        $ok = $conn->query("INSERT INTO `admins` (`username`, `password`) VALUES ('$u', '$hash')");
        $steps[] = $ok
            ? ('管理员注册成功：' . $admin_user . ' / ' . ($_SERVER['REQUEST_METHOD'] === 'POST' ? '（你设置的密码）' : '123456'))
            : ('插入管理员失败：' . $conn->error);
        if (!$ok) { $errors++; }
    }
}

$steps_html = '';
foreach ($steps as $s) {
    $steps_html .= '<li>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</li>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装 - FlyLog</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
.wrap{max-width:680px;margin:0 auto;padding:40px 20px}
h1{font-size:24px;color:#222;margin-bottom:18px}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-ok{background:#f2f9f2;border:1px solid #cde6cd;color:#2a7a2a}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
ol.steps{background:#fafafa;border:1px solid #eee;border-radius:6px;padding:14px 14px 14px 36px;font-size:14px;color:#444}
ol.steps li{margin:4px 0}
.links{margin:20px 0 12px}
.links .btn{margin-right:10px}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:#004a99;color:#fff}
.btn-gray{background:#888}
.btn-gray:hover{background:#666}
.tip{color:#888;font-size:13px;line-height:1.9}
.reg-box{background:#fafafa;border:1px solid #eee;border-radius:6px;padding:16px 18px;margin-bottom:18px}
.reg-box h2{font-size:16px;color:#222;margin-bottom:4px}
.reg-box .sub{color:#999;font-size:13px;margin-bottom:10px}
.reg-box label{display:block;font-size:13.5px;color:#555;margin:10px 0 4px;font-weight:600}
.reg-box input{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit;background:#fff;color:#333}
.reg-box input:focus{outline:none;border-color:#06c}
@media (max-width:768px){.wrap{padding:24px 14px}}
</style>
</head>
<body>
<div class="wrap">
  <h1>FlyLog 安装 / 升级</h1>
  <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
  <div class="reg-box">
    <h2>注册管理员账号（可选）</h2>
    <p class="sub">想用自己的账号密码？填下面这项再安装。留空则直接点底部「一键安装」，使用默认 admin / 123456。</p>
    <form method="post" action="install.php">
      <label for="admin_user">管理员账号</label>
      <input id="admin_user" name="admin_user" maxlength="100" placeholder="admin">
      <label for="admin_pass">密码（至少 8 位）</label>
      <input id="admin_pass" name="admin_pass" type="password" maxlength="100" placeholder="••••••••">
      <label for="admin_pass2">确认密码</label>
      <input id="admin_pass2" name="admin_pass2" type="password" maxlength="100" placeholder="再输一遍">
      <p class="links"><button class="btn" type="submit">注册并安装</button></p>
    </form>
  </div>
  <?php endif; ?>
  <div class="msg <?= $errors ? 'msg-err' : 'msg-ok' ?>">
    <?= $errors ? '安装过程中出现错误，请查看下方明细。' : '安装成功！' ?>
  </div>
  <ol class="steps"><?= $steps_html ?></ol>
  <?php if (!$errors): ?>
  <p class="links">
    <a class="btn" href="index.php">访问博客首页</a>
    <a class="btn btn-gray" href="admin/login.php">进入后台登录</a>
  </p>
  <p class="tip">
    管理员账号：<b><?= htmlspecialchars($admin_user, ENT_QUOTES, 'UTF-8') ?></b>（<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? '你注册时设置的密码' : '默认密码 123456' ?>），登录后请妥善保管。<br>
    旧版数据库再次运行本文件即可升级到 V1.1（自动补表补列，不丢数据）。<br>
    安全建议：确认安装无误后，删除 install.php。
  </p>
  <?php endif; ?>
</div>
</body>
</html>
