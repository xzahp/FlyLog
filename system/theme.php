<?php
/**
 * FlyLog V8.0 主题系统
 * 主题以 CSS 变量 + <html data-theme="..."> 表达，切换纯前端（localStorage 记忆），
 * 后台可设定默认主题与是否允许访客切换。四库无关，无需新表（存 options 表）。
 */
if (!defined('FLY_BOOTSTRAPPED') && !defined('FLY_CLI')) { return; }

if (!function_exists('fly_theme_list')) {
    /**
     * 可用主题清单（键 => 中文名）。
     * auto = 跟随系统 prefers-color-scheme；light/dark 固定；aurora/sunset 为品牌色主题。
     */
    function fly_theme_list() {
        return array(
            'auto'   => '跟随系统',
            'light'  => '亮色',
            'dark'   => '暗色',
            'aurora' => '极光（蓝紫）',
            'sunset' => '暖阳（橙金）',
        );
    }

    /** 默认主题（后台设定；访客未手动选择时采用）。 */
    function fly_theme_default() {
        $d = (string)fly_opt('theme_default', 'auto');
        $all = fly_theme_list();
        return isset($all[$d]) ? $d : 'auto';
    }

    /** 是否允许访客切换主题（默认允许）。 */
    function fly_theme_allow_switch() {
        return fly_feature('theme_switch', true);
    }

    /** 校验主题键是否合法。 */
    function fly_theme_is_valid($t) {
        $all = fly_theme_list();
        return isset($all[$t]);
    }
}
