<?php /* #179 无障碍：跳过导航直达正文（Tab 聚焦时显示） */ ?>
<a class="skip-link" href="#fly-main">跳到正文</a>
<header class="site-head">
  <div class="head-inner">
    <a class="site-title" href="<?= fly_e(fly_url('home')) ?>"><?= fly_e(fly_site_name()) ?></a>
    <span class="site-sub" aria-label="站内导航">
      <?php if (!empty($note)): ?><?= fly_e($note) ?> · <?php endif; ?>
      <?php if (!empty($_SESSION['fly_member_id'])): ?><a href="account.php"><?= fly_e(fly_term('member')) ?>中心</a><?php else: ?><a href="account.php?action=login">登录</a><?php endif; ?>
      <?php /* #213 后台自定义导航菜单（有配置则替换默认链接组；登录入口始终保留） */ ?>
      <?php $flyNav = fly_nav_menu_items(); ?>
      <?php if ($flyNav): ?>
        <?php foreach ($flyNav as $m): ?> · <a href="<?= fly_e($m['url']) ?>"<?= !empty($m['new_tab']) ? ' target="_blank" rel="noopener"' : '' ?>><?= fly_e($m['name']) ?></a><?php endforeach; ?>
      <?php else: ?>
         · <a href="<?= fly_e(fly_url('archive')) ?>"><?= fly_e(fly_term('archive')) ?></a> ·
      <a href="<?= fly_e(fly_base_url() . 'favorites.php') ?>">收藏</a> ·
      <a href="<?= fly_e(fly_url('feed')) ?>">RSS</a> ·
      <a href="<?= fly_e(fly_base_url() . 'admin/login.php') ?>">后台</a>
      <?php endif; ?>
      <?php if (function_exists('fly_theme_allow_switch') && fly_theme_allow_switch()): ?>
      <label class="fly-theme-sw-wrap" title="切换主题">
        <select id="fly-theme-sw" class="fly-theme-sw" aria-label="切换主题">
          <?php foreach (fly_theme_list() as $k => $lbl): ?><option value="<?= fly_e($k) ?>"><?= fly_e($lbl) ?></option><?php endforeach; ?>
        </select>
      </label>
      <script>
      (function(){
        var s = document.getElementById('fly-theme-sw'); if (!s) return;
        var def = '<?= fly_opt('theme_default', 'auto') ?>';
        var cur = (window.flyTheme && window.flyTheme.current && window.flyTheme.current()) || localStorage.getItem('fly_theme') || def;
        if (s.querySelector('option[value="'+cur+'"]')) s.value = cur;
        s.addEventListener('change', function(){
          if (window.flyTheme) window.flyTheme.set(s.value);
          else { localStorage.setItem('fly_theme', s.value); document.documentElement.setAttribute('data-theme', s.value); }
        });
        window.flyThemeSyncSwitcher = function(){ var c = localStorage.getItem('fly_theme') || def; if (s.querySelector('option[value="'+c+'"]')) s.value = c; };
      })();
      </script>
      <?php endif; ?>
    </span>
  </div>
</header>
<?php /* 面包屑包进 .head-inner 居中容器（1120px），与导航对齐，避免贴左边缘 */
if (!empty($breadcrumb)): ?><div class="head-inner bc-bar"><?= fly_tpl_breadcrumb($breadcrumb) ?></div><?php endif; ?>
