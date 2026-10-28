<header class="site-head">
  <a class="site-title" href="<?= fly_e(fly_url('home')) ?>"><?= fly_e(fly_site_name()) ?></a>
  <span class="site-sub">
    <?php if (!empty($note)): ?><?= fly_e($note) ?> · <?php endif; ?>
    <?php if (!empty($_SESSION['fly_member_id'])): ?><a href="account.php">会员中心</a> · <?php else: ?><a href="account.php?action=login">登录</a> · <?php endif; ?>
    <a href="<?= fly_e(fly_url('archive')) ?>">归档</a> ·
    <a href="<?= fly_e(fly_url('feed')) ?>">RSS</a> ·
    <a href="<?= fly_e(fly_base_url() . 'admin/login.php') ?>">后台</a>
  </span>
</header>
<?= fly_tpl_breadcrumb($breadcrumb ?? array()) ?>
