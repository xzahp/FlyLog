<?php
/**
 * FlyLog V2.0 命令行工具（简化版 CLI）
 * 用法：php cli.php <命令>
 *   static:build   生成整站静态快照（静态模式用，数据库连接可降为 0）
 *   cache:clear    清空文件缓存
 *   db:info        显示当前数据库信息与各表行数
 *   attachments:gc 清理孤儿附件（磁盘有文件但数据库无记录；--dry-run 只列不删）
 *   asset:minify   生成压缩版 CSS（assets/app.min.css，前端自动优先加载）
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

    case 'attachments:gc':
        // #59 孤儿附件清理：uploads 目录里有、attachments 表无记录的文件
        $opts = array_slice($argv, 2);
        $dry = in_array('--dry-run', $opts, true);
        $force = in_array('--force', $opts, true);
        $dir = FLY_DATA . '/uploads';
        if (!is_dir($dir)) {
            echo "[FAIL] 上传目录不存在：$dir\n";
            break;
        }
        $known = array();
        $dbOk = true;
        try {
            foreach (fly_db()->all('SELECT path FROM attachments') as $r) {
                $p = trim((string)$r['path']);
                if ($p === '') { continue; }
                // V6.0 #4(P1) 同时登记纯文件名与规范化路径，避免不同目录同名 / URL 与相对路径混存造成误判
                $known[basename($p)] = true;
                $known[str_replace('\\', '/', ltrim($p, '/'))] = true;
            }
        } catch (Exception $e) {
            $dbOk = false;
            echo "[WARN] 读取 attachments 表失败：" . $e->getMessage() . "\n";
        }
        if (!$dbOk) {
            // V6.0 #5(P1) 原实现空 catch 后 $known 为空 → 整个 uploads 被判为孤儿 → 非 dry-run 直接 unlink 全目录
            echo "[ABORT] 附件表不可用，为保护数据已中止，未删除任何文件。\n";
            echo "        请先修复数据库，再运行 `cli.php attachments:gc --dry-run` 试跑。\n";
            break;
        }
        $orphans = array();
        foreach (glob($dir . '/*') as $f) {
            if (!is_file($f)) { continue; }
            $base = basename($f);
            // 跳过安全守卫与隐藏文件（index.php/.htaccess 是 bootstrap 自动生成的防护，不是附件）
            if ($base === 'index.php' || $base === '.htaccess' || $base[0] === '.') { continue; }
            $rel = 'uploads/' . $base;
            if (!isset($known[$base]) && !isset($known[$rel])) { $orphans[] = $f; }
        }
        if (!$orphans) {
            echo "[OK] 没有孤儿附件（共 " . (int)(count($known) / 2) . " 个已登记）。\n";
        } elseif ($dry) {
            echo "[DRY-RUN] 发现 " . count($orphans) . " 个孤儿附件（未删除）：\n";
            foreach ($orphans as $f) { echo "  - " . basename($f) . " (" . filesize($f) . " B)\n"; }
        } elseif (!$force) {
            // V6.0 #6(P1) 删除是不可逆操作，必须显式确认，避免手滑清空站点附件
            echo "[ABORT] 发现 " . count($orphans) . " 个孤儿附件。删除不可逆，请先 --dry-run 核对，\n";
            echo "        确认无误后加 --force 执行：cli.php attachments:gc --force\n";
        } else {
            // V6.0 #7(P2) 原实现在 unlink 之后才 filesize，文件已不存在会报 warning 且总大小算成 0
            $bytes = 0;
            foreach ($orphans as $f) { $bytes += (int)filesize($f); }
            $n = 0;
            foreach ($orphans as $f) { if (is_file($f) && @unlink($f)) { $n++; } }
            echo "[OK] 已清理 $n 个孤儿附件（释放 $bytes B）。\n";
        }
        break;

    case 'asset:minify':
        // #53 生成压缩版 CSS（head 检测到 app.min.css 会优先加载）
        $src = FLY_ROOT . '/assets/app.css';
        if (!is_file($src)) {
            echo "[FAIL] 找不到 assets/app.css\n";
            break;
        }
        $min = fly_minify_css(file_get_contents($src));
        $dst = FLY_ROOT . '/assets/app.min.css';
        if (file_put_contents($dst, $min) !== false) {
            echo "[OK] 已生成 assets/app.min.css（" . strlen($min) . " B，原 " . filesize($src) . " B，省 " . round((1 - strlen($min) / filesize($src)) * 100, 1) . "%）\n";
        } else {
            echo "[FAIL] 写入 app.min.css 失败\n";
        }
        break;

    case 'db:info':
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
        echo "驱动：$driver\n";
        echo "PHP：" . PHP_VERSION . " · OpCache：" . (function_exists('opcache_get_status') && @opcache_get_status(false) ? '已启用' : '未启用') . "\n";
        echo "memory_limit：" . ini_get('memory_limit') . "\n";
        if ($driver === 'sqlite') {
            echo "文件：" . (defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (FLY_DATA . '/flylog.sqlite')) . "\n";
        } else {
            echo "主机：" . DB_HOST . ':' . DB_PORT . " 库：" . DB_NAME . " 用户：" . DB_USER . "\n";
        }
        foreach (array('posts', 'comments', 'admins', 'members', 'options', 'attachments', 'admin_logs', 'login_attempts') as $t) {
            try { echo "  $t: " . (int)fly_db()->value("SELECT COUNT(*) FROM " . $t) . " 行\n"; } catch (Throwable $e) { echo "  $t: (无)\n"; }
        }
        // 索引清单（#52 验收用）
        try {
            if ($driver === 'sqlite') {
                $idx = fly_db()->all("SELECT name FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%'");
                echo "索引：" . implode(', ', array_column($idx, 'name')) . "\n";
            } elseif ($driver === 'mysql') {
                $idx = fly_db()->all("SHOW INDEX FROM posts");
                echo "posts 索引：" . implode(', ', array_unique(array_column($idx, 'Key_name'))) . "\n";
            }
        } catch (Throwable $e) { /* 忽略 */ }
        break;

    case 'help':
    default:
        echo "FlyLog V" . FLY_VERSION . " CLI\n";
        echo "  static:build   生成整站静态快照\n";
        echo "  cache:clear    清空文件缓存\n";
        echo "  db:info        数据库信息与表行数\n";
        break;
}
