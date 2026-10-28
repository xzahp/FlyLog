<?php
/**
 * FlyLog 前台文章详情页 + 留言板
 * 伪静态地址 /post/123.html 由 .htaccess 转换为 post.php?id=123
 * V1.01：留言重定向加开放式重定向防护；留言/昵称增加服务端长度校验
 */
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$err = '';

// ---------- 留言提交 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_GET['id'] ?? 0);
    $author  = mysqli_real_escape_string($conn, trim($_POST['author'] ?? ''));
    $content = mysqli_real_escape_string($conn, trim($_POST['content'] ?? ''));
    if ($post_id <= 0) {
        $err = '文章不存在，无法留言。';
    } elseif ($author === '' || $content === '') {
        $err = '昵称和留言内容不能为空。';
    } elseif (fly_len($author) > 100 || fly_len($content) > 2000) {
        // V1.01：服务端长度校验（HTML maxlength 可被绕过）
        $err = '昵称不超过 100 字，留言内容不超过 2000 字。';
    } else {
        $conn->query("INSERT INTO `comments` (`post_id`, `author`, `content`) VALUES ($post_id, '$author', '$content')");
        // POST 后重定向到当前地址，防止刷新重复提交
        // V1.01 安全加固：REQUEST_URI 可能被伪造成外站地址（开放式重定向），仅放行站内路径
        $redir = (string)($_SERVER['REQUEST_URI'] ?? 'index.php');
        if ($redir === '' || $redir[0] !== '/' || strpos($redir, '//') !== false
            || strpos($redir, '\\') !== false || stripos($redir, '://') !== false) {
            $redir = 'index.php';
        }
        header('Location: ' . $redir);
        exit;
    }
    $id = $post_id;
}

// ---------- 读取文章 ----------
$res = $conn->query("SELECT `id`, `title`, `content`, `category`, `created_at` FROM `posts` WHERE `id` = $id LIMIT 1");
$post = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;

$css = '<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:920px;margin:0 auto;padding:24px 20px 40px}
.site-head{border-bottom:2px solid #eee;padding:18px 0;margin-bottom:26px;display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px}
.site-title{font-size:26px;font-weight:700;color:#222;letter-spacing:.5px}
.site-title:hover{color:#06c}
.site-sub{color:#999;font-size:13px}
.layout{display:flex;gap:36px;align-items:flex-start}
.main-col{flex:1;min-width:0}
.side-col{width:250px;flex-shrink:0}
.side-box{margin-bottom:26px}
.side-box h3{font-size:15px;color:#222;padding-bottom:8px;border-bottom:2px solid #f0f0f0;margin-bottom:8px}
.side-list{list-style:none}
.side-list li{padding:5px 0;font-size:13.5px;border-bottom:1px dashed #f3f3f3}
.side-list li a{color:#444}
.side-list li a:hover{color:#06c}
.side-count{color:#bbb;font-size:12px}
.side-empty{color:#bbb}
.post-full{padding:4px 0 0}
.post-full h1{font-size:24px;line-height:1.4;margin-bottom:10px;color:#222;font-weight:700}
.meta{color:#999;font-size:12.5px;margin:6px 0}
.post-body{font-size:15px;color:#333}
.post-body p{margin:10px 0}
.summary{color:#555;font-size:14px}
.empty{color:#888;padding:30px 0;text-align:center}
.back-link{display:inline-block;margin:26px 0 0;font-size:14px}
.comments{margin-top:42px}
.comments h3{font-size:17px;color:#222;border-bottom:2px solid #f0f0f0;padding-bottom:8px;margin-bottom:6px}
.c-item{padding:12px 0;border-bottom:1px solid #f5f5f5;font-size:14px}
.c-meta{color:#999;font-size:12.5px;margin-bottom:4px}
.c-meta b{color:#555;font-weight:600}
.c-body{color:#444;word-break:break-word;white-space:pre-wrap}
.c-form{margin-top:22px}
.c-form label{display:block;font-size:13.5px;color:#555;margin:12px 0 4px}
.c-form input,.c-form textarea{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit;background:#fff;color:#333}
.c-form input:focus,.c-form textarea:focus{outline:none;border-color:#06c}
.c-form textarea{min-height:110px;resize:vertical}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center}
.btn:hover{background:#004a99;color:#fff}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.site-foot{margin-top:44px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.layout{flex-direction:column;gap:26px}.side-col{width:100%}.wrap{padding:16px 14px 30px}.site-title{font-size:21px}.post-full h1{font-size:20px}}
</style>';

if ($post) {
    // ---------- 留言列表 ----------
    $comments = array();
    $cres = $conn->query("SELECT `author`, `content`, `created_at` FROM `comments` WHERE `post_id` = $id ORDER BY `id` ASC");
    if ($cres) {
        while ($c = $cres->fetch_assoc()) {
            $comments[] = $c;
        }
    }

    $cat_line = ($post['category'] !== '') ? ' · ' . fly_e($post['category']) : '';
    // 正文为管理员书写，保留 HTML；其余输出一律转义
    $inner = '<article class="post-full">'
        . '<h1>' . fly_e($post['title']) . '</h1>'
        . '<p class="meta">' . fly_e($post['created_at']) . $cat_line . '</p>'
        . '<div class="post-body">' . $post['content'] . '</div>'
        . '<a class="back-link" href="index.php">&laquo; 返回首页</a>'
        . '</article>';

    $clist = '';
    foreach ($comments as $c) {
        $clist .= '<div class="c-item">'
            . '<p class="c-meta"><b>' . fly_e($c['author']) . '</b> · ' . fly_e($c['created_at']) . '</p>'
            . '<p class="c-body">' . fly_e($c['content']) . '</p>'
            . '</div>';
    }
    if ($clist === '') {
        $clist = '<p class="empty">还没有留言，来抢沙发~</p>';
    }

    $err_html = ($err !== '') ? '<div class="msg msg-err">' . fly_e($err) . '</div>' : '';

    $inner .= '<section class="comments">'
        . '<h3>留言板（' . count($comments) . '）</h3>'
        . $clist
        . $err_html
        . '<form class="c-form" method="post" action="post/' . $id . '.html">'
        . '<label for="author">昵称</label>'
        . '<input id="author" name="author" maxlength="100" value="' . fly_e($_POST['author'] ?? '') . '" placeholder="怎么称呼你？">'
        . '<label for="content">留言内容</label>'
        . '<textarea id="content" name="content" maxlength="2000" placeholder="说点什么吧……"></textarea>'
        . '<button class="btn" type="submit">提交留言</button>'
        . '</form></section>';

    $page_title = fly_e($post['title']) . ' - FlyLog';
} else {
    $inner = '<div class="post-full"><h1>文章不存在</h1>'
        . '<p class="summary">这篇文章可能已被删除，或者链接有误。</p>'
        . '<a class="back-link" href="index.php">&laquo; 返回首页</a></div>';
    $page_title = '文章不存在 - FlyLog';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<?= $css ?>
</head>
<body>
<div class="wrap">
  <header class="site-head">
    <a class="site-title" href="index.php">FlyLog</a>
    <span class="site-sub"><a href="index.php">首页</a> · <a href="admin/login.php">后台</a></span>
  </header>
  <div class="layout">
    <div class="main-col"><?= $inner ?></div>
    <?php include __DIR__ . '/sidebar.php'; ?>
  </div>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
