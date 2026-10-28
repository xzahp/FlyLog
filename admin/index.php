<?php
/** FlyLog V2.0 后台仪表盘 */
require __DIR__ . '/_init.php';

// #107 缓存一键清理（POST + CSRF）
$ok = '';
if (isset($_POST['clear_cache'])) {
    fly_csrf_check();
    $n = fly_cache_clear();
    $ok = '已清理 ' . $n . ' 个缓存文件。';
}

// V7.0 第三批：在线升级检查（仅在已启用且配置了地址时才发起网络请求，结果缓存 6h）
$upgradeInfo = null;
if (fly_feature('upgrade_check', false) && trim((string)fly_opt('upgrade.check_url', '')) !== '') {
    try { $upgradeInfo = fly_upgrade_check(); } catch (Throwable $e) { $upgradeInfo = null; }
}

$db = fly_db();
$postTotal = (int)$db->value("SELECT COUNT(*) FROM posts WHERE deleted_at = 0");
$postPub = (int)$db->value("SELECT COUNT(*) FROM posts WHERE deleted_at = 0 AND is_draft = 0");
$postDraft = (int)$db->value("SELECT COUNT(*) FROM posts WHERE deleted_at = 0 AND is_draft = 1");
$pending = (int)$db->value("SELECT COUNT(*) FROM comments WHERE status = 0");
$commentTotal = (int)$db->value("SELECT COUNT(*) FROM comments WHERE status = 1");
$catCount = (int)$db->value("SELECT COUNT(DISTINCT category) FROM posts WHERE category <> '' AND deleted_at = 0");
$views = (int)$db->value("SELECT SUM(views) FROM posts WHERE deleted_at = 0");
$trashCount = (int)$db->value("SELECT COUNT(*) FROM posts WHERE deleted_at > 0");
$fail24 = (int)$db->value("SELECT COUNT(*) FROM admin_logs WHERE success = 0 AND created_at >= ?", array(date('Y-m-d H:i:s', time() - 86400)));

$recent = BasePost::all(array('limit' => 5));
$recentPending = $db->table('comments')->where('status', 0)->order('id', 'DESC')->limit(5)->all();

fly_admin_head('仪表盘', 'index');
?>
<h1 style="font-size:22px;margin-bottom:18px">仪表盘</h1>
<p class="hint" style="margin-bottom:18px">你好，<b><?= fly_e($_SESSION['fly_admin_name']) ?></b>（角色：<?= fly_e(fly_admin_role()) ?>）</p>
<?php if ($ok !== ''): ?><div class="msg msg-ok"><?= fly_e($ok) ?></div><?php endif; ?>
<?php if ($upgradeInfo): ?>
<div class="msg msg-warn">
  🎉 发现新版本 <b>FlyLog <?= fly_e($upgradeInfo['latest']) ?></b>（你当前 <?= fly_e($upgradeInfo['current']) ?>）。
  <?php if ($upgradeInfo['url']): ?><a href="<?= fly_e($upgradeInfo['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:#b45309;font-weight:600">前往下载 &raquo;</a><?php endif; ?>
  <?php if ($upgradeInfo['notes']): ?><div style="margin-top:6px;font-size:13px;line-height:1.6"><?= fly_e($upgradeInfo['notes']) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="cards">
  <div class="card"><div class="num"><?= $postTotal ?></div><div class="label">文章总数</div></div>
  <div class="card"><div class="num"><?= $postPub ?></div><div class="label">已发布</div></div>
  <div class="card"><div class="num"><?= $postDraft ?></div><div class="label">草稿</div></div>
  <div class="card"><div class="num"><?= $pending ?></div><div class="label">待审核留言</div></div>
  <div class="card"><div class="num"><?= $commentTotal ?></div><div class="label">已通过留言</div></div>
  <div class="card"><div class="num"><?= $catCount ?></div><div class="label">分类数</div></div>
  <div class="card"><div class="num"><?= $views ?></div><div class="label">总阅读量</div></div>
  <div class="card"><div class="num" style="<?= $trashCount ? 'color:var(--warn)' : '' ?>"><a href="trash.php" style="color:inherit"><?= $trashCount ?></a></div><div class="label">回收站</div></div>
  <div class="card"><div class="num" style="color:<?= $fail24 ? 'var(--err)' : 'var(--brand)' ?>"><?= $fail24 ?></div><div class="label">24h 登录失败</div></div>
</div>

<div class="quick">
  <a href="post.php"><div class="qi">✏️</div><div class="qt">写新文章</div><div class="qd">发布一篇新博客</div></a>
  <a href="posts.php"><div class="qi">📄</div><div class="qt">文章管理</div><div class="qd">编辑 / 置顶 / 定时 / 删除</div></a>
  <a href="comments.php"><div class="qi">💬</div><div class="qt">留言审核</div><div class="qd"><?= $pending ? $pending . ' 条待处理' : '暂无待审核' ?></div></a>
  <a href="settings.php"><div class="qi">⚙️</div><div class="qt">站点设置</div><div class="qd">TDK / SEO / 运行模式</div></a>
</div>

<div class="panel">
  <h2>缓存与维护</h2>
  <p class="hint">当页面显示异常或改了配置，可手动清空文件缓存后刷新。</p>
  <form method="post" action="">
    <?= fly_csrf_field() ?>
    <button class="btn" type="submit" name="clear_cache" value="1">🧹 清空文件缓存</button>
  </form>
</div>

<div class="panel">
  <h2>最近文章 <a href="posts.php" style="font-size:13px;font-weight:400">进入管理 &raquo;</a></h2>
  <table class="tbl"><thead><tr><th>标题</th><th>分类</th><th>状态</th><th>阅读</th><th>时间</th></tr></thead><tbody>
  <?php foreach ($recent as $p): ?>
    <tr>
      <td><a href="post.php?id=<?= (int)$p['id'] ?>"><?= fly_e($p['title']) ?></a></td>
      <td><?= fly_e($p['category']) ?></td>
      <td><?= !empty($p['is_draft']) ? '<span class="tag tag-draft">草稿</span>' : '<span class="tag tag-ok">已发布</span>' ?><?= !empty($p['is_pinned']) ? ' <span class="tag tag-wait">置顶</span>' : '' ?></td>
      <td><?= (int)$p['views'] ?></td>
      <td style="color:#9ca3af;font-size:13px"><?= fly_e($p['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="5" style="color:#9ca3af">还没有文章</td></tr><?php endif; ?>
  </tbody></table>
</div>

<?php if ($recentPending): ?>
<div class="panel">
  <h2>待审核留言 <a href="comments.php" style="font-size:13px;font-weight:400">去处理 &raquo;</a></h2>
  <table class="tbl"><thead><tr><th>作者</th><th>内容</th><th>时间</th></tr></thead><tbody>
  <?php foreach ($recentPending as $c): ?>
    <tr><td><?= fly_e($c['author']) ?></td><td><?= fly_e(fly_excerpt($c['content'], 60)) ?></td><td style="color:#9ca3af;font-size:13px"><?= fly_e($c['created_at']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php fly_admin_foot(); ?>
