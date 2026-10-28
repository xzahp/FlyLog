<?php
/**
 * FlyLog V7.0 单页（关于我 / 友链这类独立页面）
 * 与文章的区别：不进首页时间流、不进 RSS、不进 sitemap 的 article 段、无留言板。
 */
require __DIR__ . '/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$page = $slug !== '' ? fly_page_by_slug($slug) : null;

if (!$page) {
    http_response_code(404);
    $meta = array('title' => '页面不存在 - ' . fly_config('site.name', 'FlyLog'));
    echo fly_render('parts/head', compact('meta'));
    echo fly_render('parts/header', array('breadcrumb' => array(
        array('name' => '首页', 'url' => fly_url('home')),
        array('name' => '404'),
    )));
    echo '<div class="wrap"><p class="empty">页面不存在或尚未发布。<br><a href="' . fly_e(fly_url('home')) . '">返回首页</a></p></div>';
    echo fly_render('parts/footer');
    exit;
}

// 与文章正文同一条处理链：外链 nofollow、中英文空格、TOC 锚点
$toc = fly_build_toc($page['content']);
$body = fly_process_content($toc['content']);

$bc = array(
    array('name' => '首页', 'url' => fly_url('home')),
    array('name' => $page['title']),
);
$meta = array(
    'title' => $page['title'] . ' - ' . fly_config('site.name', 'FlyLog'),
    'description' => fly_excerpt($page['content'], 120),
    'type' => 'article',
    'first_image' => fly_first_image($page['content']),
    'breadcrumb' => $bc,
    'jsonld' => array(
        '@type' => 'WebPage',
        'name' => $page['title'],
        'url' => rtrim(fly_base_url(), '/') . '/page.php?slug=' . rawurlencode($page['slug']),
    ),
);

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col" id="fly-main">
      <article class="post-full">
        <h1><?= fly_e($page['title']) ?></h1>
        <p class="meta"><?= fly_e($page['updated_at']) ?></p>
        <?php if (!empty($toc['toc'])): ?>
        <nav class="toc" aria-label="页面目录">
          <div class="toc-title">📑 目录</div>
          <ul>
            <?php foreach ($toc['toc'] as $t): ?>
              <li class="lvl-<?= (int)$t['level'] ?>"><a href="#<?= fly_e($t['id']) ?>"><?= fly_e($t['text']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
        <?php endif; ?>
        <div class="post-body"><?= $body ?></div>
        <a class="back-link" href="<?= fly_e(fly_url('home')) ?>">&laquo; 返回首页</a>
      </article>
    </div>
    <?= fly_render('parts/sidebar') ?>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
