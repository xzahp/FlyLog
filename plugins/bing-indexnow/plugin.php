<?php
/**
 * Plugin Name: Bing IndexNow
 * Version: 1.0.0
 * Description: 发布文章时通知 Bing 更新索引
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('post_saved', function ($id, $data, $isNew) {
    if (!$isNew || fly_plugin_opt('bing-indexnow', 'enable', '1') !== '1') { return; }
    $key = (string)fly_plugin_opt('bing-indexnow', 'key', '');
    if ($key === '') { return; }
    $payload = json_encode(array(
        'host' => parse_url(fly_base_url(), PHP_URL_HOST),
        'key' => $key,
        'urlList' => array(rtrim(fly_base_url(), '/') . fly_url('post', array('id' => $id))),
    ));
    $ctx = stream_context_create(array('http' => array(
        'method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: application/json
",
        'content' => $payload,
    )));
    @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
}, 10);
