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
    fly_set_option('site.og_image', trim((string)($_POST['og_image'] ?? '')));          // #161 OG 封面
    fly_set_option('site.baidu_push_token', trim((string)($_POST['baidu_push_token'] ?? ''))); // #173 百度主动推送
    fly_set_option('site.favicon', trim((string)($_POST['site_favicon'] ?? '')));        // #221 Favicon 自定义
    // #201 全站名词自定义（留空 = 用默认名词）
    foreach (array('post', 'comment', 'category', 'tag', 'member', 'archive', 'search') as $t) {
        fly_set_option('term.' . $t, trim((string)($_POST['term_' . $t] ?? '')));
    }
    // #208 自定义 404 页面
    fly_set_option('page_404.title', trim((string)($_POST['page_404_title'] ?? '')));
    fly_set_option('page_404.body', (string)($_POST['page_404_body'] ?? ''));
    // #231 Webhook 通知
    fly_set_option('notify.webhook_url', trim((string)($_POST['webhook_url'] ?? '')));
    fly_set_option('feature_webhook', isset($_POST['webhook']) ? '1' : '0');
    foreach (array('comment_audit', 'comment_reply', 'register', 'github_login', 'upgrade_check', 'email_notify') as $f) {
        fly_set_option('feature_' . $f, isset($_POST[$f]) ? '1' : '0');
    }
    fly_set_option('github.client_id', trim((string)($_POST['github_id'] ?? '')));
    fly_set_option('github.client_secret', trim((string)($_POST['github_secret'] ?? '')));
    fly_set_option('upgrade.check_url', trim((string)($_POST['upgrade_url'] ?? '')));
    // #V8.0 外观 / 主题
    $themeDefault = trim((string)($_POST['theme_default'] ?? 'auto'));
    if (!fly_theme_is_valid($themeDefault)) { $themeDefault = 'auto'; }
    fly_set_option('theme_default', $themeDefault);
    fly_set_option('feature_theme_switch', isset($_POST['theme_switch']) ? '1' : '0');
    fly_audit('setting.update', 'setting', 0);   // V7.0 第二批：审计
    fly_redirect('settings.php?saved=1');
}

$s = array(
    'name' => fly_site_name(), 'desc' => fly_site_desc(), 'keywords' => fly_site_keywords(),
    'url' => (string)fly_opt('site.url', fly_config('site.url', '')),
    'og_image' => (string)fly_opt('site.og_image', ''),
    'baidu_push_token' => (string)fly_opt('site.baidu_push_token', ''),
    'favicon' => (string)fly_opt('site.favicon', ''),
);
$terms = array();
foreach (array('post' => '文章', 'comment' => '留言', 'category' => '分类', 'tag' => '标签', 'member' => '会员', 'archive' => '归档', 'search' => '搜索') as $k => $def) {
    $terms[$k] = (string)fly_opt('term.' . $k, $def);
}
$page404 = array(
    'title' => (string)fly_opt('page_404.title', ''),
    'body'  => (string)fly_opt('page_404.body', ''),
);
$feat = array(
    'comment_audit' => fly_feature('comment_audit', true),
    'comment_reply' => fly_feature('comment_reply', true),
    'register' => fly_feature('register', true),
    'github_login' => fly_feature('github_login', false),
    'upgrade_check' => fly_feature('upgrade_check', false),
    'email_notify' => fly_feature('email_notify', false),
    'webhook' => fly_feature('webhook', false),
);
$webhookUrl = (string)fly_opt('notify.webhook_url', '');
$themes = fly_theme_list();
$themeDefault = fly_theme_default();
$allowSwitch = fly_theme_allow_switch();

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
    <label for="site_favicon">站点图标 Favicon（填图片 URL，留空用默认 🚁）</label>
    <input id="site_favicon" name="site_favicon" value="<?= fly_e($s['favicon']) ?>" placeholder="/assets/favicon.png 或 https://...">
    <p class="hint">以上用于全站 TDK、Open Graph 与结构化数据；sitemap / robots / 面包屑会自动套用。</p>
  </div>

  <div class="panel">
    <h2>SEO 与搜索引擎收录</h2>
    <label for="og_image">OG 分享封面 URL（留空用默认封面；文章含图片时自动取文章首图）</label>
    <input id="og_image" name="og_image" value="<?= fly_e($s['og_image']) ?>" placeholder="/assets/og-cover.png 或 https://...">
    <label for="baidu_push_token">百度主动推送 token（可选）</label>
    <input id="baidu_push_token" name="baidu_push_token" value="<?= fly_e($s['baidu_push_token']) ?>" placeholder="百度站长平台 → 链接提交 → 主动推送 的 token">
    <p class="hint">
      Sitemap 地址：<code><?= fly_e(fly_base_url()) ?>sitemap.xml</code>（伪静态 <code>/sitemap.xml</code>）·
      提交入口：
      <a href="https://ziyuan.baidu.com/linksubmit/index" target="_blank" rel="noopener noreferrer">百度站长平台</a> ·
      <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Google Search Console</a> ·
      <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener noreferrer">Bing 站长工具</a>
    </p>
  </div>

  <div class="panel">
    <h2>全站名词自定义（把「文章」改成「飞行日志」）</h2>
    <p class="hint" style="margin-bottom:10px">留空则使用默认名词。改完立即生效于全站前台（导航 / 侧边栏 / 列表 / 详情 / 归档）。</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
      <?php foreach ($terms as $k => $v): ?>
      <div><label for="term_<?= $k ?>"><?= fly_e(ucfirst($k)) ?>（默认：<?= fly_e(array('post'=>'文章','comment'=>'留言','category'=>'分类','tag'=>'标签','member'=>'会员','archive'=>'归档','search'=>'搜索')[$k]) ?>）</label>
      <input id="term_<?= $k ?>" name="term_<?= $k ?>" value="<?= fly_e($v) ?>" placeholder="如：飞行日志"></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>自定义 404 页面</h2>
    <label for="page_404_title">404 页标题（留空用默认）</label>
    <input id="page_404_title" name="page_404_title" value="<?= fly_e($page404['title']) ?>" placeholder="页面走丢了">
    <label for="page_404_body">404 页正文（支持简单 HTML，留空用默认）</label>
    <textarea id="page_404_body" name="page_404_body" rows="3" placeholder="你访问的页面不存在或已被删除……"><?= fly_e($page404['body']) ?></textarea>
  </div>

  <div class="panel">
    <h2>Webhook 通知（新留言 / 新文章推送）</h2>
    <label style="font-weight:400"><input type="checkbox" name="webhook" value="1" style="width:auto;margin-right:6px" <?= $feat['webhook'] ? 'checked' : '' ?>>启用 Webhook 推送</label>
    <label for="webhook_url">Webhook 地址（企业微信群机器人 / 钉钉机器人，二选一）</label>
    <input id="webhook_url" name="webhook_url" value="<?= fly_e($webhookUrl) ?>" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=...">
    <p class="hint">有新留言（待审核）或发布新文章时，自动推送一条 Markdown 消息。留空或未启用则不发送。</p>
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
    <p class="hint">返回形如 <code>{"version":"2.1","url":"...","notes":"..."}</code> 的 JSON；配置并启用后，后台仪表盘会自动比对并提示新版本（结果缓存 6 小时）。官方地址：<code>https://www.flylog.zone.id/version.json</code>。</p>
  </div>

  <div class="panel">
    <h2>外观 / 主题（V8.0）</h2>
    <label for="theme_default">默认主题（访客未手动切换时的呈现）</label>
    <select id="theme_default" name="theme_default">
      <?php foreach ($themes as $k => $lbl): ?><option value="<?= fly_e($k) ?>" <?= $themeDefault === $k ? 'selected' : '' ?>><?= fly_e($lbl) ?></option><?php endforeach; ?>
    </select>
    <label style="font-weight:400;margin-top:8px"><input type="checkbox" name="theme_switch" value="1" style="width:auto;margin-right:6px" <?= $allowSwitch ? 'checked' : '' ?>>允许访客切换主题</label>
    <p class="hint">访客可在前台导航处一键切换亮色 / 暗色 / 极光 / 暖阳，选择记忆在浏览器本地；关闭后所有人仅显示上方默认主题。</p>
  </div>

  <div class="panel">
    <h2>运行信息（只读）</h2>
    <p class="kv">当前数据库驱动：<b><?= fly_e(defined('DB_DRIVER') ? DB_DRIVER : 'sqlite') ?></b> · 运行模式：<b><?= fly_e(fly_mode()) ?></b>（模式在 config.php 的 site.mode 修改）· PHP：<b><?= fly_e(PHP_VERSION) ?></b></p>
  </div>

  <div class="form-ops"><button class="btn" type="submit">保存设置</button></div>
</form>
<?php fly_admin_foot(); ?>
