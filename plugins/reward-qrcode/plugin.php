<?php
/**
 * Plugin Name: 打赏二维码
 * Version: 1.0.0
 * Description: 文章底部显示微信/支付宝收款码
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
    if (fly_plugin_opt('reward-qrcode', 'enable', '1') !== '1') { return; }
    $url = (string)fly_plugin_opt('reward-qrcode', 'url', '');
    if ($url === '') { return; }
    echo '<div style="text-align:center;margin:18px 0"><p style="font-size:13px;color:var(--text-2)">赞赏支持</p>'
       . '<img src="' . fly_e($url) . '" alt="赞赏码" style="width:150px;border-radius:8px"></div>';
});
