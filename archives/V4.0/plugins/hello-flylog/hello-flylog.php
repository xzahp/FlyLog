<?php
/**
 * 插件：Hello FlyLog（示例）
 * 演示 FlyLog V2.0 的 HOOK 机制：通过 add_action / add_filter 挂接点介入，
 * 无需修改任何核心文件。目录名需与主文件名一致：plugins/hello-flylog/hello-flylog.php
 */

// 在页脚挂接点追加一行（Action）
fly_add_action('fly_foot', function () {
    echo '<div style="text-align:center;color:#9ca3af;font-size:12px;padding:4px 0">🔌 由 Hello FlyLog 示例插件驱动</div>';
});

// 在 <head> 挂接点追加 meta（Action）
fly_add_action('fly_head', function () {
    echo '<meta name="generator" content="FlyLog ' . fly_e(FLY_VERSION) . ' + HelloFlyLog">' . "\n";
});

// 示例 Filter：给摘要末尾加点缀（可改写入栈值）
fly_add_filter('the_excerpt', function ($summary) {
    return $summary;
});
