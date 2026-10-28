<?php
/**
 * Plugin Name: 文章字数统计
 * Version: 1.0.0
 * Description: 统计每篇文章字数与全站总字数
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：文章字数与全站总字数
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_words'] = array('字数统计', 'plugins.php?admin_page=word_count', 'manage_posts', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'word_count') { return; }
    $rows = fly_db()->all("SELECT id, title, content FROM posts WHERE is_draft = 0 AND deleted_at = 0");
    $total = 0; $list = array();
    foreach ($rows as $r) {
        $n = count(preg_split('//u', trim(strip_tags((string)$r['content'])), -1, PREG_SPLIT_NO_EMPTY));
        $total += $n; $list[] = array('t' => $r['title'], 'n' => $n);
    }
    usort($list, function ($a, $b) { return $b['n'] <=> $a['n']; });
    echo '<p class="hint">全站共 ' . count($rows) . ' 篇，累计 <b>' . number_format($total) . '</b> 字。</p>';
    echo '<table class="tbl"><thead><tr><th>文章</th><th>字数</th></tr></thead><tbody>';
    foreach (array_slice($list, 0, 20) as $it) {
        echo '<tr><td>' . fly_e($it['t']) . '</td><td>' . number_format($it['n']) . '</td></tr>';
    }
    echo '</tbody></table>';
});
