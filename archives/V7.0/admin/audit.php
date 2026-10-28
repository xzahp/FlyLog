<?php
/** FlyLog V7.0 第二批 审计日志（后台操作追溯） */
require __DIR__ . '/_init.php';
fly_admin_require('view_logs');

$db = fly_db();

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$total = fly_audit_count($action);
$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $limit;

$logs = fly_audit_list($action, $limit, $offset);
$actions = fly_audit_actions();

fly_admin_head('审计日志', 'audit');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">📝 审计日志 <span style="font-size:13px;color:#6b7280;font-weight:400">（谁 · 何时 · 对什么 · 做了什么）</span></h1>
  <a class="btn btn-gray" href="logs.php">登录日志</a>
</div>

<form method="get" action="audit.php" class="side-search" style="max-width:420px;margin-bottom:16px">
  <select name="action" onchange="this.form.submit()" style="padding:8px">
    <option value="">全部操作</option>
    <?php foreach ($actions as $a): ?>
    <option value="<?= fly_e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= fly_e(fly_audit_action_label($a)) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($action !== ''): ?><a class="btn btn-gray" href="audit.php">清除筛选</a><?php endif; ?>
</form>

<div class="cards" style="margin-bottom:18px">
  <div class="card"><div class="num"><?= $total ?></div><div class="label">当前筛选记录</div></div>
</div>

<div class="panel">
  <table class="tbl"><thead><tr><th>时间</th><th>操作人</th><th>动作</th><th>目标</th><th>详情</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($l['created_at']) ?></td>
      <td><?= fly_e($l['actor'] ?: 'system') ?></td>
      <td><?= fly_e(fly_audit_action_label($l['action'])) ?></td>
      <td><?= fly_e($l['target_type'] ?: '—') ?><?= (int)$l['target_id'] > 0 ? ' #' . (int)$l['target_id'] : '' ?></td>
      <td style="max-width:300px;color:#374151"><?= fly_e($l['detail'] ?: '—') ?></td>
      <td style="font-family:monospace;font-size:12.5px"><?= fly_e(fly_mask_ip($l['ip'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6" style="color:#9ca3af">暂无审计记录。</td></tr><?php endif; ?>
  </tbody></table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="pager">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php if ($i == $page): ?><span class="cur"><?= $i ?></span><?php else: ?>
      <a href="audit.php?page=<?= $i ?><?= $action !== '' ? '&amp;action=' . fly_e(rawurlencode($action)) : '' ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php fly_admin_foot(); ?>
