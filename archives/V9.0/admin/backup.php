<?php
/**
 * FlyLog V2.0 数据备份 / 导出
 * 支持：导出 JSON（文章/留言/设置）、导出 SQL（INSERT 语句）、下载 SQLite 数据库文件。
 */
require __DIR__ . '/_init.php';
fly_admin_require('manage_backup');

$db = fly_db();
$driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
$export = isset($_GET['export']) ? $_GET['export'] : '';

if ($export === 'json') {
    $data = array(
        'version' => FLY_VERSION,
        'driver' => $driver,
        'exported_at' => date('c'),
        'posts' => $db->all("SELECT * FROM posts ORDER BY id"),
        'comments' => $db->all("SELECT * FROM comments ORDER BY id"),
        'options' => $db->all("SELECT * FROM options"),
    );
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="flylog-backup-' . date('Ymd-His') . '.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($export === 'sql') {
    $tables = array('posts', 'comments', 'admins', 'members', 'options', 'attachments', 'admin_logs', 'login_attempts');
    $out = "-- FlyLog " . FLY_VERSION . " SQL backup -- " . date('c') . "\n";
    foreach ($tables as $t) {
        $rows = $db->all("SELECT * FROM " . $t);
        foreach ($rows as $row) {
            $vals = array();
            foreach ($row as $v) {
                $vals[] = ($v === null) ? 'NULL' : $db->quote($v);
            }
            $out .= "INSERT INTO " . $t . " VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="flylog-backup-' . date('Ymd-His') . '.sql"');
    echo $out;
    exit;
}

if ($export === 'dbfile' && $driver === 'sqlite') {
    $path = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (FLY_DATA . '/flylog.sqlite');
    if (is_file($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="flylog-' . date('Ymd-His') . '.sqlite"');
        readfile($path);
        exit;
    }
}

$counts = array();
foreach (array('posts', 'comments', 'admins', 'members', 'options', 'attachments') as $t) {
    try { $counts[$t] = (int)$db->value("SELECT COUNT(*) FROM " . $t); } catch (Throwable $e) { $counts[$t] = 0; }
}

fly_admin_head('数据备份', 'backup');
?>
<h1 style="font-size:22px;margin-bottom:16px">数据备份 / 导出</h1>
<div class="cards" style="margin-bottom:20px">
  <?php foreach ($counts as $t => $n): ?>
    <div class="card"><div class="num"><?= $n ?></div><div class="label"><?= fly_e($t) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2>导出数据</h2>
  <p class="hint" style="margin-bottom:14px">建议定期备份。导出内容不含管理员密码哈希之外不会泄露登录凭据，但仍请妥善保管备份文件。</p>
  <p style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="backup.php?export=json">⬇️ 导出 JSON（文章/留言/设置）</a>
    <a class="btn" href="backup.php?export=sql">⬇️ 导出 SQL（INSERT 语句）</a>
    <?php if ($driver === 'sqlite'): ?>
      <a class="btn btn-gray" href="backup.php?export=dbfile">⬇️ 下载 SQLite 数据库文件</a>
    <?php endif; ?>
  </p>
</div>
<?php fly_admin_foot(); ?>
