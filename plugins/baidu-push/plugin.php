<?php
/**
 * Plugin Name: 百度主动推送
 * Version: 1.0.0
 * Description: 发布文章时向百度搜索主动推送链接
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 新文章保存后推送（需要后台填写 token；没填就静默跳过）
fly_add_action('post_saved', function ($id, $data, $isNew) {
    if (!$isNew || fly_plugin_opt('baidu-push', 'enable', '1') !== '1') { return; }
    $token = (string)fly_plugin_opt('baidu-push', 'token', '');
    if ($token === '') { return; }
    $site = rtrim(fly_base_url(), '/');
    $url = 'http://data.zz.baidu.com/urls?site=' . $site . '&token=' . rawurlencode($token);
    $link = $site . fly_url('post', array('id' => $id));
    $ctx = stream_context_create(array('http' => array(
        'method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: text/plain
",
        'content' => $link,
    )));
    $res = @file_get_contents($url, false, $ctx);
    if (function_exists('fly_log') && $res !== false) { fly_log('baidu push: ' . $res, 'push'); }
}, 10);
