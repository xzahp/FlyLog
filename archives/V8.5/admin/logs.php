<?php
/** FlyLog V2.0 登录日志（IP + 成败 + 时间） */
require __DIR__ . '/_init.php';
fly_admin_require('view_logs');

$db = fly_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'clear') {
    fly_csrf_check();
    $db->exec("DELETE FROM admin_logs");
    fly_redirect('logs.php?cleared=1');
}

$fail24 = (int)$db->value("SELECT COUNT(*) FROM admin_logs WHERE success = 0 AND created_at >= ?", array(date('Y-m-d H:i:s', time() - 86400)));
$logs = $db->table('admin_logs')->order('id', 'DESC')->limit(200)->all();

fly_admin_head('登录日志', 'logs');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">登录日志</h1>
  <form class="inline-form" method="post" action="logs.php" onsubmit="return confirm('确定清空全部登录日志？')">
    <?= fly_csrf_field() ?><input type="hidden" name="op" value="clear">
    <button class="btn btn-sm btn-gray" type="submit">清空日志</button>
  </form>
</div>

<div class="cards" style="margin-bottom:18px">
  <div class="card"><div class="num" style="color:<?= $fail24 ? 'var(--err)' : 'var(--ok)' ?>"><?= $fail24 ?></div><div class="label">近 24h 登录失败</div></div>
  <div class="card"><div class="num"><?= count($logs) ?></div><div class="label">最近记录（≤200）</div></div>
</div>

<?php if (isset($_GET['cleared'])): ?><div class="msg msg-ok">日志已清空。</div><?php endif; ?>

<div class="panel">
  <table class="tbl"><thead><tr><th>ID</th><th>账号</th><th>IP</th><th>结果</th><th>时间</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= (int)$l['id'] ?></td>
      <td><?= fly_e($l['username'] ?: '—') ?></td>
      <td style="font-family:monospace;font-size:13px"><?= fly_e($l['ip']) ?></td>
      <td><?= (int)$l['success'] === 1 ? '<span class="tag tag-ok">成功</span>' : '<span class="tag tag-wait">失败</span>' ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($l['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="5" style="color:#9ca3af">暂无登录记录</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php fly_admin_foot(); ?>
