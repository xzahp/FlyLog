<?php
/** FlyLog V2.0 附件管理（上传 / 下载 / 删除） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_backup');

$db = fly_db();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'delete') {
    fly_csrf_check();
    $aid = (int)($_POST['id'] ?? 0);
    $a = $db->table('attachments')->where('id', $aid)->one();
    if ($a) {
        $fp = FLY_DATA . '/uploads/' . basename($a['path']);
        if (is_file($fp)) { @unlink($fp); }
        $db->delete('attachments', 'id = ?', array($aid));
    }
    fly_redirect('attachments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'upload') {
    fly_csrf_check();
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = '上传失败，请选择文件（且不超过服务器限制）。';
    } else {
        $f = $_FILES['file'];
        $max = 10 * 1024 * 1024; // 10MB
        if ($f['size'] > $max) {
            $err = '文件不能超过 10MB。';
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . preg_replace('/[^a-z0-9]/', '', $ext) : '');
            $dest = FLY_DATA . '/uploads/' . $safe;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $db->insert('attachments', array(
                    'name' => $f['name'], 'path' => $safe, 'size' => (int)$f['size'],
                    'mime' => (string)($f['type'] ?? ''), 'created_at' => date('Y-m-d H:i:s'),
                ));
                fly_redirect('attachments.php?uploaded=1');
            } else {
                $err = '保存文件失败，请检查 datadir/uploads 目录权限。';
            }
        }
    }
}

$files = $db->table('attachments')->order('id', 'DESC')->limit(200)->all();

fly_admin_head('附件管理', 'attachments');
?>
<h1 style="font-size:22px;margin-bottom:16px">附件管理</h1>
<?php if (isset($_GET['uploaded'])): ?><div class="msg msg-ok">上传成功。</div><?php endif; ?>
<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<div class="panel">
  <h2>上传附件</h2>
  <form method="post" action="attachments.php" enctype="multipart/form-data">
    <?= fly_csrf_field() ?><input type="hidden" name="op" value="upload">
    <input type="file" name="file" required>
    <p class="hint" style="margin:8px 0">图片等文件上传到 datadir/uploads（已防直闯）。上传后可在编辑器里用外链地址引用。</p>
    <button class="btn" type="submit">上传</button>
  </form>
</div>

<div class="panel">
  <h2>已上传（<?= count($files) ?>）</h2>
  <table class="tbl"><thead><tr><th>ID</th><th>文件名</th><th>大小</th><th>类型</th><th>时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($files as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
      <td><?= fly_e($a['name']) ?></td>
      <td><?= fly_e(round((int)$a['size'] / 1024, 1) . ' KB') ?></td>
      <td style="color:#9ca3af;font-size:12.5px"><?= fly_e($a['mime']) ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($a['created_at']) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm btn-gray" href="<?= fly_e(fly_base_url() . 'file.php?f=' . rawurlencode(basename($a['path']))) ?>" target="_blank">查看</a>
        <form class="inline-form" method="post" action="attachments.php" onsubmit="return confirm('确定删除该附件？')">
          <?= fly_csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-sm btn-red" type="submit">删除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$files): ?><tr><td colspan="6" style="color:#9ca3af">还没有附件</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php fly_admin_foot(); ?>
