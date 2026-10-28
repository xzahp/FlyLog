<?php
/**
 * FlyLog 后台写文章 / 改文章
 * 带 id 参数时为编辑模式（读取原数据），否则为新建
 * 摘要由正文自动生成，写入 posts.summary
 * V1.01：标题/分类增加服务端长度校验；会话 cookie 加固；页脚显示版本号
 * V1.1 ：保存后跳转 posts.php（文章管理页）；返回按钮同步调整
 */
require_once __DIR__ . '/../config.php';
// V1.01：先设置 cookie 加固参数，再启动会话
session_set_cookie_params(fly_session_cookie_params());
session_start();

if (!isset($_SESSION['fly_admin_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$title = '';
$category = '';
$content = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- 保存 ----------
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $err = '标题和正文不能为空。';
    } elseif (fly_len($title) > 255 || fly_len($category) > 100) {
        // V1.01：服务端长度校验（HTML maxlength 可被绕过）
        $err = '标题不超过 255 字，分类不超过 100 字。';
    } else {
        $t = mysqli_real_escape_string($conn, $title);
        $c = mysqli_real_escape_string($conn, $category);
        $body = mysqli_real_escape_string($conn, $content);
        $s = mysqli_real_escape_string($conn, fly_excerpt($content, 120)); // 自动生成摘要

        if ($id > 0) {
            $conn->query("UPDATE `posts` SET `title` = '$t', `summary` = '$s', `category` = '$c', `content` = '$body' WHERE `id` = $id");
        } else {
            $conn->query("INSERT INTO `posts` (`title`, `summary`, `category`, `content`) VALUES ('$t', '$s', '$c', '$body')");
            $id = $conn->insert_id;
        }
        header('Location: posts.php?saved=1');
        exit;
    }
} elseif ($id > 0) {
    // ---------- 编辑模式：读取原数据 ----------
    $r = $conn->query("SELECT `title`, `category`, `content` FROM `posts` WHERE `id` = $id LIMIT 1");
    if ($r && $r->num_rows === 1) {
        $row = $r->fetch_assoc();
        $title = $row['title'];
        $category = $row['category'];
        $content = $row['content'];
    } else {
        $err = '文章不存在或已被删除。';
        $id = 0;
    }
}

$mode_title = ($id > 0) ? ('编辑文章 #' . $id) : '写新文章';
$form_action = ($id > 0) ? ('post.php?id=' . $id) : 'post.php';
$err_html = ($err !== '') ? '<div class="msg msg-err">' . fly_e($err) . '</div>' : '';
$view_link = ($id > 0) ? '<a class="btn btn-gray" href="../post.php?id=' . $id . '" target="_blank">查看原文</a>' : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $mode_title ?> - FlyLog 后台</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#fff;color:#333;line-height:1.6;font-size:15px}
a{color:#06c;text-decoration:none}
a:hover{color:#004a99}
.wrap{max-width:860px;margin:0 auto;padding:24px 20px 40px}
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:18px}
.topbar .t{font-size:20px;font-weight:700;color:#222}
.topbar .ops{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-block;padding:8px 22px;background:#06c;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:#004a99;color:#fff}
.btn-gray{background:#888}
.btn-gray:hover{background:#666}
.msg{padding:10px 14px;border-radius:4px;font-size:14px;margin-bottom:16px}
.msg-err{background:#fdf2f2;border:1px solid #f0cccc;color:#a33}
.form-box{max-width:720px}
.form-box label{display:block;font-size:13.5px;color:#555;margin:14px 0 5px;font-weight:600}
.form-box input,.form-box textarea{width:100%;padding:9px 11px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:inherit;background:#fff;color:#333}
.form-box input:focus,.form-box textarea:focus{outline:none;border-color:#06c}
.form-box textarea{min-height:320px;resize:vertical;line-height:1.7}
.tip{color:#999;font-size:12.5px;margin-top:4px}
.form-ops{margin-top:22px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.site-foot{margin-top:40px;padding-top:16px;border-top:1px solid #f0f0f0;color:#bbb;font-size:12.5px;text-align:center}
@media (max-width:768px){.wrap{padding:16px 14px 30px}.topbar .t{font-size:18px}.form-box textarea{min-height:240px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <span class="t"><?= $mode_title ?></span>
    <div class="ops">
      <?= $view_link ?>
      <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
    </div>
  </div>
  <?= $err_html ?>
  <form class="form-box" method="post" action="<?= $form_action ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label for="title">标题</label>
    <input id="title" name="title" maxlength="255" value="<?= fly_e($title) ?>" placeholder="文章标题" required>
    <label for="category">分类</label>
    <input id="category" name="category" maxlength="100" value="<?= fly_e($category) ?>" placeholder="如：航拍技巧 / 编程笔记（可留空）">
    <label for="content">正文</label>
    <textarea id="content" name="content" placeholder="支持 HTML 标签，摘要会自动从前 120 字生成" required><?= fly_e($content) ?></textarea>
    <p class="tip">发布后可通过伪静态地址 /post/<?= $id > 0 ? $id : '新文章ID' ?>.html 访问。</p>
    <div class="form-ops">
      <button class="btn" type="submit">保存</button>
      <a class="btn btn-gray" href="posts.php">取消</a>
    </div>
  </form>
  <footer class="site-foot">Powered by FlyLog V<?= FLYLOG_VERSION ?> · 纯原生 PHP + MySQL · 致敬 Z-Blog</footer>
</div>
</body>
</html>
