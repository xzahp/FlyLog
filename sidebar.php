<?php
/**
 * 兼容入口：V2.0 起侧边栏已迁移到 templates/parts/sidebar.php（模板标签化）。
 * 保留本文件仅为兼容旧引用；直接输出侧边栏部件。
 */
require __DIR__ . '/config.php';
echo fly_render('parts/sidebar', array('q' => $q ?? ($_GET['q'] ?? '')));
