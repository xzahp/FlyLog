<?php
/** FlyLog V7.0 单页管理（关于我 / 友链这类独立页面，不进文章列表与 RSS） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');

$ok = '';
$err = '';
$edit = null;

$eid = (int)($_GET['edit'] ?? 0);
if ($eid > 0) {
    try {
        $edit = fly_db()->one('SELECT * FROM pages WHERE id = ?', array($eid));
    } catch (Exception $e) {
        $edit = null;
    }
    if (!$edit) {
        $err = '要编辑的单页不存在。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $act = (string)($_POST['act'] ?? 'save');
    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && fly_page_delete($id)) {
            $ok = '单页已删除。';
            fly_audit('page.delete', 'page', $id);   // V7.0 第二批：审计
        } else {
            $err = '删除失败。';
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = (string)($_POST['content'] ?? '');
        if ($title === '' || trim($content) === '') {
            $err = '标题和正文不能为空。';
        } elseif (fly_len($title) > 255) {
            $err = '标题不超过 255 字。';
        } else {
            $newId = fly_page_save(array(
                'title'      => $title,
                'slug'       => trim((string)($_POST['slug'] ?? '')),
                'content'    => $content,
                'status'     => isset($_POST['status']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ), $id);
            if ($newId) {
                $ok = $id > 0 ? '单页已更新。' : '单页已创建。';
                fly_audit($id > 0 ? 'page.update' : 'page.create', 'page', $newId);   // V7.0 第二批：审计
            } else {
                $err = '保存失败（别名可能重复）。';
            }
        }
    }
    if ($err === '') {
        fly_redirect('pages.php?saved=1');
    }
}

$pages = fly_page_all(false);
fly_admin_head('单页管理', 'pages');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h1 style="font-size:22px">单页管理</h1>
  <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="msg msg-ok">操作成功。</div><?php endif; ?>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<div class="panel">
  <h2><?= $edit ? '编辑单页：' . fly_e($edit['title']) : '新建单页' ?></h2>
  <form method="post">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="row">
      <div>
        <label for="title">页面标题 *</label>
        <input id="title" name="title" maxlength="255" value="<?= fly_e($edit['title'] ?? '') ?>" placeholder="如：关于我">
      </div>
      <div>
        <label for="slug">地址别名 slug（留空自动生成）</label>
        <input id="slug" name="slug" maxlength="100" value="<?= fly_e($edit['slug'] ?? '') ?>" placeholder="如：about">
      </div>
    </div>
    <div class="row">
      <div>
        <label for="sort_order">排序（越小越靠前）</label>
        <input id="sort_order" name="sort_order" type="number" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
      <div>
        <label style="font-weight:400;margin-top:26px"><input type="checkbox" name="status" value="1" style="width:auto;margin-right:6px" <?= ($edit && (int)$edit['status'] === 1) || !$edit ? 'checked' : '' ?>>发布（取消勾选 = 存为草稿，前台不可访问）</label>
      </div>
    </div>
    <label for="content">页面正文（支持 HTML）</label>
    <textarea id="content" name="content" style="min-height:260px;font-family:monospace"><?= fly_e($edit['content'] ?? '') ?></textarea>
    <div class="form-ops">
      <button class="btn" type="submit"><?= $edit ? '保存修改' : '创建单页' ?></button>
      <?php if ($edit): ?><a class="btn btn-gray" href="pages.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h2>全部单页（<?= count($pages) ?>）</h2>
  <?php if (!$pages): ?>
    <p class="hint">还没有单页。单页适合放「关于我」「友情链接」「打赏」这类不属于博客时间流的内容。</p>
  <?php else: ?>
  <table class="tbl"><thead><tr>
    <th>标题</th><th style="width:180px">地址</th><th style="width:80px">状态</th><th style="width:80px">排序</th><th style="width:170px">操作</th>
  </tr></thead><tbody>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><?= fly_e($p['title']) ?></td>
      <td style="font-size:12.5px;word-break:break-all">page.php?slug=<?= fly_e($p['slug']) ?></td>
      <td><?= (int)$p['status'] === 1 ? '已发布' : '草稿' ?></td>
      <td><?= (int)$p['sort_order'] ?></td>
      <td>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="pages.php?edit=<?= (int)$p['id'] ?>">编辑</a>
        <?php if ((int)$p['status'] === 1): ?>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="<?= fly_e(fly_base_url() . 'page.php?slug=' . rawurlencode($p['slug'])) ?>" target="_blank">查看</a>
        <?php endif; ?>
        <form method="post" style="display:inline" onsubmit="return confirm('删除单页「<?= fly_e($p['title']) ?>」？')">
          <?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-gray" style="padding:3px 8px;font-size:12px" type="submit">删除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <p class="hint">提示：想让单页出现在导航里，去「导航菜单」里加一条链接 <code>page.php?slug=别名</code>。</p>
  <?php endif; ?>
</div>
<?php fly_admin_foot(); ?>
