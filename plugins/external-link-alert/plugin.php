<?php
/**
 * Plugin Name: 外链弹窗提示
 * Version: 1.0.0
 * Description: 点击站外链接前弹确认框（核心已有，本插件补充样式）
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('external-link-alert', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-leave-modal{border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.35);padding:22px 24px}
.fly-leave-modal .btn{margin:0 6px}
FLYCSS . '</style>';
});
