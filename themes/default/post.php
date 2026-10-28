<?php
/**
 * 主题文章页：同样复用核心 parts/post_body.php（正文+目录+点赞收藏+评论），
 * 主题只换外壳与样式。V9.0 的全部交互功能一个不少。
 *
 * 变量来自根 post.php：$meta、$post、$id、$notPublished、$pinBadge、
 * $draftBadge、$catLine、$readMin、$tocData、$tagLine、$liked、
 * $commentCount、$clist、$err
 */
echo fly_render('parts/head', compact('meta'));
include __DIR__ . '/header.php';
echo fly_render('parts/post_body', array(
    'post' => $post, 'id' => $id, 'notPublished' => $notPublished,
    'pinBadge' => $pinBadge, 'draftBadge' => $draftBadge, 'catLine' => $catLine,
    'readMin' => $readMin, 'tocData' => $tocData, 'tagLine' => $tagLine,
    'liked' => $liked, 'commentCount' => $commentCount, 'clist' => $clist, 'err' => $err,
));
include __DIR__ . '/footer.php';
