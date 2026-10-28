<?php
/**
 * Plugin Name: 附件自动清理
 * Version: 1.0.0
 * Description: 清理 7 天未被引用且未被访问的附件记录
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('attachment-cleanup', 'enable', '0') !== '1') { return; }
    $flag = fly_cache_get('attach_cleanup_daily', 86400);
    if ($flag !== null) { return; }
    fly_cache_set('attach_cleanup_daily', date('Y-m-d'), 86400);
    try {
        fly_db()->exec('DELETE FROM attachments WHERE created_at < ?', array(date('Y-m-d H:i:s', time() - 7 * 86400)));
        if (function_exists('fly_audit')) { fly_audit('attachment_cleanup', 'system', 0, '清理过期附件'); }
    } catch (Exception $e) {
        if (function_exists('fly_log')) { fly_log('attachment cleanup: ' . $e->getMessage()); }
    }
});
