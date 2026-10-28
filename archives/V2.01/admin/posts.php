<?php
/** FlyLog V2.0 后台文章管理（列表 + 标题搜索 + 置顶/草稿标识 + 删除） */
require __DIR__ . '/_init.php';

$db = fly_db();
$kw = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// 删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'delete') {
    fly_csrf_check();
    BasePost::delete((int)($_POST['id'] ?? 0));
    fly_redirect('posts.php?deleted=1');
}
// 快捷置顶切换
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'pin') {
    fly_csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $p = fly_db()->table('posts')->where('id', $id)->one();
    if ($p) {
        BasePost::update($id, array('is_pinned' => empty($p['is_pinned']) ? 1 : 0));
    }
    fly_redirect('posts.php');
}

$q = fly_db()->table('posts');
if ($kw !== '') {
    $q->whereRaw('title LIKE ?', array('%' . $kw . '%'));
}
$posts = $q->order('id', 'DESC')->all();

fly_admin_head('文章管理', 'posts');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">文章管理</h1>
  <a class="btn" href="post.php">✏️ 写新文章</a>
</div>

<?php if (isset($_GET['deleted'])): ?><div class="msg msg-ok">文章已删除。</div><?php endif; ?>

<form method="get" action="posts.php" class="side-search" style="max-width:420px;margin-bottom:16px">
  <input name="q" value="<?= fly_e($kw) ?>" placeholder="按标题搜索…">
  <button type="submit">搜索</button>
</form>

<div class="panel">
  <table class="tbl"><thead><tr><th>ID</th><th>标题</th><th>分类</th><th>状态</th><th>阅读</th><th>时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($posts as $p): ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td><a href="post.php?id=<?= (int)$p['id'] ?>"><?= fly_e($p['title']) ?></a></td>
      <td><?= fly_e($p['category'] ?: '—') ?></td>
      <td>
        <?= !empty($p['is_draft']) ? '<span class="tag tag-draft">草稿</span>' : '<span class="tag tag-ok">已发布</span>' ?>
        <?= !empty($p['is_pinned']) ? ' <span class="tag tag-wait">置顶</span>' : '' ?>
      </td>
      <td><?= (int)$p['views'] ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($p['created_at']) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm btn-gray" href="post.php?id=<?= (int)$p['id'] ?>">编辑</a>
        <form class="inline-form" method="post" action="posts.php" onsubmit="return confirm('切换置顶状态？')">
          <?= fly_csrf_field() ?><input type="hidden" name="op" value="pin"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm" type="submit"><?= !empty($p['is_pinned']) ? '取消置顶' : '置顶' ?></button>
        </form>
        <form class="inline-form" method="post" action="posts.php" onsubmit="return confirm('确定删除该文章？留言也会一起删除，不可恢复！')">
          <?= fly_csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-red" type="submit">删除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$posts): ?><tr><td colspan="7" style="color:#9ca3af"><?= $kw !== '' ? '没有匹配的文章' : '还没有文章，去写一篇吧' ?></td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php fly_admin_foot(); ?>
