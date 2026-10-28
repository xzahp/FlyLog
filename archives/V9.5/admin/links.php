<?php
/** FlyLog V9.0 友情链接管理（新增 / 编辑 / 删除 / 显隐 / 排序） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_settings');

$err = '';
$ok = '';
$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId ? fly_link_find($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $op = (string)($_POST['op'] ?? '');

    if ($op === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $input = array(
            'name'        => $_POST['name'] ?? '',
            'url'         => $_POST['url'] ?? '',
            'description' => $_POST['description'] ?? '',
            'logo'        => $_POST['logo'] ?? '',
            'sort_no'     => $_POST['sort_no'] ?? 0,
            'visible'     => empty($_POST['visible']) ? 0 : 1,
        );
        if ($id > 0) {
            list($done, $msg) = fly_link_update($id, $input);
            if ($done) {
                fly_audit('link.update', 'link', $id, $input['name']);
                fly_redirect('links.php?saved=1');
            }
            $err = $msg;
            $editing = array_merge((array)fly_link_find($id), $input);
            $editId = $id;
        } else {
            list($done, $msg) = fly_link_add($input);
            if ($done) {
                fly_audit('link.create', 'link', (int)$msg, $input['name']);
                fly_redirect('links.php?saved=1');
            }
            $err = $msg;
            $editing = $input;
        }
    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (fly_link_delete($id)) {
            fly_audit('link.delete', 'link', $id);
        }
        fly_redirect('links.php?deleted=1');
    } elseif ($op === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $row = fly_link_find($id);
        if ($row) {
            fly_db()->update('links', array('visible' => empty($row['visible']) ? 1 : 0), 'id = ?', array($id));
            fly_audit('link.update', 'link', $id, 'toggle visible');
        }
        fly_redirect('links.php');
    }
}

if (isset($_GET['saved'])) { $ok = '已保存。'; }
if (isset($_GET['deleted'])) { $ok = '已删除。'; }

$links = fly_links(false);
// 表还没建出来时提示站长跑一次升级，而不是甩一个空白页
$tableReady = fly_link_ready();

$name = $editing['name'] ?? '';
$url = $editing['url'] ?? '';
$description = $editing['description'] ?? '';
$logo = $editing['logo'] ?? '';
$sortNo = (int)($editing['sort_no'] ?? 0);
$visible = !isset($editing['visible']) || !empty($editing['visible']);

fly_admin_head('友情链接', 'links');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h1 style="font-size:22px">🔗 友情链接</h1>
  <a class="btn btn-gray" href="settings.php">« 返回设置</a>
</div>

<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
<?php if (!$tableReady): ?>
  <div class="msg msg-err">友情链接数据表尚未创建。请访问一次站点首页触发自动升级（或进入「系统健康」检查数据库），之后再回来。</div>
<?php endif; ?>

<div class="panel">
  <h2><?= $editId ? '编辑链接 #' . (int)$editId : '添加新链接' ?></h2>
  <form method="post" action="links.php">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="op" value="save">
    <input type="hidden" name="id" value="<?= (int)$editId ?>">
    <div class="row">
      <div>
        <label for="name">站点名称 *</label>
        <input id="name" name="name" maxlength="100" value="<?= fly_e($name) ?>" required placeholder="如：小周爱航拍">
      </div>
      <div>
        <label for="url">链接地址 *</label>
        <input id="url" name="url" maxlength="500" value="<?= fly_e($url) ?>" required placeholder="https://example.com">
      </div>
    </div>
    <label for="description">一句话简介</label>
    <input id="description" name="description" maxlength="255" value="<?= fly_e($description) ?>" placeholder="选填，会显示在链接标题旁">
    <label for="logo">图标地址（Logo URL）</label>
    <input id="logo" name="logo" maxlength="500" value="<?= fly_e($logo) ?>" placeholder="选填，留空则显示站点名首字">
    <div class="row">
      <div>
        <label for="sort_no">排序号（小的排前面）</label>
        <input id="sort_no" name="sort_no" type="number" value="<?= (int)$sortNo ?>">
      </div>
      <div>
        <label style="font-weight:400;margin-top:26px;display:block">
          <input type="checkbox" name="visible" value="1" style="width:auto;margin-right:6px" <?= $visible ? 'checked' : '' ?>>前台显示
        </label>
      </div>
    </div>
    <div class="form-ops">
      <button class="btn" type="submit">保存</button>
      <?php if ($editId): ?><a class="btn btn-gray" href="links.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h2>全部链接（<?= count($links) ?>）</h2>
  <table class="tbl"><thead><tr><th>排序</th><th>站点</th><th>地址</th><th>简介</th><th>状态</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($links as $l): ?>
    <tr>
      <td><?= (int)$l['sort_no'] ?></td>
      <td>
        <?php if (!empty($l['logo'])): ?>
          <img src="<?= fly_e($l['logo']) ?>" alt="" style="width:16px;height:16px;vertical-align:-3px;margin-right:5px;border-radius:3px">
        <?php endif; ?>
        <b><?= fly_e($l['name']) ?></b>
      </td>
      <td><a href="<?= fly_e($l['url']) ?>" target="_blank" rel="nofollow noopener" style="color:#9ca3af;font-size:13px"><?= fly_e($l['url']) ?></a></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($l['description']) ?></td>
      <td><?= empty($l['visible']) ? '<span class="tag tag-draft">隐藏</span>' : '<span class="tag tag-ok">显示</span>' ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm btn-gray" href="links.php?edit=<?= (int)$l['id'] ?>">编辑</a>
        <form class="inline-form" method="post" action="links.php">
          <?= fly_csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="btn btn-sm" type="submit"><?= empty($l['visible']) ? '显示' : '隐藏' ?></button>
        </form>
        <form class="inline-form" method="post" action="links.php" onsubmit="return confirm('确定删除该链接？')">
          <?= fly_csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="btn btn-sm btn-red" type="submit">删除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$links): ?><tr><td colspan="6" style="color:#9ca3af">还没有友情链接，用上面的表单添加第一个吧</td></tr><?php endif; ?>
  </tbody></table>
  <p class="hint" style="margin-top:10px">友情链接会在前台侧边栏「友情链接」一栏展示；排序号相同则按添加顺序排列。</p>
</div>
<?php fly_admin_foot(); ?>
