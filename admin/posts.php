<?php
/** FlyLog V2.0 后台文章管理（列表 + 标题搜索 + 置顶/草稿标识 + 删除） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');   // V6.0 #10(P1) 旧版只校验登录，任何已登录角色都能删除 / 置顶文章

$db = fly_db();
$kw = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// 删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'delete') {
    fly_csrf_check();
    $delId = (int)($_POST['id'] ?? 0);
    BasePost::delete($delId);   // V7.0 第二批：软删除，进回收站
    fly_audit('post.delete', 'post', $delId);   // V7.0 第二批：审计
    fly_redirect('posts.php?deleted=1');
}
// 快捷置顶切换
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'pin') {
    fly_csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $p = fly_db()->table('posts')->where('id', $id)->one();
    if ($p) {
        BasePost::update($id, array('is_pinned' => empty($p['is_pinned']) ? 1 : 0));
        fly_audit('post.update', 'post', $id, 'toggle pin');   // V7.0 第二批：审计
    }
    fly_redirect('posts.php');
}

// V9.0 批量操作：一次对多篇文章做同一动作（回收站 / 草稿 / 发布 / 置顶 / 移动分类）
// 每条都单独写审计日志，方便事后追查是谁批量动了哪些文章。
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'bulk') {
    fly_csrf_check();
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    $bulkCat = (int)($_POST['bulk_cat'] ?? 0);
    $allowed = array('trash', 'draft', 'publish', 'pin', 'unpin', 'category');
    $done = 0;
    if (in_array($bulkAction, $allowed, true)) {
        // 单次最多处理 200 篇：既防误操作拖垮请求，也够覆盖真实使用场景
        foreach (array_slice($ids, 0, 200) as $v) {
            $pid = (int)$v;
            if ($pid <= 0) {
                continue;
            }
            $row = fly_db()->table('posts')->where('id', $pid)->one();
            if (!$row) {
                continue;
            }
            switch ($bulkAction) {
                case 'trash':
                    BasePost::delete($pid);
                    fly_audit('post.delete', 'post', $pid, 'bulk trash');
                    $done++;
                    break;
                case 'draft':
                    BasePost::update($pid, array('is_draft' => 1));
                    fly_audit('post.update', 'post', $pid, 'bulk draft');
                    $done++;
                    break;
                case 'publish':
                    BasePost::update($pid, array('is_draft' => 0));
                    fly_audit('post.update', 'post', $pid, 'bulk publish');
                    $done++;
                    break;
                case 'pin':
                    BasePost::update($pid, array('is_pinned' => 1));
                    fly_audit('post.update', 'post', $pid, 'bulk pin');
                    $done++;
                    break;
                case 'unpin':
                    BasePost::update($pid, array('is_pinned' => 0));
                    fly_audit('post.update', 'post', $pid, 'bulk unpin');
                    $done++;
                    break;
                case 'category':
                    if ($bulkCat <= 0 || !fly_db_has_column('posts', 'category_id')) {
                        break;
                    }
                    $catRow = fly_cat_resolve($bulkCat);
                    if (!$catRow) {
                        break;
                    }
                    // 同步写回老的 category 字段，保证老模板/导出数据拿到的分类名一致
                    BasePost::update($pid, array('category_id' => $bulkCat, 'category' => $catRow['name']));
                    fly_audit('post.update', 'post', $pid, 'bulk category ' . $bulkCat);
                    $done++;
                    break;
            }
        }
    }
    fly_redirect('posts.php?bulk=' . $done);
}

$q = fly_db()->table('posts');
$q->whereRaw('deleted_at = 0');   // V7.0 第二批：回收站里的文章不出现在此列表
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

<?php if (isset($_GET['deleted'])): ?><div class="msg msg-ok">文章已移入回收站（可在「回收站」里还原或彻底删除）。</div><?php endif; ?>

<form method="get" action="posts.php" class="side-search" style="max-width:420px;margin-bottom:16px">
  <input name="q" value="<?= fly_e($kw) ?>" placeholder="按标题搜索…">
  <button type="submit">搜索</button>
</form>
<p style="margin-bottom:14px">
  <a href="trash.php">🗑 回收站</a>
  <?php $__trashN = (int)fly_db()->value("SELECT COUNT(*) FROM posts WHERE deleted_at > 0"); if ($__trashN): ?>
    · <span style="color:#9ca3af">有 <?= $__trashN ?> 篇在回收站</span>
  <?php endif; ?>
</p>

<?php if (isset($_GET['bulk'])): ?><div class="msg msg-ok">已批量处理 <?= (int)$_GET['bulk'] ?> 篇文章。</div><?php endif; ?>

<div class="panel">
  <?php /* V9.0 批量操作：复选框用 HTML5 form 属性挂到这个表单上，
           这样行内的删除/置顶小表单不会被嵌套进去（HTML 不允许表单套表单）。 */ ?>
  <form method="post" action="posts.php" id="fly-bulk-form" onsubmit="return flyBulkCheck()">
    <?= fly_csrf_field() ?><input type="hidden" name="op" value="bulk">
    <div class="bulk-bar">
      <b style="font-size:14px">批量操作</b>
      <select name="bulk_action" id="bulk-action" onchange="flyBulkToggle()">
        <option value="">请选择操作…</option>
        <option value="trash">🗑 移入回收站</option>
        <option value="draft">📝 转为草稿</option>
        <option value="publish">✅ 转为发布</option>
        <option value="pin">📌 设为置顶</option>
        <option value="unpin">取消置顶</option>
        <option value="category">📂 移动到分类…</option>
      </select>
      <select name="bulk_cat" id="bulk-cat" style="display:none"><?= fly_cat_options(0) ?></select>
      <button class="btn btn-sm" type="submit">应用</button>
      <span class="bulk-count" id="bulk-count">已选 0 篇</span>
    </div>
  </form>

  <table class="tbl"><thead><tr>
    <th class="col-check"><input type="checkbox" id="bulk-all" aria-label="全选/全不选"></th>
    <th>ID</th><th>标题</th><th>分类</th><th>状态</th><th>阅读</th><th>时间</th><th>操作</th>
  </tr></thead><tbody>
  <?php foreach ($posts as $p): ?>
    <tr>
      <td class="col-check"><input type="checkbox" class="bulk-item" name="ids[]" value="<?= (int)$p['id'] ?>" form="fly-bulk-form" aria-label="选择文章 <?= (int)$p['id'] ?>"></td>
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
  <?php if (!$posts): ?><tr><td colspan="8" style="color:#9ca3af"><?= $kw !== '' ? '没有匹配的文章' : '还没有文章，去写一篇吧' ?></td></tr><?php endif; ?>
  </tbody></table>
</div>

<script>
/* V9.0 批量操作：全选联动 + 已选计数 + 提交前校验 */
(function(){
  var all = document.getElementById('bulk-all');
  var items = document.querySelectorAll('.bulk-item');
  var cnt = document.getElementById('bulk-count');
  function count(){
    var n = 0;
    for (var i = 0; i < items.length; i++) { if (items[i].checked) { n++; } }
    if (cnt) { cnt.textContent = '已选 ' + n + ' 篇'; }
    if (all) { all.checked = items.length > 0 && n === items.length; }
  }
  if (all) {
    all.addEventListener('change', function(){
      for (var i = 0; i < items.length; i++) { items[i].checked = all.checked; }
      count();
    });
  }
  for (var i = 0; i < items.length; i++) { items[i].addEventListener('change', count); }
  count();
})();
function flyBulkToggle(){
  var a = document.getElementById('bulk-action');
  var c = document.getElementById('bulk-cat');
  if (c) { c.style.display = (a && a.value === 'category') ? '' : 'none'; }
}
function flyBulkCheck(){
  var a = document.getElementById('bulk-action');
  if (!a || a.value === '') { alert('请先选择要执行的操作。'); return false; }
  var items = document.querySelectorAll('.bulk-item'), n = 0;
  for (var i = 0; i < items.length; i++) { if (items[i].checked) { n++; } }
  if (n === 0) { alert('请先勾选至少一篇文章。'); return false; }
  var tip = ({'trash':'移入回收站','draft':'转为草稿','publish':'转为发布','pin':'设为置顶','unpin':'取消置顶','category':'移动分类'})[a.value] || a.value;
  return confirm('确定对选中的 ' + n + ' 篇文章执行「' + tip + '」？');
}
flyBulkToggle();
</script>
<?php fly_admin_foot(); ?>
