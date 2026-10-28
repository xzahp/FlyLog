<?php
/**
 * Plugin Name: 自定义 TDK
 * Version: 1.0.0
 * Description: 每篇文章单独设置标题/描述/关键词（V9.0 核心已支持，本插件提供默认值填充）
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 保存文章时：标题为空则不用管，关键词为空则用站点默认关键词补齐
fly_add_filter('pre_post_save', function ($data, $id) {
    if (fly_plugin_opt('custom-tdk', 'enable', '1') !== '1') { return $data; }
    if (isset($data['seo_keywords']) && $data['seo_keywords'] === '') {
        $data['seo_keywords'] = (string)fly_config('site.keywords', '');
    }
    return $data;
}, 10);
