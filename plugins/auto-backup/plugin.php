<?php
/**
 * Plugin Name: 数据库自动备份
 * Version: 1.0.0
 * Description: 定时备份数据库，保留最近 7 份
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('auto-backup', 'enable', '0') !== '1') { return; }
    $flag = fly_cache_get('auto_backup_daily', 86400);
    if ($flag !== null) { return; }
    fly_cache_set('auto_backup_daily', date('Y-m-d'), 86400);
    if (!function_exists('fly_backup_run')) { return; }
    try {
        $dir = FLY_DATA . '/backups';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        fly_backup_run($dir);
        // 只保留最近 7 份
        $files = glob($dir . '/*.sql*');
        if ($files && count($files) > 7) {
            usort($files, function ($a, $b) { return filemtime($a) <=> filemtime($b); });
            foreach (array_slice($files, 0, count($files) - 7) as $old) { @unlink($old); }
        }
    } catch (Throwable $e) {
        if (function_exists('fly_log')) { fly_log('auto backup: ' . $e->getMessage()); }
    }
});
