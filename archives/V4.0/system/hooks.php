<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 HOOK 插件机制（Filter / Action）
 * 采用事件挂接（Filter/Action）思路：核心与插件解耦，插件通过挂接点介入而不改核心代码。
 * 用法：
 *   fly_add_filter('the_content', function($c){ return $c.'<hr>'; });
 *   fly_add_action('init', function(){ ... });
 *   $out = fly_apply_filters('the_content', $content);
 *   fly_do_action('init');
 */

$GLOBALS['FLY_HOOKS'] = array('filter' => array(), 'action' => array());

/** 注册 Filter（可改写入栈值），$priority 越小越先执行 */
function fly_add_filter($tag, $fn, $priority = 10)
{
    $GLOBALS['FLY_HOOKS']['filter'][$tag][] = array('fn' => $fn, 'priority' => $priority);
}

/** 注册 Action（纯副作用，无返回值） */
function fly_add_action($tag, $fn, $priority = 10)
{
    $GLOBALS['FLY_HOOKS']['action'][$tag][] = array('fn' => $fn, 'priority' => $priority);
}

/** 按优先级排序执行队列 */
function fly_sorted_hooks($type, $tag)
{
    $list = $GLOBALS['FLY_HOOKS'][$type][$tag] ?? array();
    usort($list, function ($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
    return $list;
}

/** 应用 Filter：值依次穿过所有挂接函数 */
function fly_apply_filters($tag, $value)
{
    $args = func_get_args();
    array_shift($args); // 去掉 $tag
    $args[0] = $value;
    foreach (fly_sorted_hooks('filter', $tag) as $h) {
        $args[0] = call_user_func_array($h['fn'], $args);
    }
    return $args[0];
}

/** 触发 Action */
function fly_do_action($tag)
{
    $args = func_get_args();
    array_shift($args);
    foreach (fly_sorted_hooks('action', $tag) as $h) {
        call_user_func_array($h['fn'], $args);
    }
}

/** 加载插件目录下所有启用插件的主文件（plugins/<名>/<名>.php）
 *  启用列表优先取后台设置（options 表 plugins.enabled，逗号分隔），回退 config.php。 */
function fly_load_plugins()
{
    $dir = defined('FLY_PLUGINS') ? FLY_PLUGINS : (__DIR__ . '/../plugins');
    if (!is_dir($dir)) {
        return;
    }
    $enabledStr = (string)fly_opt('plugins.enabled', '');
    if ($enabledStr !== '') {
        $enabled = array_filter(array_map('trim', explode(',', $enabledStr)));
    } else {
        $enabled = fly_config('plugins.enabled', array());
    }
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $pd) {
        $name = basename($pd);
        if (!in_array($name, $enabled, true)) {
            continue;
        }
        $main = $pd . '/' . $name . '.php';
        if (is_file($main)) {
            require_once $main;
        }
    }
}
