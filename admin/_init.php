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

/**
 * V6.0 #9(P1) 会话有效性回查
 * 旧实现只看会话里有没有 fly_admin_id：管理员被删除后，旧会话仍能继续操作后台。
 * 这里每次请求都回查 admins 表，账号不存在即销毁会话；同时把库里的角色写回会话，
 * 避免会话里的 role 被篡改为 admin 后长期生效。
 */
function fly_admin_current()
{
    static $row = null;
    if ($row !== null) {
        return $row;
    }
    $id = isset($_SESSION['fly_admin_id']) ? (int)$_SESSION['fly_admin_id'] : 0;
    if ($id <= 0) {
        return $row = false;
    }
    try {
        $r = fly_db()->one('SELECT id, username, role FROM admins WHERE id = ?', array($id));
    } catch (Exception $e) {
        // 库不可用时放行：避免整个后台因一次查询失败而全瘫，但角色降级为会话值
        return $row = false;
    }
    if (!$r) {
        $_SESSION = array();
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        fly_redirect('login.php?gone=1');
    }
    if (isset($r['role']) && $r['role'] !== '') {
        $_SESSION['fly_admin_role'] = $r['role'];
    }
    return $row = $r;
}

// 每个后台页面都执行回查
fly_admin_current();

/** 当前角色（优先取库里的权威值） */
function fly_admin_role()
{
    $u = fly_admin_current();
    if ($u && isset($u['role']) && $u['role'] !== '') {
        return $u['role'];
    }
    return isset($_SESSION['fly_admin_role']) ? $_SESSION['fly_admin_role'] : 'editor';
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
        'trash'        => array('回收站', 'trash.php', 'manage_posts', ''),   // V7.0 第二批 软删除回收站
        'categories'   => array('分类', 'categories.php', '', ''),   // V7.0 分类树
        'tags'         => array('标签', 'tags.php', '', ''),          // V7.0 标签
        'pages'        => array('单页', 'pages.php', '', ''),          // V7.0 独立页面
        'comments'     => array('留言', 'comments.php', '', $pending ? '<span class="badge">' . $pending . '</span>' : ''),
        'nav'          => array('导航菜单', 'nav.php', 'manage_settings', ''),
        'links'        => array('友情链接', 'links.php', 'manage_settings', ''),   // V9.0 友情链接管理
        'logs'         => array('登录日志', 'logs.php', 'view_logs', ''),
        'audit'        => array('审计日志', 'audit.php', 'view_logs', ''),   // V7.0 第二批 操作审计
        'health'       => array('系统健康', 'health.php', 'view_logs', ''),   // V7.0 第二批 系统健康页
        'settings'     => array('设置', 'settings.php', 'manage_settings', ''),
        'backup'       => array('备份', 'backup.php', 'manage_backup', ''),
        'attachments'  => array('附件', 'attachments.php', 'manage_backup', ''),
        'plugins'      => array('插件', 'plugins.php', 'manage_plugins', ''),
        'themes'       => array('主题', 'themes.php', 'manage_settings', ''),   // V10.0 原生主题引擎
        'password'     => array('修改密码', 'password.php', '', ''),
    );
    // ===== V10.0 插件 Hook：fly_admin_menu（后台菜单生成时，可增删改） =====
    // 插件加菜单示例：
    //   fly_add_filter('fly_admin_menu', function ($nav) {
    //       $nav['my_plugin'] = array('我的插件', '../plugins/my_plugin/admin.php', 'manage_settings', '');
    //       return $nav;
    //   });
    $nav = fly_apply_filters('fly_admin_menu', $nav);
    // ===== V10.0 插件 Hook：fly_admin_head（后台每页顶部，可注入 CSS/JS） =====
    fly_do_action('fly_admin_head', $title, $active);
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
