<?php
/**
 * Plugin Name: 社交链接
 * Version: 1.0.0
 * Description: 页脚展示 GitHub / B站 / 邮箱等社交链接
 * Author: FlyLog
 * Category: 外观
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('social-links', 'enable', '1') !== '1') { return; }
    $raw = (string)fly_plugin_opt('social-links', 'items', '');
    if ($raw === '') { return; }
    $html = '<p style="text-align:center;margin-top:10px;display:flex;gap:12px;justify-content:center;font-size:13px">';
    foreach (preg_split('/\r\n|\n/', $raw) as $line) {
        if (strpos($line, '|') === false) { continue; }
        list($t, $u) = explode('|', $line, 2);
        $html .= '<a href="' . fly_e(trim($u)) . '" target="_blank" rel="noopener">' . fly_e(trim($t)) . '</a>';
    }
    echo $html . '</p>';
});
