<?php
/**
 * FlyLog V2.0 前台首页（文章列表 + 分类 + 全站搜索 + 分页）
 */
require __DIR__ . '/config.php';

// 静态模式：命中快照直接输出（不连数据库）
if (fly_static_serve('home-' . max(1, (int)($_GET['page'] ?? 1)) . '.html')) {
    exit;
}

$db = fly_db();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$cat = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;

$opt = array('limit' => $limit, 'offset' => ($page - 1) * $limit);
if ($cat !== '') { $opt['category'] = $cat; }
if ($q !== '')   { $opt['keyword'] = $q; }

$total = BasePost::count($opt);
$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) { $page = $totalPages; }
$opt['offset'] = ($page - 1) * $limit;
$posts = BasePost::all($opt);

$items = '';
foreach ($posts as $row) {
    $items .= fly_tpl_post_item($row, $q);
}
if ($items === '') {
    $msg = '这里还没有文章。';
    if ($q !== '')   { $msg = '没有找到与「' . fly_e($q) . '」相关的文章。'; }
    if ($cat !== '') { $msg = '该分类下暂无文章。<br><a href="' . fly_e(fly_url('home')) . '">查看全部</a>'; }
    $items = '<p class="empty">' . $msg . '</p>';
}

$urlFor = function ($p) use ($cat, $q) {
    $u = fly_url('page', array('page' => $p));
    $extra = array();
    if ($cat !== '') { $extra[] = 'cat=' . rawurlencode($cat); }
    if ($q !== '')   { $extra[] = 'q=' . rawurlencode($q); }
    if ($extra) { $u .= (strpos($u, '?') !== false ? '&' : '?') . implode('&', $extra); }
    return $u;
};

// SEO + 面包屑
if ($q !== '') {
    $title = '搜索：' . $q . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '搜索：' . $q));
} elseif ($cat !== '') {
    $title = '分类：' . $cat . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '分类：' . $cat));
} else {
    $title = fly_config('site.name', 'FlyLog') . ' - ' . fly_config('site.desc', '');
    $bc = array(array('name' => '首页'));
}
$meta = array(
    'title' => $title,
    'description' => fly_config('site.desc', ''),
    'keywords' => fly_config('site.keywords', ''),
    'jsonld' => array(
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => fly_config('site.name', 'FlyLog'),
        'url' => fly_base_url(),
    ),
);

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc, 'note' => fly_config('site.desc', '')));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col">
      <?= $items ?>
      <?= fly_pager($page, $totalPages, $urlFor) ?>
    </div>
    <?= fly_render('parts/sidebar', array('q' => $q)) ?>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
