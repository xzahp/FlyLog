<?php
/**
 * Plugin Name: 文章过期自动下架
 * Version: 1.0.0
 * Description: 设定时间到点自动转为草稿
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
    if (fly_plugin_opt('auto-unpublish', 'enable', '0') !== '1') { return; }
    $flag = fly_cache_get('auto_unpublish_daily', 3600);
    if ($flag !== null) { return; }
    fly_cache_set('auto_unpublish_daily', '1', 3600);
    if (!fly_db_has_column('posts', 'expire_at')) { return; }
    try {
        fly_db()->exec("UPDATE posts SET is_draft = 1 WHERE is_draft = 0 AND expire_at > 0 AND expire_at <= " . time());
    } catch (Exception $e) {
        if (function_exists('fly_log')) { fly_log('auto unpublish: ' . $e->getMessage()); }
    }
});
