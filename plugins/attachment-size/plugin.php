<?php
/**
 * Plugin Name: 附件占用空间
 * Version: 1.0.0
 * Description: 统计 uploads 目录总大小与文件数
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：uploads 目录占用统计
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_attsize'] = array('附件容量', 'plugins.php?admin_page=attachment_size', 'manage_backup', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'attachment_size') { return; }
    $dir = FLY_DATA . '/uploads';
    $size = 0; $count = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) { $size += $f->getSize(); $count++; }
        }
    }
    echo '<p class="hint">uploads 目录：' . $count . ' 个文件，占用 <b>'
       . number_format($size / 1048576, 2) . ' MB</b>。</p>';
});
