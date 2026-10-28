<?php
/**
 * Plugin Name: 导入 Markdown
 * Version: 1.0.0
 * Description: 上传 .md 文件自动解析为文章
 * Author: FlyLog
 * Category: 极客
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：上传 .md 自动建文章
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_impmd'] = array('导入 MD', 'plugins.php?admin_page=import_markdown', 'manage_posts', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'import_markdown') { return; }
    if (!empty($_FILES['md']) && is_uploaded_file($_FILES['md']['tmp_name'])) {
        $raw = (string)@file_get_contents($_FILES['md']['tmp_name']);
        $title = trim(str_replace('#', '', strtok($raw, "\n"))) ?: '导入文章';
        $body = trim(substr($raw, strpos($raw, "\n") === false ? strlen($raw) : strpos($raw, "\n")));
        $id = BasePost::create(array('title' => $title, 'content' => nl2br(fly_e($body)), 'is_draft' => 1));
        echo '<div class="msg msg-ok" style="margin:10px 0">已导入为草稿 #' . (int)$id . '：' . fly_e($title) . '</div>';
    }
    echo '<form method="post" enctype="multipart/form-data" style="margin:12px 0">'
       . '<input type="file" name="md" accept=".md,.markdown,.txt"> '
       . '<button class="btn btn-sm" type="submit">导入为草稿</button></form>'
       . '<p class="hint">首行 # 标题，其余为正文。导入后是草稿状态，确认无误再发布。</p>';
});
