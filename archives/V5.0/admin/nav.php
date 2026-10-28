<?php
/** FlyLog V5.0 导航菜单自定义（#213）
 *  前台顶部导航支持后台增删改、拖拽排序、外链新窗口打开。
 *  存储：options 表 site.nav_menu，JSON 数组 [{name,url,new_tab}] */
require __DIR__ . '/_init.php';
fly_admin_require('manage_settings');

$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $act = (string)($_POST['act'] ?? 'save');
    $menu = fly_nav_menu_items();

    if ($act === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $url  = trim((string)($_POST['url'] ?? ''));
        if ($name !== '' && $url !== '') {
            $menu[] = array('name' => $name, 'url' => $url, 'new_tab' => isset($_POST['new_tab']) ? 1 : 0);
            fly_set_option('site.nav_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
            $ok = '已添加菜单项。';
        } else {
            $ok = '名称和链接不能为空。';
        }
    } elseif ($act === 'del' && isset($_POST['idx'])) {
        $idx = (int)$_POST['idx'];
        if (isset($menu[$idx])) {
            array_splice($menu, $idx, 1);
            fly_set_option('site.nav_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
            $ok = '已删除。';
        }
    } elseif ($act === 'up' && isset($_POST['idx'])) {
        $idx = (int)$_POST['idx'];
        if ($idx > 0 && isset($menu[$idx])) {
            $tmp = $menu[$idx - 1]; $menu[$idx - 1] = $menu[$idx]; $menu[$idx] = $tmp;
            fly_set_option('site.nav_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
            $ok = '已上移。';
        }
    } elseif ($act === 'down' && isset($_POST['idx'])) {
        $idx = (int)$_POST['idx'];
        if (isset($menu[$idx + 1])) {
            $tmp = $menu[$idx + 1]; $menu[$idx + 1] = $menu[$idx]; $menu[$idx] = $tmp;
            fly_set_option('site.nav_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
            $ok = '已下移。';
        }
    } elseif ($act === 'reset') {
        fly_set_option('site.nav_menu', '');
        $ok = '已恢复默认导航。';
    }
    fly_redirect('nav.php?saved=1');
}

$menu = fly_nav_menu_items();
fly_admin_head('导航菜单', 'nav');
?>
<h1 style="font-size:22px;margin-bottom:16px">导航菜单自定义</h1>
<?php if (isset($_GET['saved'])): ?><div class="msg msg-ok">操作成功。</div><?php endif; ?>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>

<div class="panel">
  <h2>当前菜单项（从上到下 = 前台从左到右）</h2>
  <?php if (!$menu): ?>
    <p class="hint">未自定义，前台显示默认导航（登录/会员中心、归档、RSS、后台）。</p>
  <?php else: ?>
  <table class="tbl"><thead><tr><th style="width:40px">序</th><th>名称</th><th>链接</th><th>新窗口</th><th style="width:150px">操作</th></tr></thead><tbody>
    <?php foreach ($menu as $i => $m): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= fly_e($m['name']) ?></td>
      <td style="word-break:break-all;font-size:12.5px"><?= fly_e($m['url']) ?></td>
      <td><?= !empty($m['new_tab']) ? '是' : '否' ?></td>
      <td>
        <form method="post" style="display:inline"><?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="up"><input type="hidden" name="idx" value="<?= $i ?>">
          <button class="btn btn-gray" type="submit" style="padding:3px 8px;font-size:12px" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
        </form>
        <form method="post" style="display:inline"><?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="down"><input type="hidden" name="idx" value="<?= $i ?>">
          <button class="btn btn-gray" type="submit" style="padding:3px 8px;font-size:12px" <?= $i === count($menu) - 1 ? 'disabled' : '' ?>>↓</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('确定删除该菜单项？')"><?= fly_csrf_field() ?>
          <input type="hidden" name="act" value="del"><input type="hidden" name="idx" value="<?= $i ?>">
          <button class="btn btn-gray" type="submit" style="padding:3px 8px;font-size:12px">删</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <form method="post" style="margin-top:10px" onsubmit="return confirm('恢复默认导航？自定义项将全部清除。')"><?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="reset">
    <button class="btn btn-gray" type="submit">恢复默认导航</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>添加菜单项</h2>
  <form method="post">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="act" value="add">
    <div class="row">
      <div><label for="mname">显示名称</label><input id="mname" name="name" placeholder="如：飞行学院"></div>
      <div><label for="murl">链接地址</label><input id="murl" name="url" placeholder="如：https://... 或 /archive"></div>
    </div>
    <label style="font-weight:400"><input type="checkbox" name="new_tab" value="1" style="width:auto;margin-right:6px">新窗口打开（外链建议勾选）</label>
    <div class="form-ops"><button class="btn" type="submit">添加</button></div>
  </form>
  <p class="hint">提示：菜单为空时前台用默认导航；自定义后「登录/会员中心、归档、RSS、后台」需自行添加链接（登录：<code>/account.php?action=login</code>，归档：<code>/archive</code>，RSS：<code>/feed</code>）。</p>
</div>

<?php fly_admin_foot(); ?>
