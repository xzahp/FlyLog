<?php
/**
 * Plugin Name: 数据库大小
 * Version: 1.0.0
 * Description: 显示当前数据库文件大小
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：数据库文件大小（SQLite 直接看文件；其它库提示用备份功能）
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_dbsize'] = array('数据库大小', 'plugins.php?admin_page=db_size', 'manage_backup', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'db_size') { return; }
    $cfg = fly_config('db', array());
    $driver = (string)($cfg['driver'] ?? '');
    if ($driver === 'sqlite' && !empty($cfg['path'])) {
        $p = $cfg['path'];
        if (!preg_match('#^/#', $p) && strpos($p, ':') === false) { $p = FLY_ROOT . '/' . ltrim($p, '/'); }
        echo '<p class="hint">SQLite 文件：<code>' . fly_e($p) . '</code>，大小 <b>'
           . (is_file($p) ? number_format(filesize($p) / 1048576, 2) . ' MB' : '未知') . '</b></p>';
    } else {
        echo '<p class="hint">当前使用 ' . fly_e($driver) . ' 数据库，大小请在「备份」页导出查看。</p>';
    }
});
