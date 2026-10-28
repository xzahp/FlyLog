<?php
/** FlyLog V2.0 站点设置（TDK / SEO / 功能开关 / 第三方登录，全部可视化，不碰代码） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    fly_set_option('site.name', trim((string)($_POST['site_name'] ?? '')));
    fly_set_option('site.desc', trim((string)($_POST['site_desc'] ?? '')));
    fly_set_option('site.keywords', trim((string)($_POST['site_keywords'] ?? '')));
    fly_set_option('site.url', rtrim(trim((string)($_POST['site_url'] ?? '')), '/'));
    foreach (array('comment_audit', 'comment_reply', 'register', 'github_login', 'upgrade_check', 'email_notify') as $f) {
        fly_set_option('feature_' . $f, isset($_POST[$f]) ? '1' : '0');
    }
    fly_set_option('github.client_id', trim((string)($_POST['github_id'] ?? '')));
    fly_set_option('github.client_secret', trim((string)($_POST['github_secret'] ?? '')));
    fly_set_option('upgrade.check_url', trim((string)($_POST['upgrade_url'] ?? '')));
    fly_redirect('settings.php?saved=1');
}

$s = array(
    'name' => fly_site_name(), 'desc' => fly_site_desc(), 'keywords' => fly_site_keywords(),
    'url' => (string)fly_opt('site.url', fly_config('site.url', '')),
);
$feat = array(
    'comment_audit' => fly_feature('comment_audit', true),
    'comment_reply' => fly_feature('comment_reply', true),
    'register' => fly_feature('register', true),
    'github_login' => fly_feature('github_login', false),
    'upgrade_check' => fly_feature('upgrade_check', false),
    'email_notify' => fly_feature('email_notify', false),
);

fly_admin_head('站点设置', 'settings');
?>
<h1 style="font-size:22px;margin-bottom:16px">站点设置</h1>
<?php if (isset($_GET['saved'])): ?><div class="msg msg-ok">设置已保存。</div><?php endif; ?>

<form method="post" action="settings.php">
  <?= fly_csrf_field() ?>
  <div class="panel">
    <h2>基本信息 / TDK</h2>
    <label for="site_name">站点名称</label>
    <input id="site_name" name="site_name" value="<?= fly_e($s['name']) ?>">
    <label for="site_desc">站点描述（description）</label>
    <input id="site_desc" name="site_desc" value="<?= fly_e($s['desc']) ?>">
    <label for="site_keywords">关键词（keywords，逗号分隔）</label>
    <input id="site_keywords" name="site_keywords" value="<?= fly_e($s['keywords']) ?>">
    <label for="site_url">站点完整 URL（留空自动识别；静态模式建议填写）</label>
    <input id="site_url" name="site_url" value="<?= fly_e($s['url']) ?>" placeholder="https://example.com">
    <p class="hint">以上用于全站 TDK、Open Graph 与结构化数据；sitemap / robots / 面包屑会自动套用。</p>
  </div>

  <div class="panel">
    <h2>功能开关</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:8px">
      <label style="font-weight:400"><input type="checkbox" name="comment_audit" value="1" style="width:auto;margin-right:6px" <?= $feat['comment_audit'] ? 'checked' : '' ?>>留言先审后发</label>
      <label style="font-weight:400"><input type="checkbox" name="comment_reply" value="1" style="width:auto;margin-right:6px" <?= $feat['comment_reply'] ? 'checked' : '' ?>>留言支持回复</label>
      <label style="font-weight:400"><input type="checkbox" name="register" value="1" style="width:auto;margin-right:6px" <?= $feat['register'] ? 'checked' : '' ?>>开放前台会员注册</label>
      <label style="font-weight:400"><input type="checkbox" name="email_notify" value="1" style="width:auto;margin-right:6px" <?= $feat['email_notify'] ? 'checked' : '' ?>>新留言邮件通知</label>
      <label style="font-weight:400"><input type="checkbox" name="github_login" value="1" style="width:auto;margin-right:6px" <?= $feat['github_login'] ? 'checked' : '' ?>>启用 GitHub 登录</label>
      <label style="font-weight:400"><input type="checkbox" name="upgrade_check" value="1" style="width:auto;margin-right:6px" <?= $feat['upgrade_check'] ? 'checked' : '' ?>>启用在线上级检查</label>
    </div>
  </div>

  <div class="panel">
    <h2>GitHub 登录（OAuth）</h2>
    <p class="hint" style="margin-bottom:8px">在 GitHub → Settings → Developer settings → OAuth Apps 创建应用，回调地址填：<code><?= fly_e(fly_base_url()) ?>github.php</code></p>
    <div class="row">
      <div><label for="github_id">Client ID</label><input id="github_id" name="github_id" value="<?= fly_e((string)fly_opt('github.client_id', '')) ?>"></div>
      <div><label for="github_secret">Client Secret</label><input id="github_secret" name="github_secret" type="password" value="<?= fly_e((string)fly_opt('github.client_secret', '')) ?>"></div>
    </div>
  </div>

  <div class="panel">
    <h2>在线升级检查</h2>
    <label for="upgrade_url">版本信息 JSON 地址</label>
    <input id="upgrade_url" name="upgrade_url" value="<?= fly_e((string)fly_opt('upgrade.check_url', '')) ?>" placeholder="https://example.com/version.json">
    <p class="hint">返回形如 <code>{"version":"2.1","url":"..."}</code> 的 JSON；配置并启用后，后台会提示新版本。</p>
  </div>

  <div class="panel">
    <h2>运行信息（只读）</h2>
    <p class="kv">当前数据库驱动：<b><?= fly_e(defined('DB_DRIVER') ? DB_DRIVER : 'sqlite') ?></b> · 运行模式：<b><?= fly_e(fly_mode()) ?></b>（模式在 config.php 的 site.mode 修改）· PHP：<b><?= fly_e(PHP_VERSION) ?></b></p>
  </div>

  <div class="form-ops"><button class="btn" type="submit">保存设置</button></div>
</form>
<?php fly_admin_foot(); ?>
