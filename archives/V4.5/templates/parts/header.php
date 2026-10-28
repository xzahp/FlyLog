<?php /* #179 无障碍：跳过导航直达正文（Tab 聚焦时显示） */ ?>
<a class="skip-link" href="#fly-main">跳到正文</a>
<header class="site-head">
  <div class="head-inner">
    <a class="site-title" href="<?= fly_e(fly_url('home')) ?>"><?= fly_e(fly_site_name()) ?></a>
    <span class="site-sub" aria-label="站内导航">
      <?php if (!empty($note)): ?><?= fly_e($note) ?> · <?php endif; ?>
      <?php if (!empty($_SESSION['fly_member_id'])): ?><a href="account.php">会员中心</a> · <?php else: ?><a href="account.php?action=login">登录</a> · <?php endif; ?>
      <a href="<?= fly_e(fly_url('archive')) ?>">归档</a> ·
      <a href="<?= fly_e(fly_url('feed')) ?>">RSS</a> ·
      <a href="<?= fly_e(fly_base_url() . 'admin/login.php') ?>">后台</a>
    </span>
  </div>
</header>
<?php /* 面包屑包进 .head-inner 居中容器（1120px），与导航对齐，避免贴左边缘 */
if (!empty($breadcrumb)): ?><div class="head-inner bc-bar"><?= fly_tpl_breadcrumb($breadcrumb) ?></div><?php endif; ?>
