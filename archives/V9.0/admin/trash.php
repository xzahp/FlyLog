<?php
/** FlyLog V7.0 第二批 回收站（软删除文章的还原 / 彻底删除） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');

$db = fly_db();
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $op = (string)($_POST['op'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($op === 'restore' && $id > 0) {
        BasePost::restore($id);
        fly_audit('post.restore', 'post', $id);
        $ok = '已从回收站还原。';
    } elseif ($op === 'purge' && $id > 0) {
        BasePost::purge($id);
        fly_audit('post.purge', 'post', $id);
        $ok = '已彻底删除，不可恢复。';
    } elseif ($op === 'empty' && isset($_POST['confirm'])) {
        // 清空回收站：批量硬删
        $ids = $db->all("SELECT id FROM posts WHERE deleted_at > 0");
        $n = 0;
        foreach ($ids as $r) {
            if (BasePost::purge((int)$r['id'])) { $n++; }
        }
        fly_audit('post.purge', 'post', 0, '清空回收站，共 ' . $n . ' 篇');
        $ok = '回收站已清空（' . $n . ' 篇）。';
    }
}

// 回收站列表（deleted_at > 0，按删除时间倒序）
$trash = $db->all("SELECT id, title, category, deleted_at FROM posts WHERE deleted_at > 0 ORDER BY deleted_at DESC");

fly_admin_head('回收站', 'trash');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">🗑 回收站 <span style="font-size:13px;color:#6b7280;font-weight:400">（软删除的文章，可还原或彻底删除）</span></h1>
  <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
</div>

<?php if (isset($_GET['ok'])): ?><div class="msg msg-ok">操作成功。</div><?php endif; ?>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<?php if ($trash): ?>
<form method="post" action="trash.php" onsubmit="return confirm('确定清空回收站？所有文章将被彻底删除，不可恢复！')">
  <?= fly_csrf_field() ?><input type="hidden" name="op" value="empty"><input type="hidden" name="confirm" value="1">
  <button class="btn btn-red" type="submit">清空回收站（<?= count($trash) ?>）</button>
</form>
<?php endif; ?>

<div class="panel">
  <table class="tbl"><thead><tr><th>ID</th><th>标题</th><th>分类</th><th>删除时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($trash as $t): ?>
    <tr>
      <td><?= (int)$t['id'] ?></td>
      <td><?= fly_e($t['title']) ?></td>
      <td><?= fly_e($t['category'] ?: '—') ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e(date('Y-m-d H:i:s', (int)$t['deleted_at'])) ?></td>
      <td style="white-space:nowrap">
        <form class="inline-form" method="post" action="trash.php"><?= fly_csrf_field() ?><input type="hidden" name="op" value="restore"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm" type="submit">还原</button></form>
        <form class="inline-form" method="post" action="trash.php" onsubmit="return confirm('彻底删除《<?= fly_e($t['title']) ?>》？不可恢复！')"><?= fly_csrf_field() ?><input type="hidden" name="op" value="purge"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-red" type="submit">彻底删除</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$trash): ?><tr><td colspan="5" style="color:#9ca3af">回收站是空的。</td></tr><?php endif; ?>
  </tbody></table>
</div>
<p class="hint">说明：后台「文章管理」里的「删除」会把文章移入回收站，而不是立即销毁。重要文章误删后可在本页一键还原。</p>
<?php fly_admin_foot(); ?>
