<?php
/** FlyLog V2.0 留言审核（先审后发 + 回复展示 + 通过/撤下/删除/一键通过） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_comments');   // V6.0 #11(P1) 旧版只校验登录，无权限角色也能通过 / 删除留言

$db = fly_db();
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $op = $_POST['op'] ?? '';
    $cid = (int)($_POST['id'] ?? 0);
    if ($op === 'approve') {
        BaseComment::setStatus($cid, 1);
        fly_audit('comment.approve', 'comment', $cid);   // V7.0 第二批：审计
    } elseif ($op === 'unapprove') {
        BaseComment::setStatus($cid, 0);
        fly_audit('comment.unapprove', 'comment', $cid);   // V7.0 第二批：审计
    } elseif ($op === 'delete') {
        BaseComment::delete($cid);
        fly_audit('comment.delete', 'comment', $cid);   // V7.0 第二批：审计
    } elseif ($op === 'approve_all') {
        $db->exec("UPDATE comments SET status = 1 WHERE status = 0");
        fly_audit('comment.approve_all', 'comment', 0, 'count=' . $pendingCount);   // V7.0 第二批：审计
    }
    fly_redirect('comments.php?filter=' . urlencode($filter));
}

$q = $db->table('comments');
if ($filter === 'pending') { $q->where('status', 0); }
elseif ($filter === 'approved') { $q->where('status', 1); }
$comments = $q->order('id', 'DESC')->limit(200)->all();

// 关联文章标题 + 回复对象作者
$postTitles = array();
$authorNames = array();
foreach ($comments as $c) {
    $postTitles[(int)$c['post_id']] = $postTitles[(int)$c['post_id']] ?? $db->value("SELECT title FROM posts WHERE id = ?", array((int)$c['post_id']));
    if ((int)$c['parent_id'] > 0 && !isset($authorNames[(int)$c['parent_id']])) {
        $authorNames[(int)$c['parent_id']] = $db->value("SELECT author FROM comments WHERE id = ?", array((int)$c['parent_id']));
    }
}
$pendingCount = (int)$db->value("SELECT COUNT(*) FROM comments WHERE status = 0");

fly_admin_head('留言审核', 'comments');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">留言审核</h1>
  <?php if ($pendingCount): ?>
  <form class="inline-form" method="post" action="comments.php?filter=pending" onsubmit="return confirm('确定一次性通过全部 <?= $pendingCount ?> 条待审核留言？')">
    <?= fly_csrf_field() ?><input type="hidden" name="op" value="approve_all">
    <button class="btn" type="submit">一键通过全部待审核（<?= $pendingCount ?>）</button>
  </form>
  <?php endif; ?>
</div>

<p style="margin-bottom:14px">
  <a href="comments.php?filter=pending" style="<?= $filter === 'pending' ? 'font-weight:700' : '' ?>">待审核（<?= $pendingCount ?>）</a> ·
  <a href="comments.php?filter=approved" style="<?= $filter === 'approved' ? 'font-weight:700' : '' ?>">已通过</a> ·
  <a href="comments.php?filter=all" style="<?= $filter === 'all' ? 'font-weight:700' : '' ?>">全部</a>
</p>

<div class="panel">
  <table class="tbl"><thead><tr><th>ID</th><th>作者</th><th>所属文章</th><th>内容</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($comments as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><b><?= fly_e($c['author']) ?></b><?php if ((int)$c['parent_id'] > 0): ?><br><span style="color:#9ca3af;font-size:12px">回复 @<?= fly_e($authorNames[(int)$c['parent_id']] ?? ('#' . $c['parent_id'])) ?></span><?php endif; ?></td>
      <td><?= fly_e($postTitles[(int)$c['post_id']] ?? ('文章#' . $c['post_id'])) ?></td>
      <td style="max-width:280px"><?= fly_e($c['content']) ?></td>
      <td><?= (int)$c['status'] === 1 ? '<span class="tag tag-ok">已通过</span>' : '<span class="tag tag-wait">待审核</span>' ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($c['created_at']) ?></td>
      <td style="white-space:nowrap">
        <?php if ((int)$c['status'] !== 1): ?>
          <form class="inline-form" method="post" action="comments.php?filter=<?= fly_e($filter) ?>"><?= fly_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="op" value="approve"><button class="btn btn-sm" type="submit">通过</button></form>
        <?php else: ?>
          <form class="inline-form" method="post" action="comments.php?filter=<?= fly_e($filter) ?>"><?= fly_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="op" value="unapprove"><button class="btn btn-sm btn-gray" type="submit">撤下</button></form>
        <?php endif; ?>
        <form class="inline-form" method="post" action="comments.php?filter=<?= fly_e($filter) ?>" onsubmit="return confirm('确定删除这条留言？')"><?= fly_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="op" value="delete"><button class="btn btn-sm btn-red" type="submit">删除</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$comments): ?><tr><td colspan="7" style="color:#9ca3af">该分类下暂无留言</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php fly_admin_foot(); ?>
