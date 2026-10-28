<footer class="foot">
  Powered by <a href="<?= fly_e(fly_url('home')) ?>">FlyLog</a> V<?= fly_e(FLY_VERSION) ?> · 轻量现代博客系统<?php if (is_file(FLY_ROOT . '/download.php')): ?> · <a href="<?= fly_e(fly_base_url() . 'download.php') ?>">下载本程序</a><?php endif; ?>
</footer>
<?php fly_do_action('fly_foot'); ?>
</body>
</html>
