<?php
/**
 * FlyLog V10.0 主题管理
 * ------------------------------------------------------------------
 * 扫描 themes/ 目录 → 展示主题卡片（截图/名称/版本/作者）
 * → 一键切换 / 恢复默认模板 / 在线编辑 style.css（自动备份 .bak）
 */
require __DIR__ . '/_init.php';
fly_admin_require('manage_settings');

$err = '';
$ok  = '';

// ---------- 动作 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $op   = (string)($_POST['op'] ?? '');
    $name = fly_theme_safe_name((string)($_POST['theme'] ?? ''));

    if ($op === 'activate' && $name !== '') {
        if (fly_theme_set($name)) {
            $ok = '已启用主题：' . $name;
        } else {
            $err = '主题目录不存在：' . $name;
        }
    } elseif ($op === 'reset') {
        fly_theme_reset();
        $ok = '已恢复默认模板（templates/）';
    } elseif ($op === 'save_css' && $name !== '') {
        $css = (string)($_POST['css'] ?? '');
        if (fly_theme_write_style($name, $css)) {
            $ok = 'style.css 已保存（旧版本已备份为 style.css.bak）';
        } else {
            $err = '保存失败：主题目录不可写';
        }
    } elseif ($op === 'restore_css' && $name !== '') {
        $dir = fly_theme_root() . '/' . $name;
        $bak = $dir . '/style.css.bak';
        if (is_file($bak)) {
            @copy($bak, $dir . '/style.css');
            $ok = '已恢复到上一版 style.css';
        } else {
            $err = '没有找到备份文件';
        }
    }
    if ($err === '') {
        fly_redirect('themes.php?done=1&edit=' . urlencode($name));
    }
}

// ---------- 在线编辑 CSS ----------
$edit = fly_theme_safe_name((string)($_GET['edit'] ?? ''));
if ($edit !== '' && is_dir(fly_theme_root() . '/' . $edit)) {
    $theme = fly_theme_info($edit);
    $css   = fly_theme_read_style($edit);
    fly_admin_head('编辑主题样式：' . $theme['name'], 'themes');
    ?>
    <div class="panel">
      <h2 class="sec-title">在线编辑 style.css — <?= fly_e($theme['name']) ?>
        <small style="font-weight:400;color:#6b7280">v<?= fly_e($theme['version']) ?></small>
      </h2>
      <p class="hint">文件：<code>themes/<?= fly_e($edit) ?>/style.css</code>　保存前会自动备份一份 <code>.bak</code>。</p>
      <?php if ($err): ?><p class="alert alert-err"><?= fly_e($err) ?></p><?php endif; ?>
      <?php if ($ok): ?><p class="alert alert-ok"><?= fly_e($ok) ?></p><?php endif; ?>
      <form method="post">
        <?= fly_csrf_field() ?>
        <input type="hidden" name="op" value="save_css">
        <input type="hidden" name="theme" value="<?= fly_e($edit) ?>">
        <textarea name="css" class="css-editor" spellcheck="false" style="width:100%;min-height:420px;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6"><?= fly_e($css) ?></textarea>
        <p style="margin-top:12px">
          <button class="btn" type="submit">保存</button>
          <a class="btn btn-gray" href="themes.php">返回主题列表</a>
        </p>
      </form>
      <form method="post" style="margin-top:8px" onsubmit="return confirm('用上一版备份覆盖当前 style.css？');">
        <?= fly_csrf_field() ?>
        <input type="hidden" name="op" value="restore_css">
        <input type="hidden" name="theme" value="<?= fly_e($edit) ?>">
        <button class="btn btn-sm btn-gray" type="submit" <?= is_file(fly_theme_root() . '/' . $edit . '/style.css.bak') ? '' : 'disabled' ?>>恢复到上一版</button>
      </form>
    </div>
    <?php
    fly_admin_foot();
    exit;
}

// ---------- 列表 ----------
$themes = fly_theme_scan();
$cur    = fly_theme_current();
fly_admin_head('主题', 'themes');
?>
<div class="panel">
  <h2 class="sec-title">主题（<?= count($themes) ?> 个）</h2>
  <p class="hint">
    主题目录：<code>themes/&lt;主题名&gt;/</code>，可含 <code>index.php</code>、<code>post.php</code>、<code>header.php</code>、<code>footer.php</code>、<code>style.css</code>、<code>screenshot.png</code>。
    <br>渲染规则：<strong>主题优先，缺哪个文件就回退到 <code>templates/</code> 默认模板</strong>——只写一个 style.css 的主题也能直接生效。
  </p>
  <?php if ($err): ?><p class="alert alert-err"><?= fly_e($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="alert alert-ok"><?= fly_e($ok) ?></p><?php endif; ?>

  <!-- 默认模板卡片 -->
  <div class="plugin-grid">
    <div class="plugin-card <?= $cur === '' ? 'is-active' : '' ?>">
      <div class="plugin-shot"><div class="plugin-shot-ph">默认模板</div></div>
      <div class="plugin-body">
        <h3 class="plugin-name">默认模板 <span class="plugin-ver">内置</span></h3>
        <p class="plugin-desc">FlyLog 自带模板（templates/），零依赖、永远可用。所有主题都基于它做覆盖。</p>
        <div class="plugin-actions">
          <?php if ($cur === ''): ?>
            <span class="badge badge-on">使用中</span>
          <?php else: ?>
            <form method="post" style="display:inline">
              <?= fly_csrf_field() ?>
              <input type="hidden" name="op" value="reset">
              <button class="btn btn-sm" type="submit">启用默认模板</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php foreach ($themes as $t): ?>
      <div class="plugin-card <?= $t['is_active'] ? 'is-active' : '' ?>">
        <div class="plugin-shot">
          <?php if ($t['screenshot']): ?>
            <img src="<?= fly_e($t['screenshot']) ?>" alt="" loading="lazy">
          <?php else: ?>
            <div class="plugin-shot-ph">暂无截图</div>
          <?php endif; ?>
        </div>
        <div class="plugin-body">
          <h3 class="plugin-name"><?= fly_e($t['name']) ?> <span class="plugin-ver">v<?= fly_e($t['version']) ?></span></h3>
          <p class="plugin-desc"><?= fly_e($t['description']) ?></p>
          <p class="plugin-meta">
            <?= fly_e($t['author']) ?> · <code><?= fly_e($t['slug']) ?></code>
            <br>模板：
            <?php foreach ($t['templates'] as $tk => $has): ?>
              <span class="tpl-flag <?= $has ? 'on' : 'off' ?>"><?= $tk ?><?= $has ? '' : '（回退）' ?></span>
            <?php endforeach; ?>
          </p>
          <div class="plugin-actions">
            <?php if ($t['is_active']): ?>
              <span class="badge badge-on">使用中</span>
            <?php else: ?>
              <form method="post" style="display:inline">
                <?= fly_csrf_field() ?>
                <input type="hidden" name="op" value="activate">
                <input type="hidden" name="theme" value="<?= fly_e($t['slug']) ?>">
                <button class="btn btn-sm" type="submit">启用</button>
              </form>
            <?php endif; ?>
            <?php if ($t['has_style']): ?>
              <a class="btn btn-sm btn-gray" href="themes.php?edit=<?= urlencode($t['slug']) ?>">编辑 CSS</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-gray" href="<?= fly_e(fly_url('home')) ?>" target="_blank">预览前台</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$themes): ?>
    <p style="color:#6b7280;margin-top:14px">themes/ 目录还是空的。新建一个 <code>themes/我的主题/style.css</code> 就会自动出现在这里。</p>
  <?php endif; ?>
</div>
<?php fly_admin_foot(); ?>
