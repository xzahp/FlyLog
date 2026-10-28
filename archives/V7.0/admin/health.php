<?php
/** FlyLog V7.0 第二批 系统健康（运行环境 / 目录权限 / 库表 / 异常统计） */
require __DIR__ . '/_init.php';
fly_admin_require('view_logs');

$db = fly_db();
$driver = $db->driver;

// ---- 数据库版本（按驱动）----
$dbVersion = '—';
try {
    if ($driver === 'sqlite') {
        $dbVersion = 'SQLite ' . (string)$db->value('SELECT sqlite_version()');
    } elseif ($driver === 'pgsql') {
        $dbVersion = (string)$db->value('SELECT version()');
    } else {
        $dbVersion = (string)$db->value('SELECT VERSION()');
    }
} catch (Throwable $e) {
    $dbVersion = '读取失败';
}

// ---- 各表行数 ----
$tableRows = array();
try {
    foreach (array('posts', 'comments', 'members', 'admins', 'categories', 'tags', 'post_tags', 'pages', 'attachments', 'options', 'admin_logs', 'post_likes', 'audit_logs') as $t) {
        if (fly_db_has_table($t)) {
            $tableRows[$t] = (int)$db->value('SELECT COUNT(*) FROM ' . fly_ident($t, $driver));
        }
    }
} catch (Throwable $e) {
    $tableRows = array();
}

// ---- 目录可写性 ----
function fly_dir_writable($d) { return is_dir($d) && is_writable($d); }
$dirStatus = array(
    'cachedir' => fly_dir_writable(FLY_CACHE),
    'logsdir'  => fly_dir_writable(FLY_LOGS),
    'datadir'  => fly_dir_writable(FLY_DATA),
);

// ---- 扩展 ----
$ext = function ($n) { return extension_loaded($n) ? '已启用' : '未启用'; };

// ---- 近期异常统计 ----
function fly_count_log_lines($prefix, $hours = 24)
{
    $dir = FLY_LOGS;
    if (!is_dir($dir)) { return 0; }
    $cut = time() - $hours * 3600;
    $n = 0;
    foreach (glob($dir . '/' . $prefix . '-*.log') as $f) {
        if (filemtime($f) < $cut) { continue; }
        $n += (int)substr_count(file_get_contents($f), "\n");
    }
    return $n;
}
$err24 = fly_count_log_lines('php', 24);
$slow24 = fly_count_log_lines('slow', 24);

// ---- 磁盘空间 ----
$diskFree = @disk_free_space(FLY_ROOT);
$diskTotal = @disk_total_space(FLY_ROOT);

// ---- 安全响应头是否开启（简易探测）----
$secureCfg = (bool)fly_config('site.url', '');

fly_admin_head('系统健康', 'health');
?>
<h1 style="font-size:22px;margin-bottom:6px">🩺 系统健康</h1>
<p class="hint" style="margin-bottom:18px">运行环境、目录权限、数据量与近期异常的总览。绿色=正常，橙色=注意，红色=需处理。</p>

<div class="health-grid">
  <div class="health-item"><div class="h-k">PHP 版本</div><div class="h-v health-ok"><?= fly_e(PHP_VERSION) ?>（<?= fly_e(php_sapi_name()) ?>）</div></div>
  <div class="health-item"><div class="h-k">数据库</div><div class="h-v health-ok"><?= fly_e($driver) ?> · <?= fly_e($dbVersion) ?></div></div>
  <div class="health-item"><div class="h-k">内存上限</div><div class="h-v"><?= fly_e(ini_get('memory_limit')) ?></div></div>
  <div class="health-item"><div class="h-k">上传上限</div><div class="h-v"><?= fly_e(ini_get('upload_max_filesize')) ?> / POST <?= fly_e(ini_get('post_max_size')) ?></div></div>
  <div class="health-item"><div class="h-k">执行时限</div><div class="h-v"><?= fly_e(ini_get('max_execution_time')) ?>s</div></div>
  <div class="health-item"><div class="h-k">磁盘剩余</div><div class="h-v"><?= $diskFree !== false ? fly_e(round($diskFree / 1048576) . ' MB') : '—' ?><?= $diskTotal !== false ? ' / ' . round($diskTotal / 1048576) . ' MB' : '' ?></div></div>
  <div class="health-item"><div class="h-k">缓存目录</div><div class="h-v <?= $dirStatus['cachedir'] ? 'health-ok' : 'health-bad' ?>"><?= $dirStatus['cachedir'] ? '可写 ✓' : '不可写 ✗' ?></div></div>
  <div class="health-item"><div class="h-k">日志目录</div><div class="h-v <?= $dirStatus['logsdir'] ? 'health-ok' : 'health-bad' ?>"><?= $dirStatus['logsdir'] ? '可写 ✓' : '不可写 ✗' ?></div></div>
  <div class="health-item"><div class="h-k">数据目录</div><div class="h-v <?= $dirStatus['datadir'] ? 'health-ok' : 'health-bad' ?>"><?= $dirStatus['datadir'] ? '可写 ✓' : '不可写 ✗' ?></div></div>
  <div class="health-item"><div class="h-k">近 24h PHP 错误</div><div class="h-v <?= $err24 ? 'health-warn' : 'health-ok' ?>"><?= $err24 ?> 行</div></div>
  <div class="health-item"><div class="h-k">近 24h 慢请求(>1s)</div><div class="h-v <?= $slow24 ? 'health-warn' : 'health-ok' ?>"><?= $slow24 ?> 条</div></div>
  <div class="health-item"><div class="h-k">HTTPS 配置</div><div class="h-v <?= $secureCfg ? 'health-ok' : 'health-warn' ?>"><?= $secureCfg ? '已配置站点 URL' : '未配置（可能走 HTTP）' ?></div></div>
</div>

<h2 style="font-size:17px;margin:22px 0 10px">扩展依赖</h2>
<div class="health-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
  <div class="health-item"><div class="h-k">GD (图像处理)</div><div class="h-v <?= extension_loaded('gd') ? 'health-ok' : 'health-warn' ?>"><?= fly_e($ext('gd')) ?></div></div>
  <div class="health-item"><div class="h-k">cURL</div><div class="h-v <?= extension_loaded('curl') ? 'health-ok' : 'health-warn' ?>"><?= fly_e($ext('curl')) ?></div></div>
  <div class="health-item"><div class="h-k">mbstring</div><div class="h-v <?= extension_loaded('mbstring') ? 'health-ok' : 'health-bad' ?>"><?= fly_e($ext('mbstring')) ?></div></div>
  <div class="health-item"><div class="h-k">json</div><div class="h-v <?= extension_loaded('json') ? 'health-ok' : 'health-bad' ?>"><?= fly_e($ext('json')) ?></div></div>
  <div class="health-item"><div class="h-k">openssl</div><div class="h-v <?= extension_loaded('openssl') ? 'health-ok' : 'health-warn' ?>"><?= fly_e($ext('openssl')) ?></div></div>
  <div class="health-item"><div class="h-k">zip</div><div class="h-v <?= extension_loaded('zip') ? 'health-ok' : 'health-warn' ?>"><?= fly_e($ext('zip')) ?></div></div>
</div>

<h2 style="font-size:17px;margin:22px 0 10px">数据量（各表行数）</h2>
<div class="panel">
  <table class="tbl"><thead><tr><th>数据表</th><th>行数</th></tr></thead><tbody>
    <?php foreach ($tableRows as $t => $c): ?>
    <tr><td><?= fly_e($t) ?></td><td><?= (int)$c ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$tableRows): ?><tr><td colspan="2" style="color:#9ca3af">无数据</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php fly_admin_foot(); ?>
