<?php
/**
 * FlyLog 一键安装脚本
 * 功能：创建数据库 -> 创建 文章/评论/管理员 三张表 -> 写入默认管理员 admin / 123456
 * 用法：浏览器访问本文件，看到“安装成功”即可进入后台
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

// 第 2 步：创建数据库
$ok = $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
if ($ok) {
    $steps[] = '数据库 `' . DB_NAME . '` 创建成功（或已存在）';
} else {
    $steps[] = '创建数据库失败：' . $conn->error;
    $errors++;
}
$conn->select_db(DB_NAME);

// 第 3 步：文章表
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `posts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `summary` VARCHAR(500) NOT NULL DEFAULT '',
        `content` MEDIUMTEXT,
        `category` VARCHAR(100) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_category` (`category`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '文章表 posts 创建成功' : ('文章表创建失败：' . $conn->error);
if (!$ok) { $errors++; }

// 第 4 步：评论表
$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `comments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `post_id` INT UNSIGNED NOT NULL,
        `author` VARCHAR(100) NOT NULL DEFAULT '',
        `content` TEXT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_post` (`post_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$steps[] = $ok ? '评论表 comments 创建成功' : ('评论表创建失败：' . $conn->error);
if (!$ok) { $errors++; }

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
$steps[] = $ok ? '管理员表 admins 创建成功' : ('管理员表创建失败：' . $conn->error);
if (!$ok) { $errors++; }

// 第 6 步：默认管理员 admin / 123456（密码哈希存储）
$chk = $conn->query("SELECT `id` FROM `admins` WHERE `username` = 'admin' LIMIT 1");
if ($chk && $chk->num_rows > 0) {
    $steps[] = '默认管理员 admin 已存在，跳过插入';
} else {
    $hash = mysqli_real_escape_string($conn, password_hash('123456', PASSWORD_DEFAULT));
    $ok = $conn->query("INSERT INTO `admins` (`username`, `password`) VALUES ('admin', '$hash')");
    $steps[] = $ok ? '默认管理员插入成功：admin / 123456' : ('插入管理员失败：' . $conn->error);
    if (!$ok) { $errors++; }
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
@media (max-width:768px){.wrap{padding:24px 14px}}
</style>
</head>
<body>
<div class="wrap">
  <h1>FlyLog 安装</h1>
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
    默认管理员账号：<b>admin</b>，密码：<b>123456</b>（登录后请妥善保管）。<br>
    安全建议：确认安装无误后，删除 install.php。
  </p>
  <?php endif; ?>
</div>
</body>
</html>
