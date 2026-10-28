<?php
/**
 * 主题：FlyLog Default（V10.0 内置示例主题）
 * ------------------------------------------------------------------
 * 演示主题引擎的两条关键规则：
 *   1) 主题目录下的 index.php / post.php 会接管对应页面；
 *   2) 没提供的部分自动回退 templates/ —— 所以这里直接复用核心的
 *      parts/home_body.php（列表+分页+侧栏）与 parts/head.php，
 *      主题只负责「外壳」和样式，一行业务逻辑都不用重写。
 *
 * 变量全部来自根 index.php 的作用域：$meta、$items、$commentHits、
 * $q、$page、$totalPages、$urlFor、$title、$bc ……
 */
echo fly_render('parts/head', compact('meta'));
include __DIR__ . '/header.php';
echo fly_render('parts/home_body', array(
    'items'       => $items,
    'commentHits' => $commentHits,
    'q'           => $q,
    'page'        => $page,
    'totalPages'  => $totalPages,
    'urlFor'      => $urlFor,
));
include __DIR__ . '/footer.php';
