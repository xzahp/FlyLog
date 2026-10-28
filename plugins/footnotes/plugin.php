<?php
/**
 * Plugin Name: 脚注解析
 * Version: 1.0.0
 * Description: 把正文里的 [^1] 解析为文末脚注并支持点击回跳
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 挂到正文过滤点：核心输出正文前会经过这里
fly_add_filter('the_content', function ($html) {
    if (fly_plugin_opt('footnotes', 'enable', '1') !== '1') { return $html; }

    // 形如 [^1]: 脚注内容  —— 收集到文末，正文处变成上标链接
    $notes = array();
    $html = preg_replace_callback('/\[\^([^\]]+)\]:\s*([^\n]+)/', function ($m) use (&$notes) {
        $notes[$m[1]] = trim($m[2]);
        return '';
    }, $html);
    if ($notes) {
        $html = preg_replace_callback('/\[\^([^\]]+)\]/', function ($m) use (&$notes) {
            $k = $m[1];
            if (!isset($notes[$k])) { return $m[0]; }
            return '<sup class="fly-fn"><a href="#fn-' . rawurlencode($k) . '" id="fnref-' . rawurlencode($k) . '">[' . $k . ']</a></sup>';
        }, $html);
        $html .= '<div class="fly-footnotes"><h3>脚注</h3><ol>';
        foreach ($notes as $k => $v) {
            $kk = rawurlencode($k);
            $html .= '<li id="fn-' . $kk . '">' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8')
                   . ' <a href="#fnref-' . $kk . '">↩</a></li>';
        }
        $html .= '</ol></div>';
    }
    return $html;
});
