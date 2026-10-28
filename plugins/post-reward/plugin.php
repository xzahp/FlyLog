<?php
/**
 * Plugin Name: 文章打赏
 * Version: 1.0.0
 * Description: 文章底部显示赞赏二维码（后台上传图片）
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


fly_add_action('fly_foot', function () {
    if (fly_plugin_opt('post-reward', 'enable', '1') !== '1') { return; }
    $wx = (string)fly_plugin_opt('post-reward', 'wx', '');
    $ali = (string)fly_plugin_opt('post-reward', 'ali', '');
    if ($wx === '' && $ali === '') { return; }
    if (strpos((string)($_SERVER['REQUEST_URI'] ?? ''), 'post') === false) { return; }
    $html = '<div style="text-align:center;margin:22px 0" class="fly-reward"><p style="color:var(--text-2);font-size:13px">'
          . '如果这篇文章帮到你，可以请我喝杯咖啡 ☕</p>';
    if ($wx !== '') { $html .= '<img src="' . fly_e($wx) . '" alt="微信收款码" style="width:140px;margin:0 8px;border-radius:8px">'; }
    if ($ali !== '') { $html .= '<img src="' . fly_e($ali) . '" alt="支付宝收款码" style="width:140px;margin:0 8px;border-radius:8px">'; }
    $html .= '</div>';
    echo $html;
});
