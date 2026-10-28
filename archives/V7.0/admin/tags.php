<?php
/** FlyLog V7.0 标签管理（列表 + 改名 + 删除 + 空标签清理） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');

$ok = '';
$err = '';
$edit = null;

$eid = (int)($_GET['edit'] ?? 0);
if ($eid > 0) {
    try {
        $edit = fly_db()->one('SELECT * FROM tags WHERE id = ?', array($eid));
    } catch (Exception $e) {
        $edit = null;
    }
    if (!$edit) {
        $err = '要编辑的标签不存在。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $act = (string)($_POST['act'] ?? 'save');

    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && fly_tag_delete($id)) {
            $ok = '已删除标签（文章不受影响）。';
            fly_audit('tag.delete', 'tag', $id);   // V7.0 第二批：审计
        } else {
            $err = '删除失败。';
        }
    } elseif ($act === 'gc') {
        // 清理没有任何文章引用的孤儿标签
        $n = 0;
        try {
            $orphans = fly_db()->all(
                'SELECT t.id FROM tags t WHERE NOT EXISTS (SELECT 1 FROM post_tags pt WHERE pt.tag_id = t.id)'
            );
            foreach ($orphans as $o) {
                if (fly_tag_delete((int)$o['id'])) {
                    $n++;
                }
            }
        } catch (Exception $e) {
            $err = '清理中断：' . $e->getMessage();
        }
        if ($err === '') {
            $ok = '已清理 ' . $n . ' 个无人使用的标签。';
            fly_audit('tag.gc', 'tag', 0, 'count=' . $n);   // V7.0 第二批：审计
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        if ($name === '') {
            $err = '标签名称不能为空。';
        } elseif (fly_len($name) > 50) {
            $err = '标签名称不超过 50 字。';
        } else {
            $slug = $slug !== '' ? fly_slugify($slug, 'tag') : fly_slugify($name, 'tag');
            $slug = fly_slug_unique('tags', $slug, $id);
            try {
                if ($id > 0) {
                    fly_db()->update('tags', array('name' => $name, 'slug' => $slug), 'id = ?', array($id));
                    $ok = '标签已更新。';
                    fly_audit('tag.update', 'tag', $id);   // V7.0 第二批：审计
                } else {
                    fly_db()->insert('tags', array(
                        'name' => $name, 'slug' => $slug, 'created_at' => date('Y-m-d H:i:s'),
                    ));
                    $ok = '标签已创建，可在写文章时直接勾选。';
                    fly_audit('tag.create', 'tag', (int)fly_db()->lastInsertId());   // V7.0 第二批：审计
                }
            } catch (Exception $e) {
                $err = '保存失败（别名可能重复）。';
            }
        }
    }
    if ($err === '') {
        fly_redirect('tags.php?saved=1');
    }
}

$tags = fly_tag_all();
fly_admin_head('标签管理', 'tags');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h1 style="font-size:22px">标签管理</h1>
  <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="msg msg-ok">操作成功。</div><?php endif; ?>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<div class="panel">
  <h2><?= $edit ? '编辑标签：' . fly_e($edit['name']) : '新建标签' ?></h2>
  <form method="post">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="row">
      <div>
        <label for="name">标签名称 *</label>
        <input id="name" name="name" maxlength="50" value="<?= fly_e($edit['name'] ?? '') ?>" placeholder="如：入门教程">
      </div>
      <div>
        <label for="slug">别名 slug（留空自动生成）</label>
        <input id="slug" name="slug" maxlength="100" value="<?= fly_e($edit['slug'] ?? '') ?>">
      </div>
    </div>
    <div class="form-ops">
      <button class="btn" type="submit"><?= $edit ? '保存修改' : '创建标签' ?></button>
      <?php if ($edit): ?><a class="btn btn-gray" href="tags.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
  <p class="hint">日常不用先来这里建标签：写文章时在「标签」框里直接输入新词，保存时会自动创建。</p>
</div>

<div class="panel">
  <h2>全部标签（<?= count($tags) ?>）</h2>
  <?php if (!$tags): ?>
    <p class="hint">还没有标签。写文章时填标签，或上面新建。</p>
  <?php else: ?>
  <table class="tbl"><thead><tr>
    <th>标签</th><th style="width:200px">别名</th><th style="width:90px">引用文章</th><th style="width:170px">操作</th>
  </tr></thead><tbody>
    <?php foreach ($tags as $t): ?>
    <tr>
      <td>#<?= fly_e($t['name']) ?></td>
      <td style="font-size:12.5px;word-break:break-all"><?= fly_e($t['slug']) ?></td>
      <td><?= (int)$t['n'] ?></td>
      <td>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="tags.php?edit=<?= (int)$t['id'] ?>">编辑</a>
        <a class="btn btn-gray" style="padding:3px 8px;font-size:12px" href="<?= fly_e(fly_base_url() . '?tag=' . rawurlencode($t['slug'])) ?>" target="_blank">查看</a>
        <form method="post" style="display:inline" onsubmit="return confirm('删除标签「<?= fly_e($t['name']) ?>」？\n只会解除与文章的关联，文章本身不会删。')">
          <?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <button class="btn btn-gray" style="padding:3px 8px;font-size:12px" type="submit">删除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <form method="post" style="margin-top:10px" onsubmit="return confirm('清理无人使用的标签？')">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="gc">
    <button class="btn btn-gray" type="submit">清理空标签</button>
  </form>
  <?php endif; ?>
</div>
<?php fly_admin_foot(); ?>
