<?php
/**
 * FlyLog V2.0 下载页
 * 展示当前版本、特性、安装指引，并提供完整安装包下载 + MD5 校验。
 */
require __DIR__ . '/config.php';

$zipName = 'FlyLog-V2.0.zip';
$zipPath = __DIR__ . '/downloads/' . $zipName;
$zipUrl = fly_base_url() . 'downloads/' . $zipName;
$hasZip = is_file($zipPath);
$zipSize = $hasZip ? filesize($zipPath) : 0;
$zipMd5 = $hasZip ? md5_file($zipPath) : '';

function fly_hsize($b)
{
    $b = (int)$b;
    if ($b >= 1048576) { return round($b / 1048576, 2) . ' MB'; }
    if ($b >= 1024) { return round($b / 1024, 1) . ' KB'; }
    return $b . ' B';
}

$meta = array('title' => '下载 FlyLog V' . FLY_VERSION . ' - ' . fly_site_name());
echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '下载'))));
?>
<div class="wrap">
  <div class="center-card wide" style="margin-top:10px">
    <div class="dl-hero">
      <div class="v">FlyLog V<?= fly_e(FLY_VERSION) ?></div>
      <p>轻量现代博客系统 · 纯 PHP + PDO · 四数据库 · 开源免费</p>
      <?php if ($hasZip): ?>
        <a class="btn dl-btn" href="<?= fly_e($zipUrl) ?>" download>⬇️ 下载安装包（<?= fly_e(fly_hsize($zipSize)) ?>）</a>
        <p class="hint" style="margin-top:12px">MD5：<code style="user-select:all"><?= fly_e($zipMd5) ?></code></p>
      <?php else: ?>
        <a class="btn dl-btn btn-gray" href="#">安装包打包中，请稍后</a>
        <p class="hint" style="margin-top:12px">完整源码也可直接从项目目录获取。</p>
      <?php endif; ?>
    </div>

    <h2 style="font-size:17px;margin:22px 0 10px">✨ 本版本亮点</h2>
    <div class="feat">
      <div>🗄️ <b>四数据库</b>：MySQL / MariaDB / PgSQL / SQLite，安装时四选一，SQLite 上传即用</div>
      <div>🔌 <b>HOOK 插件机制</b>：Filter/Action 事件挂接，核心与插件解耦</div>
      <div>🎨 <b>模板标签 + 主题</b>：模板引擎 + 可移植主题，换肤不改代码</div>
      <div>⚙️ <b>三模式</b>：静态 HTML / 伪静态 / 动态，可切换</div>
      <div>🔍 <b>SEO 全链路</b>：TDK / sitemap / robots / 面包屑 / OG / 结构化数据</div>
      <div>📌 <b>置顶 + 定时发布 + 草稿</b></div>
      <div>🔐 <b>登录注册 + 权限分级 + IP 级防爆破</b></div>
      <div>💬 <b>留言审核 + 回复楼层</b></div>
      <div>📦 <b>备份导出 / 附件 / 文件缓存 / 只读 JSON API</b></div>
    </div>

    <h2 style="font-size:17px;margin:22px 0 10px">🚀 安装指引</h2>
    <ol style="padding-left:22px;line-height:2;color:#374151;font-size:14px">
      <li>下载并解压安装包。</li>
      <li>上传全部文件到网站根目录（PHP 7.4+，需 PDO 扩展）。</li>
      <li>浏览器访问 <code>install.php</code>，选择数据库（SQLite 无需建库）、注册管理员。</li>
      <li>安装完成后，建议删除 <code>install.php</code>。</li>
    </ol>
    <p class="hint" style="margin-top:14px">系统要求：PHP 7.4+ · PDO 扩展 · 对应数据库驱动（pdo_sqlite / pdo_mysql / pdo_pgsql）· Apache 或 Nginx（伪静态需 rewrite）。</p>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
