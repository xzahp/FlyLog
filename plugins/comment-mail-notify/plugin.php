<?php
/**
 * Plugin Name: 评论邮件通知
 * Version: 1.0.0
 * Description: 新评论时用 mail() 通知站长
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 评论入库后发信（需在配置里填写站长邮箱；mail() 不可用时静默失败）
fly_add_action('fly_init', function () {
    if (fly_plugin_opt('comment-mail-notify', 'enable', '1') !== '1') { return; }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['content'])) { return; }
    $to = (string)fly_plugin_opt('comment-mail-notify', 'mail', '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return; }
    @mail($to, '[' . fly_config('site.name', 'FlyLog') . '] 新留言通知',
        "有人在你的站点留言：

" . (string)$_POST['content'],
        'From: no-reply@' . (parse_url(fly_base_url(), PHP_URL_HOST) ?: 'localhost'));
});
