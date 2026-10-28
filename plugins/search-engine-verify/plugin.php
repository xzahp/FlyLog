<?php
/**
 * Plugin Name: 搜索引擎验证
 * Version: 1.0.0
 * Description: 后台填入百度/谷歌验证代码，自动输出 meta 标签
 * Author: FlyLog
 * Category: SEO
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('search-engine-verify', 'enable', '1') !== '1') { return; }
    foreach (array('baidu' => 'baidu-site-verification', 'google' => 'google-site-verification') as $k => $name) {
        $v = (string)fly_plugin_opt('search-engine-verify', $k, '');
        if ($v !== '') { echo '<meta name="' . $name . '" content="' . fly_e($v) . '">'; }
    }
});
