<?php
/**
 * FlyLog V2.0 命令行工具（简化版 CLI）
 * 用法：php cli.php <命令>
 *   static:build   生成整站静态快照（静态模式用，数据库连接可降为 0）
 *   cache:clear    清空文件缓存
 *   db:info        显示当前数据库信息与各表行数
 *   help           显示帮助
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
define('FLY_CLI', true);
require __DIR__ . '/config.php';

/** 捕获某页渲染输出（用于静态化构建） */
function fly_cli_capture($file)
{
    ob_start();
    include __DIR__ . '/' . $file;
    return ob_get_clean();
}

$cmd = isset($argv[1]) ? $argv[1] : 'help';

switch ($cmd) {
    case 'static:build':
        define('FLY_BUILDING', true);
        $posts = BasePost::all();
        $built = 0;
        $_GET = array();
        fly_static_build('home-1.html', fly_cli_capture('index.php')); $built++;
        fly_static_build('archive.html', fly_cli_capture('archive.php')); $built++;
        fly_static_build('feed.xml', fly_cli_capture('feed.php')); $built++;
        foreach ($posts as $p) {
            $_GET = array('id' => (int)$p['id']);
            fly_static_build('post-' . (int)$p['id'] . '.html', fly_cli_capture('post.php')); $built++;
        }
        echo "[OK] 已生成 $built 个静态快照到 cachedir/static/（数据库驱动：" . (defined('DB_DRIVER') ? DB_DRIVER : 'sqlite') . "）\n";
        echo "     提示：将 config.php 的 site.mode 设为 static 即切换为静态模式。\n";
        break;

    case 'cache:clear':
        $n = fly_cache_clear();
        echo "[OK] 已清空 $n 个缓存文件。\n";
        break;

    case 'db:info':
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
        echo "驱动：$driver\n";
        if ($driver === 'sqlite') {
            echo "文件：" . (defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (FLY_DATA . '/flylog.sqlite')) . "\n";
        } else {
            echo "主机：" . DB_HOST . ':' . DB_PORT . " 库：" . DB_NAME . " 用户：" . DB_USER . "\n";
        }
        foreach (array('posts', 'comments', 'admins', 'members', 'options', 'attachments', 'admin_logs', 'login_attempts') as $t) {
            try { echo "  $t: " . (int)fly_db()->value("SELECT COUNT(*) FROM " . $t) . " 行\n"; } catch (Throwable $e) { echo "  $t: (无)\n"; }
        }
        break;

    case 'help':
    default:
        echo "FlyLog V" . FLY_VERSION . " CLI\n";
        echo "  static:build   生成整站静态快照\n";
        echo "  cache:clear    清空文件缓存\n";
        echo "  db:info        数据库信息与表行数\n";
        break;
}
