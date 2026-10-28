<?php
/**
 * FlyLog 侧边栏组件
 * 显示“最新文章”和“分类目录”，被 index.php 与 post.php 复用
 * 依赖：父页面已 include config.php（$conn 可用）
 */

// 最新文章 5 篇
$latest = $conn->query("SELECT `id`, `title` FROM `posts` ORDER BY `id` DESC LIMIT 5");

// 分类目录（按文章数倒序）
$cats = $conn->query("SELECT `category`, COUNT(*) AS n FROM `posts` WHERE `category` <> '' GROUP BY `category` ORDER BY n DESC, `category` ASC");

$latest_html = '';
if ($latest) {
    while ($r = $latest->fetch_assoc()) {
        $latest_html .= '<li><a href="post/' . (int)$r['id'] . '.html">' . fly_e($r['title']) . '</a></li>';
    }
}
if ($latest_html === '') { $latest_html = '<li class="side-empty">暂无文章</li>'; }

$cats_html = '';
if ($cats) {
    while ($c = $cats->fetch_assoc()) {
        $cats_html .= '<li><a href="index.php?cat=' . urlencode($c['category']) . '">'
            . fly_e($c['category']) . '</a> <span class="side-count">(' . (int)$c['n'] . ')</span></li>';
    }
}
if ($cats_html === '') { $cats_html = '<li class="side-empty">暂无分类</li>'; }
?>
<aside class="side-col">
  <div class="side-box">
    <h3>最新文章</h3>
    <ul class="side-list"><?= $latest_html ?></ul>
  </div>
  <div class="side-box">
    <h3>分类目录</h3>
    <ul class="side-list"><?= $cats_html ?></ul>
  </div>
</aside>
