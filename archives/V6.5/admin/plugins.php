<?php
/** FlyLog V2.0 插件管理（应用中心迷你来版：安装即启用，可启停） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_plugins');

$dir = FLY_PLUGINS;
$enabledStr = (string)fly_opt('plugins.enabled', '');
$enabled = $enabledStr !== '' ? array_filter(array_map('trim', explode(',', $enabledStr))) : fly_config('plugins.enabled', array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $name = basename((string)($_POST['name'] ?? ''));
    $op = $_POST['op'] ?? '';
    if ($name !== '' && is_dir($dir . '/' . $name)) {
        $cur = $enabled;
        $pos = array_search($name, $cur, true);
        if ($op === 'enable' && $pos === false) {
            $cur[] = $name;
        } elseif ($op === 'disable' && $pos !== false) {
            unset($cur[$pos]);
        }
        fly_set_option('plugins.enabled', implode(',', array_values($cur)));
    }
    fly_redirect('plugins.php');
}

$plugins = array();
foreach (glob($dir . '/*', GLOB_ONLYDIR) as $pd) {
    $name = basename($pd);
    $desc = '';
    $main = $pd . '/' . $name . '.php';
    if (is_file($main)) {
        $head = file_get_contents($main);
        if (preg_match('/描述[:：]\s*(.+)/u', $head, $m)) { $desc = trim($m[1]); }
    }
    $plugins[] = array('name' => $name, 'desc' => $desc, 'enabled' => in_array($name, $enabled, true));
}

fly_admin_head('插件管理', 'plugins');
?>
<h1 style="font-size:22px;margin-bottom:8px">插件管理</h1>
<p class="hint" style="margin-bottom:16px">把插件目录放到 <code>plugins/</code> 即完成「安装」，在这里启用/停用。FlyLog 通过 HOOK（Filter/Action）让插件与核心解耦——这是应用中心的迷你内核。</p>

<?php if (!$plugins): ?>
  <div class="panel"><p style="color:#9ca3af">还没有插件。示例插件 <code>hello-flylog</code> 应出现在下面。</p></div>
<?php else: ?>
  <?php foreach ($plugins as $pl): ?>
  <div class="panel" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <b style="font-size:16px">🔌 <?= fly_e($pl['name']) ?></b>
      <?= $pl['enabled'] ? '<span class="tag tag-ok" style="margin-left:8px">已启用</span>' : '<span class="tag tag-draft" style="margin-left:8px">未启用</span>' ?>
      <?php if ($pl['desc']): ?><p class="hint" style="margin-top:4px"><?= fly_e($pl['desc']) ?></p><?php endif; ?>
    </div>
    <form class="inline-form" method="post" action="plugins.php">
      <?= fly_csrf_field() ?><input type="hidden" name="name" value="<?= fly_e($pl['name']) ?>">
      <input type="hidden" name="op" value="<?= $pl['enabled'] ? 'disable' : 'enable' ?>">
      <button class="btn <?= $pl['enabled'] ? 'btn-gray' : '' ?>" type="submit"><?= $pl['enabled'] ? '停用' : '启用' ?></button>
    </form>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php fly_admin_foot(); ?>
