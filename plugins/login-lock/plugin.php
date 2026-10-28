<?php
/**
 * Plugin Name: 登录失败锁定
 * Version: 1.0.0
 * Description: 同一 IP 连续错 5 次锁 1 小时（核心已内置，本插件补提示样式）
 * Author: FlyLog
 * Category: 安全
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('login-lock', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.msg-err{background:#fdecec;color:#b91c1c;border:1px solid #f5c2c2;border-radius:8px;padding:8px 12px;font-size:13.5px}
FLYCSS . '</style>';
});
