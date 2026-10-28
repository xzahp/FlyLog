<?php
/** FlyLog V2.0 修改密码（验证旧密码 + 新密码 ≥8 位 + 两次一致） */
require __DIR__ . '/_init.php';

$db = fly_db();
$adminId = (int)$_SESSION['fly_admin_id'];
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');
    $admin = $db->table('admins')->where('id', $adminId)->one();

    if (!$admin || !fly_password_verify($old, $admin['password'])) {
        $err = '旧密码不正确。';
    } elseif (fly_len($new) < 8) {
        $err = '新密码至少 8 位。';
    } elseif ($new !== $new2) {
        $err = '两次输入的新密码不一致。';
    } else {
        $db->update('admins', array('password' => fly_password_hash($new)), 'id = ?', array($adminId));
        $ok = '密码修改成功！下次登录请使用新密码。';
    }
}

fly_admin_head('修改密码', 'password');
?>
<div class="panel" style="max-width:520px">
  <h2>修改密码</h2>
  <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
  <?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
  <form method="post" action="password.php">
    <?= fly_csrf_field() ?>
    <label for="old_password">当前密码</label>
    <input id="old_password" name="old_password" type="password" required>
    <label for="new_password">新密码（至少 8 位）</label>
    <input id="new_password" name="new_password" type="password" required minlength="8">
    <label for="new_password2">确认新密码</label>
    <input id="new_password2" name="new_password2" type="password" required minlength="8">
    <div class="form-ops"><button class="btn" type="submit">保存新密码</button></div>
  </form>
</div>
<?php fly_admin_foot(); ?>
