<?php
/**
 * Plugin Name: 发布时间微调
 * Version: 1.0.0
 * Description: 保存文章时支持把发布时间改成任意日期（补发旧文）
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_filter('pre_post_save', function ($data, $id) {
    if (fly_plugin_opt('publish-time-adjust', 'enable', '1') !== '1') { return $data; }
    if (isset($_POST['custom_publish_at']) && $_POST['custom_publish_at'] !== '') {
        $data['created_at'] = (string)$_POST['custom_publish_at'];
    }
    return $data;
}, 10);
