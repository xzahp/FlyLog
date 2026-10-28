<?php
/**
 * Plugin Name: Git 自动提交
 * Version: 1.0.0
 * Description: 文章保存后自动 git commit（服务器装了 git 才生效）
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('post_saved', function ($id, $data, $isNew) {
    if (fly_plugin_opt('git-autocommit', 'enable', '0') !== '1') { return; }
    if (!is_dir(FLY_ROOT . '/.git')) { return; }
    $msg = ($isNew ? 'add post ' : 'update post ') . (int)$id;
    @shell_exec('cd ' . escapeshellarg(FLY_ROOT) . ' && git add -A && git commit -m ' . escapeshellarg($msg) . ' 2>&1');
}, 10);
