<?php
/**
 * FlyLog V2.0 后台公共初始化
 * 每个后台页面开头 require 本文件：自动登录校验 + 角色权限 + 统一 head/foot。
 */
require __DIR__ . '/../config.php';

// 未登录跳登录页
if (empty($_SESSION['fly_admin_id'])) {
    fly_redirect('login.php');
}

/** 当前角色 */
function fly_admin_role()
{
    return $_SESSION['fly_admin_role'] ?? 'editor';
}

/** 角色能力表（权限分级） */
function fly_admin_caps($role)
{
    $all = array('manage_posts', 'manage_comments', 'manage_settings', 'manage_users', 'manage_backup', 'manage_plugins', 'view_logs', 'change_password');
    if ($role === 'admin') {
        return $all;
    }
    if ($role === 'editor') {
        return array('manage_posts', 'manage_comments', 'change_password');
    }
    return array();
}
function fly_admin_can($cap)
{
    return in_array($cap, fly_admin_caps(fly_admin_role()), true);
}
function fly_admin_require($cap)
{
    if (!fly_admin_can($cap)) {
        http_response_code(403);
        die('<div style="max-width:520px;margin:60px auto;font-family:sans-serif;text-align:center">'
            . '<h2>权限不足</h2><p>当前角色（' . fly_e(fly_admin_role()) . '）无权访问此功能。</p>'
            . '<a href="index.php">返回仪表盘</a></div>');
    }
}

/** 后台统一头部 */
function fly_admin_head($title, $active = '')
{
    $db = fly_db();
    $pending = (int)$db->value("SELECT COUNT(*) FROM comments WHERE status = 0");
    $role = fly_admin_role();
    // [key => [显示名, 链接, 需要的能力（''=不限）, 角标HTML]]
    $nav = array(
        'index'        => array('仪表盘', 'index.php', '', ''),
        'posts'        => array('文章', 'posts.php', '', ''),
        'comments'     => array('留言', 'comments.php', '', $pending ? '<span class="badge">' . $pending . '</span>' : ''),
        'logs'         => array('登录日志', 'logs.php', 'view_logs', ''),
        'settings'     => array('设置', 'settings.php', 'manage_settings', ''),
        'backup'       => array('备份', 'backup.php', 'manage_backup', ''),
        'attachments'  => array('附件', 'attachments.php', 'manage_backup', ''),
        'plugins'      => array('插件', 'plugins.php', 'manage_plugins', ''),
        'password'     => array('修改密码', 'password.php', '', ''),
    );
    $links = '';
    foreach ($nav as $key => $item) {
        if ($item[2] !== '' && !fly_admin_can($item[2])) {
            continue; // 角色无权，隐藏
        }
        $cls = ($active === $key) ? ' style="color:var(--brand);font-weight:700"' : '';
        $links .= '<a href="' . fly_e($item[1]) . '"' . $cls . '>' . fly_e($item[0]) . $item[3] . '</a>';
    }
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= fly_e($title) ?> - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<link rel="stylesheet" href="<?= fly_e(fly_base_url() . 'assets/app.css') ?>">
</head>
<body>
<div class="topbar">
  <a class="t" href="index.php">🚁 FlyLog 后台</a>
  <nav>
    <?= $links ?>
    <a href="logout.php">退出</a>
  </nav>
</div>
<div class="wrap-wide">
    <?php
}

/** 后台统一底部 */
function fly_admin_foot()
{
    ?>
  <div class="foot">Powered by FlyLog V<?= fly_e(FLY_VERSION) ?> · <a href="<?= fly_e(fly_base_url()) ?>" target="_blank">查看站点</a></div>
</div>
</body>
</html>
    <?php
}
