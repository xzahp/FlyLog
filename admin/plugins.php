<?php
/**
 * FlyLog V10.0 插件管理
 * ------------------------------------------------------------------
 * 扫描 plugins/ 目录 → 展示插件卡片（截图/名称/版本/作者/分类/价格）
 * → 启用 / 停用 / 卸载 / 设置 / ZIP 上传安装
 * 全部原生 PHP，不依赖任何第三方库。
 */
require __DIR__ . '/_init.php';
fly_admin_require('manage_plugins');

$err = '';
$ok  = '';

// ---------- 处理动作 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $op   = (string)($_POST['op'] ?? '');
    $slug = fly_plugin_safe_name((string)($_POST['slug'] ?? ''));

    if ($op === 'activate' && $slug !== '') {
        if (!is_dir(fly_plugin_dir($slug))) {
            $err = '插件目录不存在：' . $slug;
        } elseif (fly_plugin_activate($slug)) {
            $ok = '已启用插件：' . $slug;
        } else {
            $err = '启用失败（数据库不可写？）';
        }
    } elseif ($op === 'deactivate' && $slug !== '') {
        fly_plugin_deactivate($slug);
        $ok = '已停用插件：' . $slug;
    } elseif ($op === 'uninstall' && $slug !== '') {
        $remove = !empty($_POST['remove_files']);
        fly_plugin_uninstall($slug, $remove);
        $ok = '已卸载插件：' . $slug . ($remove ? '（文件已删除）' : '（文件保留，可重新安装）');
    } elseif ($op === 'upload') {
        // ZIP 安装：校验上传 + 后缀 + 大小（上限 10MB）
        if (empty($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
            $err = '上传失败（错误码 ' . (int)($_FILES['zipfile']['error'] ?? -1) . '）';
        } else {
            $f = $_FILES['zipfile'];
            if ($f['size'] > 10 * 1024 * 1024) {
                $err = '插件包超过 10MB';
            } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'zip') {
                $err = '请上传 .zip 格式的插件包';
            } else {
                $msg = '';
                $name = fly_plugin_install_zip($f['tmp_name'], $msg);
                if ($name === false) {
                    $err = '安装失败：' . $msg;
                } else {
                    $ok = '插件已安装：' . $name . '（默认停用，记得点启用）';
                }
            }
        }
    }
    if ($err === '' && $op !== 'upload') {
        fly_redirect('plugins.php?done=1');
    }
}

// ---------- 插件配置界面（?configure=slug 时加载插件自己的 config.php） ----------
$configure = fly_plugin_safe_name((string)($_GET['configure'] ?? ''));
if ($configure !== '' && is_dir(fly_plugin_dir($configure))) {
    $info = fly_plugin_info($configure);
    $cfgFile = fly_plugin_dir($configure) . '/config.php';
    if (!is_file($cfgFile)) {
        fly_redirect('plugins.php');
    }
    fly_admin_head('插件设置：' . $info['name'], 'plugins');
    ?>
    <div class="panel">
      <h2 class="sec-title">插件设置：<?= fly_e($info['name']) ?> <small style="font-weight:400;color:#6b7280">v<?= fly_e($info['version']) ?> · <?= fly_e($info['author']) ?></small></h2>
      <p class="hint" style="margin:6px 0 14px"><?= fly_e($info['description']) ?></p>
      <?php
      // 插件的 config.php 自己负责表单与保存（可用 fly_plugin_opt / fly_plugin_set_opt）
      include $cfgFile;
      ?>
      <p style="margin-top:16px"><a class="btn btn-gray" href="plugins.php">← 返回插件列表</a></p>
    </div>
    <?php
    fly_admin_foot();
    exit;
}

// ---------- 列表 ----------
$plugins = fly_plugin_scan();
$activeCount = 0;
foreach ($plugins as $p) {
    if ($p['status'] === 'active') { $activeCount++; }
}
$zipOk = class_exists('ZipArchive') ? 'ZipArchive' : '纯 PHP 解压';

fly_admin_head('插件', 'plugins');
?>
<div class="panel">
  <h2 class="sec-title">插件（<?= count($plugins) ?> 个，已启用 <?= $activeCount ?> 个）</h2>
  <p class="hint">
    插件目录：<code>plugins/&lt;插件名&gt;/plugin.php</code>。启用后由内核在引导阶段自动加载，
    插件通过 <code>fly_add_action()</code> / <code>fly_add_filter()</code> 挂到系统各埋点上。
    解压方式：<?= fly_e($zipOk) ?>。
  </p>
  <?php if ($err): ?><p class="alert alert-err"><?= fly_e($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="alert alert-ok"><?= fly_e($ok) ?></p><?php endif; ?>

  <!-- 上传安装 -->
  <form method="post" enctype="multipart/form-data" style="margin:12px 0;padding:12px;border:1px dashed var(--border);border-radius:8px">
    <?= fly_csrf_field() ?>
    <input type="hidden" name="op" value="upload">
    <strong>安装新插件（ZIP）</strong>
    <input type="file" name="zipfile" accept=".zip" required style="margin:0 8px">
    <button class="btn btn-sm" type="submit">上传并安装</button>
    <span class="hint" style="margin-left:8px">包内需含 plugin.php；同名插件会被覆盖；安装后默认停用</span>
  </form>

  <?php if (!$plugins): ?>
    <p style="color:#6b7280;margin-top:14px">plugins/ 目录下还没有插件。上传一个 .zip 试试，或手动放入目录。</p>
  <?php else: ?>
  <div class="plugin-grid">
    <?php foreach ($plugins as $p): ?>
      <div class="plugin-card <?= $p['status'] === 'active' ? 'is-active' : '' ?>">
        <div class="plugin-shot">
          <?php if ($p['screenshot']): ?>
            <img src="<?= fly_e($p['screenshot']) ?>" alt="" loading="lazy">
          <?php else: ?>
            <div class="plugin-shot-ph">暂无截图</div>
          <?php endif; ?>
        </div>
        <div class="plugin-body">
          <h3 class="plugin-name"><?= fly_e($p['name']) ?>
            <span class="plugin-ver">v<?= fly_e($p['version']) ?></span>
          </h3>
          <p class="plugin-desc"><?= fly_e($p['description']) ?></p>
          <p class="plugin-meta">
            <?= fly_e($p['author']) ?>
            · <?= fly_e($p['category']) ?>
            · <span class="price-tag"><?= strtolower($p['price']) === 'free' || $p['price'] === '免费' ? '免费' : fly_e($p['price']) ?></span>
            · <code><?= fly_e($p['slug']) ?></code>
          </p>
          <div class="plugin-actions">
            <?php if ($p['status'] === 'active'): ?>
              <form method="post" style="display:inline">
                <?= fly_csrf_field() ?>
                <input type="hidden" name="op" value="deactivate">
                <input type="hidden" name="slug" value="<?= fly_e($p['slug']) ?>">
                <button class="btn btn-sm btn-gray" type="submit">停用</button>
              </form>
              <span class="badge badge-on">已启用</span>
            <?php else: ?>
              <form method="post" style="display:inline">
                <?= fly_csrf_field() ?>
                <input type="hidden" name="op" value="activate">
                <input type="hidden" name="slug" value="<?= fly_e($p['slug']) ?>">
                <button class="btn btn-sm" type="submit" <?= $p['has_main'] ? '' : 'disabled title="缺少 plugin.php"' ?>>启用</button>
              </form>
            <?php endif; ?>

            <?php if ($p['has_config']): ?>
              <a class="btn btn-sm btn-gray" href="plugins.php?configure=<?= urlencode($p['slug']) ?>">设置</a>
            <?php endif; ?>

            <form method="post" style="display:inline" onsubmit="return confirm('确定卸载「<?= fly_e($p['name']) ?>」？\n勾选「同时删除文件」会连插件目录一起删掉。');">
              <?= fly_csrf_field() ?>
              <input type="hidden" name="op" value="uninstall">
              <input type="hidden" name="slug" value="<?= fly_e($p['slug']) ?>">
              <label class="hint" style="margin-right:6px"><input type="checkbox" name="remove_files" value="1" style="width:auto;margin-right:4px">同时删除文件</label>
              <button class="btn btn-sm btn-danger" type="submit">卸载</button>
            </form>
          </div>
          <?php if (!$p['has_main']): ?>
            <p class="hint" style="color:#b91c1c">缺少 plugin.php，无法加载</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel">
  <h2 class="sec-title">开发者：插件怎么写</h2>
  <p class="hint">最小可用插件（<code>plugins/hello/plugin.php</code>）：</p>
<pre class="code-block"><code>&lt;?php
/*
Plugin Name: Hello FlyLog
Version: 1.0.0
Description: 在页脚说句话
Author: 小周
Category: 示例
Price: free
*/

// 挂到页脚埋点：核心执行到 fly_do_action('fly_foot') 时就会调用它
fly_add_action('fly_foot', function () {
    echo '&lt;p style="text-align:center;color:#888"&gt;Hello FlyLog V10.0！&lt;/p&gt;';
});

// 挂到正文过滤点：可以改写文章内容
// 回调签名 ($html, $post)，$post 是当前文章行，文章页/列表页都会走
fly_add_filter('the_content', function ($html, $post) {
    return $html;
});</code></pre>
  <p class="hint" style="margin-top:10px">可用埋点：<code>fly_init</code>、<code>fly_plugins_loaded</code>、<code>fly_head</code>、<code>fly_foot</code>、<code>fly_admin_head</code>、<code>fly_admin_menu</code>、<code>before_front_render</code>、<code>pre_post_save</code>、<code>post_saved</code>、<code>fly_comment_posted</code>、<code>the_content</code>。</p>
  <p class="hint" style="margin-top:6px">完整开发规范（目录约定 / 钩子总表 / 可用 API 白名单 / 配置页契约 / 自检清单）：<code>docs/PLUGIN-SPEC.md</code>。把这份文件交给任意 AI，它就能按规范写出可直接跑的插件。</p>
</div>
<?php fly_admin_foot(); ?>
