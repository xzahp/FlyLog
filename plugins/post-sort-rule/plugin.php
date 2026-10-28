<?php
/**
 * Plugin Name: 文章排序规则
 * Version: 1.0.0
 * Description: 首页按时间/阅读量/点赞数排序
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_init', function () {
    if (fly_plugin_opt('post-sort-rule', 'enable', '0') !== '1') { return; }
    $rule = (string)fly_plugin_opt('post-sort-rule', 'rule', 'time');
    if ($rule !== 'time' && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php') {
        $_GET['sort'] = $rule;
    }
});
