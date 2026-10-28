<?php
/**
 * Plugin Name: 敏感词过滤
 * Version: 1.0.0
 * Description: 读取 badwords.txt，正文与评论中的违规词替换为 ***
 * Author: FlyLog
 * Category: 互动
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 挂到正文过滤点：核心输出正文前会经过这里
fly_add_filter('the_content', function ($html) {
    if (fly_plugin_opt('badwords-filter', 'enable', '1') !== '1') { return $html; }

    $file = FLY_DATA . '/badwords.txt';
    if (!is_file($file)) { return $html; }
    static $words = null;
    if ($words === null) {
        $words = array_filter(array_map('trim', preg_split('/\r\n|\n|\r|,/', (string)@file_get_contents($file))));
    }
    if (!$words) { return $html; }
    foreach ($words as $w) {
        if ($w === '') { continue; }
        $html = str_replace($w, str_repeat('*', mb_strlen($w, 'UTF-8')), $html);
    }
    return $html;
});
