<?php
/**
 * FlyLog V2.0 前台首页（文章列表 + 分类 + 全站搜索 + 分页）
 */
require __DIR__ . '/config.php';

// #141 搜索联想：返回 JSON 标题列表（须在静态快照判断前，避免被 home 快照拦截）
// #12(P3) 长度限制 + 服务端节流（同 IP 10 次/分钟），防全表 LIKE 扫描放大 DoS
if (isset($_GET['suggest'])) {
    $sq = trim((string)($_GET['q'] ?? ''));
    if (fly_len($sq) > 50) { $sq = substr($sq, 0, 50); }
    $rows = array();
    $ipKey = 'suggest:' . fly_client_ip();
    $hits = (int)fly_cache_get($ipKey, 60);
    if ($sq !== '' && $hits < 10) {
        fly_cache_set($ipKey, $hits + 1, 60);
        $rows = fly_db()->all(
            "SELECT id, title FROM posts
             WHERE deleted_at = 0 AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
               AND (title LIKE ? OR content LIKE ?)
             ORDER BY id DESC LIMIT 8",
            array('%' . $sq . '%', '%' . $sq . '%')
        );
    }
    $out = array();
    foreach ($rows as $r) {
        $out[] = array('id' => (int)$r['id'], 'title' => $r['title'], 'url' => fly_url('post', array('id' => $r['id'])));
    }
    fly_json(array('items' => $out));
}

// 静态模式：命中快照直接输出（不连数据库）
// V7.0：分类树/标签/搜索是条件视图，不能复用 home 快照，否则会串味
$__dynamicView = isset($_GET['q']) || isset($_GET['cat']) || isset($_GET['cid']) || isset($_GET['tag']);
if (!$__dynamicView && fly_static_serve('home-' . max(1, (int)($_GET['page'] ?? 1)) . '.html')) {
    exit;
}

$db = fly_db();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$cat = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
$cid = isset($_GET['cid']) ? max(0, (int)$_GET['cid']) : 0;
$tagSlug = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;

// V7.0 分类树：cid 命中分类时，把它所有后代分类的文章一起列出来
$catObj = $cid > 0 ? fly_cat_resolve($cid) : null;
$tagObj = $tagSlug !== '' ? fly_tag_resolve($tagSlug) : null;

$opt = array('limit' => $limit, 'offset' => ($page - 1) * $limit);
if ($catObj)      { $opt['cat_ids'] = fly_cat_subtree_ids((int)$catObj['id']); }
elseif ($cat !== '') { $opt['category'] = $cat; }
if ($tagObj)      { $opt['tag_id'] = (int)$tagObj['id']; }
if ($q !== '')    { $opt['keyword'] = $q; }

$total = BasePost::count($opt);
$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) { $page = $totalPages; }
$opt['offset'] = ($page - 1) * $limit;
$posts = BasePost::all($opt);

// #109 跨模块搜索：关键词同时检索「已通过留言」，作为补充结果展示
$commentHits = array();
if ($q !== '') {
    $commentHits = $db->all(
        "SELECT c.id, c.post_id, c.content, c.created_at, p.title
         FROM comments c LEFT JOIN posts p ON p.id = c.post_id
         WHERE c.status = 1 AND c.content LIKE ?
         ORDER BY c.id DESC LIMIT 10",
        array('%' . $q . '%')
    );
}

$viewLabel = '';   // V7.0：当前归档视图的名字（分类名 / 标签名），用于空态提示
if ($catObj)      { $viewLabel = $catObj['name']; }
elseif ($tagObj)  { $viewLabel = '#' . $tagObj['name']; }
elseif ($cat !== '') { $viewLabel = $cat; }

$items = '';
foreach ($posts as $row) {
    $items .= fly_tpl_post_item($row, $q);
}
if ($items === '') {
    $msg = '这里还没有' . fly_term('post') . '。';
    if ($q !== '')   { $msg = '没有找到与「' . fly_e($q) . '」相关的' . fly_term('post') . '。'; }
    elseif ($viewLabel !== '') {
        $msg = '该' . ($tagObj ? '标签' : fly_term('category'))
            . '（' . fly_e($viewLabel) . '）下暂无' . fly_term('post')
            . '。<br><a href="' . fly_e(fly_url('home')) . '">查看全部</a>';
    }
    $items = '<p class="empty">' . $msg . '</p>';
}

$urlFor = function ($p) use ($cat, $q, $cid, $tagSlug) {
    $u = fly_url('page', array('page' => $p));
    $extra = array();
    if ($cid > 0)         { $extra[] = 'cid=' . $cid; }
    elseif ($cat !== '')  { $extra[] = 'cat=' . rawurlencode($cat); }
    if ($tagSlug !== '')  { $extra[] = 'tag=' . rawurlencode($tagSlug); }
    if ($q !== '')        { $extra[] = 'q=' . rawurlencode($q); }
    if ($extra) { $u .= (strpos($u, '?') !== false ? '&' : '?') . implode('&', $extra); }
    return $u;
};

// SEO + 面包屑
if ($q !== '') {
    $title = '搜索：' . $q . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '搜索：' . $q));
} elseif ($catObj) {
    // V7.0 分类树：面包屑按「祖先链」逐级展开，父分类可点回去
    $title = fly_term('category') . '：' . $catObj['name'] . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')));
    $chain = fly_cat_path((int)$catObj['id']);
    $last = count($chain) - 1;
    foreach ($chain as $i => $c) {
        $bc[] = ($i === $last)
            ? array('name' => $c['name'])
            : array('name' => $c['name'], 'url' => fly_cat_url($c));
    }
} elseif ($tagObj) {
    $title = '标签：' . $tagObj['name'] . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '标签：' . $tagObj['name']));
} elseif ($cat !== '') {
    $title = '分类：' . $cat . ' - ' . fly_config('site.name', 'FlyLog');
    $bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '分类：' . $cat));
} else {
    $title = fly_config('site.name', 'FlyLog') . ' - ' . fly_config('site.desc', '');
    $bc = array(array('name' => '首页'));
}
$description = fly_config('site.desc', '');
if ($catObj && !empty($catObj['description'])) {
    $description = $catObj['description'];
} elseif ($tagObj) {
    $description = '标签「' . $tagObj['name'] . '」下的全部' . fly_term('post') . '。';
}
$meta = array(
    'title' => $title,
    'description' => $description,
    'keywords' => fly_config('site.keywords', ''),
    'breadcrumb' => $bc,
    'jsonld' => array(
        array(
            '@type' => 'WebSite',
            'name' => fly_config('site.name', 'FlyLog'),
            'url' => fly_base_url(),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => rtrim(fly_base_url(), '/') . '/?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ),
        ),
        array(
            '@type' => 'Blog',
            'name' => fly_config('site.name', 'FlyLog'),
            'url' => fly_base_url(),
        ),
    ),
);

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc, 'note' => fly_config('site.desc', '')));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col" id="fly-main">
      <?= $items ?>
      <?php if (!empty($commentHits)): ?>
      <div class="search-extra">
        <h2 class="sec-title">留言中命中「<?= fly_e($q) ?>」（<?= count($commentHits) ?>）</h2>
        <ul class="cmt-hits">
          <?php foreach ($commentHits as $ch): ?>
            <li>
              <?php if ($ch['post_id']): ?><a href="<?= fly_e(fly_url('post', array('id' => $ch['post_id']))) ?>#comment-<?= (int)$ch['id'] ?>">@<?= fly_e($ch['title']) ?></a>：<?php endif; ?>
              <?= fly_e(fly_excerpt($ch['content'], 80)) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?= fly_pager($page, $totalPages, $urlFor) ?>
  </div>
    <?= fly_render('parts/sidebar', array('q' => $q)) ?>
  </div>
</div>
<?php echo fly_render('parts/footer'); ?>
