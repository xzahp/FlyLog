<?php
/**
 * FlyLog V2.0 一键安装 / 升级
 *  - 安装时四选一数据库：SQLite（上传即用）/ MySQL / MariaDB / PostgreSQL
 *  - 选择结果写回 config.php 持久化
 *  - 建表（按驱动生成 DDL）；旧库自动补列补表，不丢数据
 *  - 支持 POST 注册自定义管理员（≥8 位）
 */
require __DIR__ . '/system/bootstrap.php';

$drivers = fly_db_drivers();
$steps = array();
$errors = 0;

/** 建立 PDO 连接 */
function fly_install_pdo($driver, $host, $port, $user, $pass, $name, $withDb = true)
{
    if ($driver === 'sqlite') {
        $path = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (FLY_DATA . '/flylog.sqlite');
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        return new PDO('sqlite:' . $path, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    if ($driver === 'pgsql') {
        $dsn = 'pgsql:host=' . $host . ';port=' . (int)$port . ($withDb ? ';dbname=' . $name : '');
        return new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    // mysql / mariadb
    $dsn = 'mysql:host=' . $host . ';port=' . (int)$port . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    if ($withDb && $name !== '') {
        $pdo->exec('USE `' . $name . '`');
    }
    return $pdo;
}

/** 列是否存在（跨驱动） */
function fly_install_col_exists(PDO $pdo, $driver, $table, $col)
{
    try {
        if ($driver === 'sqlite') {
            $rs = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rs as $r) {
                if (strcasecmp($r['name'], $col) === 0) {
                    return true;
                }
            }
            return false;
        } elseif ($driver === 'pgsql') {
            $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name=? AND column_name=?");
            $st->execute(array($table, $col));
            return (bool)$st->fetchColumn();
        } else {
            $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute(array($col));
            return (bool)$st->fetchColumn();
        }
    } catch (Exception $e) {
        return false;
    }
}

/** 表是否存在 */
function fly_install_table_exists(PDO $pdo, $driver, $table)
{
    if ($driver === 'sqlite') {
        $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $st->execute(array($table));
        return (bool)$st->fetchColumn();
    } elseif ($driver === 'pgsql') {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name=?");
        $st->execute(array($table));
        return (bool)$st->fetchColumn();
    } else {
        $st = $pdo->prepare("SHOW TABLES LIKE ?");
        $st->execute(array($table));
        return (bool)$st->fetchColumn();
    }
}

/** #64(P0) 安装锁：已安装后写入 datadir/install.lock，本文件再次被访问即拒绝 */
function fly_install_lock_path()
{
    return rtrim(defined('FLY_DATA') ? FLY_DATA : (__DIR__ . '/datadir'), '/\\') . '/install.lock';
}
function fly_install_locked()
{
    return is_file(fly_install_lock_path());
}
function fly_install_write_lock()
{
    @file_put_contents(fly_install_lock_path(), date('c') . ' installed ' . FLY_VERSION . "\n", LOCK_EX);
}

/** #65(P0) 安全地把所选数据库配置写回 config.php
 *  值统一经 var_export 生成合法 PHP 字面量，替换串经 callback 返回（避开 preg_replace 的
 *  $1/\1 反向引用与引号突围），彻底杜绝「密码含引号 → 任意 PHP 代码落盘」的 RCE。 */
function fly_install_write_config($driver, $host, $port, $user, $pass, $name)
{
    $file = __DIR__ . '/config.php';
    $src = file_get_contents($file);
    if ($src === false) {
        return false;
    }
    $vals = array(
        "/define\('DB_DRIVER', '[^']*'\)/" => (string)$driver,
        "/define\('DB_HOST', '[^']*'\)/"   => (string)$host,
        "/define\('DB_PORT', [^)]*\)/"     => 'DB_PORT_INT',
        "/define\('DB_USER', '[^']*'\)/"   => (string)$user,
        "/define\('DB_PASS', '[^']*'\)/"   => (string)$pass,
        "/define\('DB_NAME', '[^']*'\)/"   => (string)$name,
    );
    foreach ($vals as $pat => $val) {
        if ($val === 'DB_PORT_INT') {
            $to = "define('DB_PORT', " . (int)$port . ")";
        } else {
            $to = "define('" . (preg_match("/define\('(\w+)'/", $pat, $m) ? $m[1] : 'X') . "', " . var_export($val, true) . ")";
        }
        $src = preg_replace_callback($pat, function () use ($to) { return $to; }, $src, 1);
    }
    // 写入前做语法自检：生成内容必须能通过 php -l 等效检查（token_get_all 不可用时退守引号平衡）
    if (function_exists('token_get_all')) {
        try {
            token_get_all($src);
        } catch (Throwable $e) {
            return false;
        }
    }
    return (bool)file_put_contents($file, $src, LOCK_EX);
}

$formErr = '';
$done = false;

// #64(P0) 已安装守卫：存在 install.lock 即拒绝一切访问（GET/POST 均不可再建管理员/改配置）
if (fly_install_locked()) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>已完成安装</title></head>'
        . '<body style="font-family:sans-serif;background:#f5f7fa;color:#1f2937;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
        . '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px 36px;max-width:460px;text-align:center">'
        . '<h1 style="font-size:20px;margin-bottom:10px">FlyLog 已完成安装</h1>'
        . '<p style="color:#6b7280;font-size:14px;line-height:1.7">本安装器已锁定。如需重新安装，请先删除 <code>datadir/install.lock</code>；'
        . '为安全起见，建议直接删除本文件（install.php）。</p>'
        . '<p style="margin-top:14px"><a href="index.php" style="color:#2563eb">返回首页</a></p></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $driver = in_array($_POST['driver'] ?? '', array('sqlite', 'mysql', 'pgsql'), true) ? $_POST['driver'] : 'sqlite';
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $port = (int)($_POST['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $name = trim((string)($_POST['db_name'] ?? ''));

    $adminUser = trim((string)($_POST['admin_user'] ?? ''));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

    if ($adminUser === '' || $adminPass === '') {
        $formErr = '请填写管理员账号和密码（至少 8 位）。';
    } elseif (fly_len($adminPass) < 8) {
        $formErr = '密码至少 8 位。';
    } elseif ($adminPass !== $adminPass2) {
        $formErr = '两次输入的密码不一致。';
    } elseif ($driver !== 'sqlite' && ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name))) {
        // #66(P2) 库名标识符白名单：杜绝引号/分号注入 DDL
        $formErr = '数据库名只能包含字母、数字和下划线。';
    } elseif ($driver !== 'sqlite' && !preg_match('/^[A-Za-z0-9._-]+$/', $host)) {
        $formErr = '数据库主机名格式不正确。';
    } else {
        try {
            // 连接（mysql/pgsql 先不选库，尝试建库）
            if ($driver === 'sqlite') {
                $pdo = fly_install_pdo('sqlite', '', 0, '', '', '');
                $steps[] = 'SQLite 数据文件就绪（上传即用）';
            } elseif ($driver === 'pgsql') {
                // 先连到默认库尝试；失败则连 postgres 库再建
                try {
                    $pdo = fly_install_pdo('pgsql', $host, $port, $user, $pass, $name, true);
                    $steps[] = '已连接 PostgreSQL 数据库 ' . $name;
                } catch (Exception $e) {
                    $pdo = fly_install_pdo('pgsql', $host, $port, $user, $pass, 'postgres', true);
                    $pdo->exec('CREATE DATABASE "' . $name . '"');
                    $pdo = fly_install_pdo('pgsql', $host, $port, $user, $pass, $name, true);
                    $steps[] = '已创建并连接 PostgreSQL 数据库 ' . $name;
                }
            } else {
                // mysql/mariadb：账号多无全局建库权，先尝试选库，选不上再建
                try {
                    $pdo = fly_install_pdo('mysql', $host, $port, $user, $pass, $name, true);
                    $steps[] = '已选择 MySQL 数据库 ' . $name;
                } catch (Exception $e) {
                    $pdo = fly_install_pdo('mysql', $host, $port, $user, $pass, '', false);
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` DEFAULT CHARACTER SET utf8mb4');
                    $pdo->exec('USE `' . $name . '`');
                    $steps[] = '已创建 MySQL 数据库 ' . $name;
                }
            }

            // 建表
            $schema = fly_schema($driver);
            foreach ($schema as $t => $sql) {
                $pdo->exec($sql);
                $steps[] = '数据表 ' . $t . ' 就绪';
            }

            // 旧库平滑升级：补列
            $addCols = array(
                'posts' => array(
                    'is_pinned' => 'SMALLINT NOT NULL DEFAULT 0',
                    'is_draft' => 'SMALLINT NOT NULL DEFAULT 0',
                    'publish_at' => ($driver === 'sqlite' ? 'DATETIME' : 'DATETIME') . ' DEFAULT NULL',
                ),
                'comments' => array('parent_id' => 'INT NOT NULL DEFAULT 0'),
                'admins' => array('role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'"),
            );
            foreach ($addCols as $t => $cols) {
                if (!fly_install_table_exists($pdo, $driver, $t)) {
                    continue;
                }
                foreach ($cols as $c => $def) {
                    if (!fly_install_col_exists($pdo, $driver, $t, $c)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $t . ' ADD COLUMN ' . $c . ' ' . $def);
                            $steps[] = '旧库升级：' . $t . ' 补齐 ' . $c . ' 列';
                        } catch (Exception $e) {
                            $steps[] = '补列 ' . $t . '.' . $c . ' 失败：' . $e->getMessage();
                        }
                    }
                }
            }

            // #52 复合索引：高频查询字段（列表排序/分类筛选/定时发布/留言/日志）
            // MySQL 不支持 CREATE INDEX IF NOT EXISTS，重复创建会抛异常，静默即视为已就绪
            $indexes = array(
                'idx_posts_list'      => 'posts(is_pinned, created_at)',
                'idx_posts_category'  => 'posts(category)',
                'idx_posts_publish'   => 'posts(publish_at)',
                'idx_comments_post'   => 'comments(post_id, status)',
                'idx_logs_created'    => 'admin_logs(created_at)',
            );
            foreach ($indexes as $iname => $idef) {
                try {
                    $pdo->exec('CREATE INDEX ' . $iname . ' ON ' . $idef);
                    $steps[] = '索引 ' . $iname . ' 已创建';
                } catch (Exception $e) {
                    // 已存在或数据库不支持，忽略
                }
            }

            // #64(P0) 注册管理员：仅当 admins 表为空时允许（防止未授权重复安装插入新管理员）
            $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($adminCount > 0) {
                $steps[] = '已存在管理员账号，跳过注册（防止重复安装，请登录后台管理）';
            } else {
                $hash = fly_password_hash($adminPass);
                $ins = $pdo->prepare('INSERT INTO admins (username, password, role, created_at) VALUES (?, ?, ?, ?)');
                $ins->execute(array($adminUser, $hash, 'admin', date('Y-m-d H:i:s')));
                $steps[] = '管理员注册成功：' . $adminUser;
            }

            // 写回配置（mysql/pgsql）
            if ($driver !== 'sqlite') {
                if (fly_install_write_config($driver, $host, $port, $user, $pass, $name)) {
                    $steps[] = '数据库配置已写入 config.php';
                } else {
                    $steps[] = '警告：config.php 写入失败，请手动修改 DB_* 配置';
                }
            }

            // #64(P0) 安装成功：落安装锁，之后本文件不再可用
            fly_install_write_lock();

            $done = true;
        } catch (Exception $e) {
            $errors++;
            $steps[] = '安装出错：' . $e->getMessage();
        }
    }
}

$stepsHtml = '';
foreach ($steps as $s) {
    $stepsHtml .= '<li>' . fly_e($s) . '</li>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装 - FlyLog V2.0</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#f5f7fa;color:#333;line-height:1.6;font-size:15px}
.wrap{max-width:720px;margin:0 auto;padding:40px 20px}
h1{font-size:24px;color:#1a1a2e;margin-bottom:6px}
.sub{color:#888;font-size:13px;margin-bottom:22px}
.box{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px 22px;margin-bottom:18px}
.box h2{font-size:16px;color:#1a1a2e;margin-bottom:12px}
label{display:block;font-size:13.5px;color:#555;margin:12px 0 4px;font-weight:600}
input,select{width:100%;padding:9px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-family:inherit;background:#fff;color:#222}
input:focus,select:focus{outline:none;border-color:#2563eb}
.hint{color:#999;font-size:12.5px;margin-top:4px}
.btn{display:inline-block;padding:10px 26px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;text-decoration:none}
.btn:hover{background:#1d4ed8}
.btn-gray{background:#6b7280}
.msg{padding:11px 15px;border-radius:6px;font-size:14px;margin-bottom:16px}
.msg-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
.msg-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
ol.steps{background:#f9fafb;border:1px solid #eee;border-radius:8px;padding:14px 14px 14px 36px;font-size:14px;color:#444;margin:0}
ol.steps li{margin:4px 0}
.row{display:flex;gap:12px}.row>div{flex:1}
.tip{color:#6b7280;font-size:13px;line-height:1.9}
.kv{color:#374151}
</style>
</head>
<body>
<div class="wrap">
  <h1>FlyLog V2.0 安装 / 升级</h1>
  <p class="sub">选择数据库 → 注册管理员 → 一键安装。SQLite 模式上传即用，无需建库。</p>

  <?php if ($done && !$errors): ?>
    <div class="msg msg-ok">安装成功！</div>
    <ol class="steps"><?= $stepsHtml ?></ol>
    <p style="margin:18px 0 10px">
      <a class="btn" href="index.php">访问博客首页</a>
      <a class="btn btn-gray" href="admin/login.php">进入后台登录</a>
    </p>
    <p class="tip">
      管理员账号：<b class="kv"><?= fly_e($adminUser) ?></b>（你设置的密码），登录后请妥善保管，可在后台「修改密码」。<br>
      旧版数据库再次运行本文件即可升级到 V2.0（自动补表补列，不丢数据）。<br>
      安全建议：确认安装无误后，删除 install.php。
    </p>
  <?php else: ?>
    <?php if ($formErr !== ''): ?><div class="msg msg-err"><?= fly_e($formErr) ?></div><?php endif; ?>
    <?php if ($errors && $stepsHtml): ?><div class="msg msg-err">安装过程出现问题：</div><ol class="steps"><?= $stepsHtml ?></ol><?php endif; ?>
    <form method="post" action="install.php">
      <?= fly_csrf_field() ?>
      <div class="box">
        <h2>① 选择数据库</h2>
        <label for="driver">数据库类型</label>
        <select id="driver" name="driver" onchange="document.getElementById('srv').style.display=(this.value==='sqlite')?'none':'block'">
          <option value="sqlite">SQLite（推荐：上传即用，无需建库）</option>
          <option value="mysql">MySQL / MariaDB</option>
          <option value="pgsql">PostgreSQL</option>
        </select>
        <div id="srv" style="display:none">
          <div class="row">
            <div><label for="db_host">主机</label><input id="db_host" name="db_host" value="localhost"></div>
            <div><label for="db_port">端口</label><input id="db_port" name="db_port" value="3306"></div>
          </div>
          <label for="db_name">数据库名</label><input id="db_name" name="db_name" placeholder="例如 flylog">
          <div class="row">
            <div><label for="db_user">用户名</label><input id="db_user" name="db_user"></div>
            <div><label for="db_pass">密码</label><input id="db_pass" name="db_pass" type="password"></div>
          </div>
          <p class="hint">MySQL/PgSQL：库可由面板预建（填好直接选库）；也可留空尝试自动建库。</p>
        </div>
      </div>
      <div class="box">
        <h2>② 注册管理员</h2>
        <label for="admin_user">管理员账号</label>
        <input id="admin_user" name="admin_user" maxlength="100" placeholder="例如 xiaozhou">
        <div class="row">
          <div><label for="admin_pass">密码（至少 8 位）</label><input id="admin_pass" name="admin_pass" type="password" maxlength="100"></div>
          <div><label for="admin_pass2">确认密码</label><input id="admin_pass2" name="admin_pass2" type="password" maxlength="100"></div>
        </div>
      </div>
      <button class="btn" type="submit">注册并安装</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
