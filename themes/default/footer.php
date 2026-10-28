<?php
/**
 * 主题页脚（footer.php）
 * 品牌行 + 主题署名。核心的 fly_foot 钩子照常触发，
 * 插件往页脚注入内容的能力不受主题影响。
 */
?>
</div><!-- /.th-wrap -->
<footer class="th-foot">
  <p>
    © <?= date('Y') ?> <?= fly_e(fly_config('site.name', 'FlyLog')) ?>
    · Powered by FlyLog PHP <?= fly_e(FLY_VERSION) ?>
    · 主题：FlyLog Default
  </p>
  <p class="th-foot-links">
    <a href="<?= fly_e(fly_base_url()) ?>sitemap.php">站点地图</a>
    <a href="<?= fly_e(fly_base_url()) ?>feed.php">RSS 订阅</a>
    <a href="<?= fly_e(fly_base_url()) ?>admin/">管理后台</a>
  </p>
</footer>
<?php fly_do_action('fly_foot'); ?>
</body>
</html>
