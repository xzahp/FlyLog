<?php
/** FlyLog V7.0 分类树管理（无限级：父级 / 排序 / 描述 / 别名） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');

$ok = '';
$err = '';
$edit = null;

// ---------- 编辑载入 ----------
$eid = (int)($_GET['edit'] ?? 0);
if ($eid > 0) {
    $edit = fly_cat_resolve($eid);
    if (!$edit) {
        $err = '要编辑的分类不存在。';
    }
}

// ---------- 写操作 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $act = (string)($_POST['act'] ?? 'save');

    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && fly_cat_delete($id)) {
            $ok = '已删除分类（其下的' . fly_term('post') . '改为「未分类」，子分类已上提一级）。';
            fly_audit('category.delete', 'category', $id);   // V7.0 第二批：审计
        } else {
            $err = '删除失败。';
        }
    } elseif ($act === 'import') {
        // 把老库遗留的「文章里的分类字符串」一次性搬进分类树
        $n = 0;
        try {
            $rows = fly_db()->all("SELECT DISTINCT category FROM posts WHERE category <> ''");
            foreach ($rows as $r) {
                if (fly_cat_ensure(trim((string)$r['category']))) {
                    $n++;
                }
            }
            for ($i = 0; $i < 20; $i++) {
                if (!fly_db_migrate_v7()) {
                    break;
                }
            }
        } catch (Exception $e) {
            $err = '导入中断：' . $e->getMessage();
        }
        if ($err === '') {
            $ok = '已从历史' . fly_term('post') . '中导入 ' . $n . ' 个分类。';
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        // 父分类不能是自己或自己的后代，否则树会成环
        if ($id > 0 && $parentId > 0 && in_array($parentId, fly_cat_subtree_ids($id), true)) {
            $err = '父分类不能是它自己或它的子分类。';
        } elseif ($name === '') {
            $err = '分类名称不能为空。';
        } elseif (fly_len($name) > 100) {
            $err = '分类名称不超过 100 字。';
        } else {
            $newId = fly_cat_save(array(
                'name'        => $name,
                'slug'        => trim((string)($_POST['slug'] ?? '')),
                'parent_id'   => $parentId,
                'sort_order'  => (int)($_POST['sort_order'] ?? 0),
                'description' => trim((string)($_POST['description'] ?? '')),
            ), $id);
            if ($newId) {
                $ok = $id > 0 ? '分类已更新。' : '分类已创建。';
                fly_audit($id > 0 ? 'category.update' : 'category.create', 'category', $newId);   // V7.0 第二批：审计
            } else {
                $err = '保存失败（可能是别名与其它分类重复）。';
            }
        }
    }
    if ($err === '') {
        fly_redirect('categories.php?saved=1');
    }
}

$tree = fly_cat_tree();
fly_admin_head('分类管理', 'categories');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h1 style="font-size:22px">分类管理 <span style="font-size:13px;color:#6b7280;font-weight:400">（支持无限级子分类）</span></h1>
  <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="msg msg-ok">操作成功。</div><?php endif; ?>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<div class="panel">
  <h2><?= $edit ? '编辑分类：' . fly_e($edit['name']) : '添加新分类' ?></h2>
  <form method="post">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="row">
      <div>
        <label for="name">分类名称 *</label>
        <input id="name" name="name" maxlength="100" value="<?= fly_e($edit['name'] ?? '') ?>" placeholder="如：航拍教程">
      </div>
      <div>
        <label for="slug">别名 slug（留空自动按名称生成）</label>
        <input id="slug" name="slug" maxlength="100" value="<?= fly_e($edit['slug'] ?? '') ?>" placeholder="如：drone-course">
      </div>
    </div>
    <div class="row">
      <div>
        <label for="parent_id">父分类（选「无」= 顶级）</label>
        <select id="parent_id" name="parent_id">
          <option value="0">— 无（顶级分类）—</option>
          <?= fly_cat_options((int)($edit['parent_id'] ?? 0), (int)($edit['id'] ?? 0), false) ?>
        </select>
      </div>
      <div>
        <label for="sort_order">排序（越小越靠前）</label>
        <input id="sort_order" name="sort_order" type="number" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
    </div>
    <label for="description">分类描述（用于分类页 SEO description，可留空）</label>
    <input id="description" name="description" maxlength="500" value="<?= fly_e($edit['description'] ?? '') ?>">
    <div class="form-ops">
      <button class="btn" type="submit"><?= $edit ? '保存修改' : '创建分类' ?></button>
      <?php if ($edit): ?><a class="btn btn-gray" href="categories.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h2>全部分类（<?= count($tree) ?>）</h2>
  <?php if (!$tree): ?>
    <p class="hint">还没有分类。可以在上面新建，也可以点下面的按钮把历史文章里已有的分类名一次性导入。</p>
    <form method="post" onsubmit="return confirm('把历史文章里出现过的分类名导入为正式分类？')">
      <?= fly_csrf_field() ?>
      <input type="hidden" name="act" value="import">
      <button class="btn btn-gray" type="submit">从历史文章导入分类</button>
    </form>
  <?php else: ?>
  <table class="tbl"><thead><tr>
    <th>分类（缩进 = 层级）</th><th style="width:150px">别名</th><th style="width:80px">文章</th><th style="width:90px">排序</th><th style="width:170px">操作</th>
  </tr></thead><tbody>
    <?php foreach ($tree as $c): ?>
    <tr>
      <td><?= fly_e($c['indent'] . $c['name']) ?></td>
      <td style="font-size:12.5px;word-break:break-all"><?= fly_e($c['slug']) ?></td>
      <td><?= (int)fly_cat_count((int)$c['id']) ?></td>
      <td><?= (int)$c['sort_order'] ?></td>
      <td>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="categories.php?edit=<?= (int)$c['id'] ?>">编辑</a>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="<?= fly_e(fly_base_url() . ltrim(fly_cat_url($c), '/')) ?>" target="_blank">查看</a>
        <form method="post" style="display:inline" onsubmit="return confirm('删除「<?= fly_e($c['name']) ?>」？\n子分类会自动上提一级，文章不会被删除。')">
          <?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-gray" style="padding:3px 8px;font-size:12px" type="submit">删除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>
<?php fly_admin_foot(); ?>
