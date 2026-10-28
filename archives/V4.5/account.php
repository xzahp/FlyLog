<?php
/**
 * FlyLog V2.0 前台会员登录 / 注册（?action=login|register|logout）
 */
require __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'login';
$err = '';
$memberId = (int)($_SESSION['fly_member_id'] ?? 0);
$member = $memberId ? BaseMember::find($memberId) : null;

if ($action === 'logout') {
    unset($_SESSION['fly_member_id']);
    fly_redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'login') {
        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        $m = ($u !== '' && $p !== '') ? BaseMember::verify($u, $p) : null;
        if ($m) {
            session_regenerate_id(true);
            $_SESSION['fly_member_id'] = (int)$m['id'];
            fly_redirect('account.php');
        }
        $err = '账号或密码不正确。';
    } elseif ($do === 'register') {
        if (!fly_feature('register', true)) {
            $err = '本站暂未开放注册。';
        } else {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            $email = trim((string)($_POST['email'] ?? ''));
            if ($u === '' || $p === '') { $err = '账号和密码不能为空。'; }
            elseif (fly_len($p) < 8) { $err = '密码至少 8 位。'; }
            elseif ($p !== $p2) { $err = '两次输入的密码不一致。'; }
            elseif (BaseMember::findByUsername($u)) { $err = '该账号已被注册。'; }
            else {
                $id = BaseMember::register($u, $p, $email);
                $_SESSION['fly_member_id'] = $id;
                fly_redirect('account.php');
            }
        }
    }
}

$meta = array('title' => '会员中心 - ' . fly_site_name(), 'description' => '登录 / 注册');
echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '会员中心'))));
?>
<div class="wrap">
  <div class="center-card">
    <?php if ($member): ?>
      <h1>👋 你好，<?= fly_e($member['username']) ?></h1>
      <p class="sub">已登录会员账号</p>
      <a class="btn" href="<?= fly_e(fly_url('home')) ?>">返回首页</a>
      <a class="btn btn-gray" href="account.php?action=logout" style="margin-top:8px">退出登录</a>
    <?php else: ?>
      <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>
      <?php if (($action ?? 'login') === 'register'): ?>
        <h1>注册</h1>
        <p class="sub">创建一个会员账号</p>
        <?php if (!fly_feature('register', true)): ?>
          <div class="msg msg-warn">本站暂未开放注册。</div>
        <?php else: ?>
        <form method="post" action="account.php?action=register">
          <?= fly_csrf_field() ?><input type="hidden" name="do" value="register">
          <label for="username">账号</label><input id="username" name="username" required>
          <label for="email">邮箱（选填）</label><input id="email" name="email" type="email">
          <label for="password">密码（至少 8 位）</label><input id="password" name="password" type="password" required minlength="8">
          <label for="password2">确认密码</label><input id="password2" name="password2" type="password" required minlength="8">
          <button class="btn" type="submit">注 册</button>
        </form>
        <p class="hint" style="text-align:center;margin-top:12px">已有账号？<a href="account.php?action=login">去登录</a></p>
        <?php endif; ?>
      <?php else: ?>
        <h1>会员登录</h1>
        <p class="sub">登录以使用会员功能</p>
        <form method="post" action="account.php?action=login">
          <?= fly_csrf_field() ?><input type="hidden" name="do" value="login">
          <label for="username">账号</label><input id="username" name="username" required>
          <label for="password">密码</label><input id="password" name="password" type="password" required>
          <button class="btn" type="submit">登 录</button>
        </form>
        <?php if (fly_feature('register', true)): ?>
          <p class="hint" style="text-align:center;margin-top:12px">还没有账号？<a href="account.php?action=register">去注册</a></p>
        <?php endif; ?>
        <?php if (fly_feature('github_login', false) && (string)fly_opt('github.client_id', '') !== ''): ?>
          <p class="hint" style="text-align:center;margin-top:8px"><a href="github.php">用 GitHub 登录</a></p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
