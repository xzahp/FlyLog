<?php
/**
 * FlyLog V2.0 文章详情页 + 留言板（阅读量 + 留言审核 + 回复楼层）
 */
require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if (fly_static_serve('post-' . $id . '.html')) {
    exit;
}

$db = fly_db();
$err = '';
$ok = '';

// ---------- 留言提交 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $author = trim((string)($_POST['author'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $parentId = (int)($_POST['parent_id'] ?? 0);
    if ($id <= 0 || !BasePost::find($id)) {
        $err = '文章不存在，无法留言。';
    } elseif ($author === '' || $content === '') {
        $err = '昵称和留言内容不能为空。';
    } elseif (fly_len($author) > 100 || fly_len($content) > 2000) {
        $err = '昵称不超过 100 字，留言内容不超过 2000 字。';
    } else {
        $status = fly_config('features.comment_audit', true) ? 0 : 1; // 先审后发
        $cid = $db->insert('comments', array(
            'post_id' => $id,
            'parent_id' => $parentId > 0 ? $parentId : 0,
            'author' => $author,
            'content' => $content,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        fly_do_action('fly_comment_posted', $cid, $id);
        fly_redirect(fly_url('post', array('id' => $id)) . '?submitted=1');
    }
}

// ---------- 读取文章 + 阅读量 ----------
$post = BasePost::find($id, true);
if (!$post) {
    http_response_code(404);
    $meta = array('title' => '文章不存在 - ' . fly_config('site.name', 'FlyLog'));
    echo fly_render('parts/head', compact('meta'));
    echo fly_render('parts/header', array('breadcrumb' => array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '404'))));
    echo '<div class="wrap"><p class="empty">文章不存在或已被删除。<br><a href="' . fly_e(fly_url('home')) . '">返回首页</a></p></div>';
    echo fly_render('parts/footer');
    exit;
}
if (empty($post['is_draft']) && !defined('FLY_BUILDING')) {
    // 会话去重：同一会话对同一文章只计一次
    if (empty($_SESSION['fly_viewed'])) { $_SESSION['fly_viewed'] = array(); }
    if (!in_array($id, $_SESSION['fly_viewed'], true)) {
        BasePost::incrementViews($id);
        $_SESSION['fly_viewed'][] = $id;
        $post['views'] = (int)$post['views'] + 1;
    }
}

// ---------- 留言列表（按楼层父子结构） ----------
$all = BaseComment::forPost($id, true);
$byParent = array();
foreach ($all as $c) {
    $byParent[(int)$c['parent_id']][] = $c;
}
$renderComments = function ($parentId, $depth) use (&$renderComments, $byParent) {
    if (empty($byParent[$parentId])) { return ''; }
    $html = '';
    foreach ($byParent[$parentId] as $c) {
        $cls = $depth > 0 ? 'c-item c-reply' : 'c-item';
        $html .= '<div class="' . $cls . '">'
            . '<p class="c-meta"><b>' . fly_e($c['author']) . '</b> · ' . fly_e($c['created_at'])
            . ($depth > 0 ? ' · 回复' : '') . '</p>'
            . '<p class="c-body">' . fly_e($c['content']) . '</p>'
            . (fly_config('features.comment_reply', true) && $depth < 3
                ? '<p class="c-meta"><a href="#" onclick="return fly_reply(' . (int)$c['id'] . ',\'' . fly_e(addslashes($c['author'])) . '\')">回复</a></p>'
                : '')
            . '</div>';
        $html .= $renderComments((int)$c['id'], $depth + 1);
    }
    return $html;
};
$clist = $renderComments(0, 0);
$commentCount = count($all);

$catLine = ($post['category'] !== '' && $post['category'] !== null)
    ? ' · <a href="' . fly_e(fly_url('category', array('cat' => $post['category']))) . '">' . fly_e($post['category']) . '</a>'
    : '';
$pinBadge = !empty($post['is_pinned']) ? '<span class="pin">📌 置顶</span>' : '';
$draftBadge = !empty($post['is_draft']) ? '<span class="tag tag-draft">草稿</span>' : '';

$meta = array(
    'title' => fly_e($post['title']) . ' - ' . fly_config('site.name', 'FlyLog'),
    'description' => fly_excerpt($post['content'], 120),
    'type' => 'article',
    'jsonld' => array(
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post['title'],
        'datePublished' => $post['created_at'],
        'author' => array('@type' => 'Person', 'name' => fly_config('site.name', 'FlyLog')),
    ),
);
$bc = array(
    array('name' => '首页', 'url' => fly_url('home')),
    array('name' => $post['title']),
);

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col">
      <article class="post-full">
        <h1><?= fly_e($post['title']) ?><?= $pinBadge ?><?= $draftBadge ?></h1>
        <p class="meta"><?= fly_e($post['created_at']) ?><?= $catLine ?> · 阅读 <?= (int)$post['views'] ?></p>
        <div class="post-body"><?= $post['content'] /* 作者书写的 HTML，已由编辑器产生 */ ?></div>
        <a class="back-link" href="<?= fly_e(fly_url('home')) ?>">&laquo; 返回首页</a>
      </article>

      <section class="comments">
        <h3>留言板（<?= $commentCount ?>）</h3>
        <?= $clist ?>
        <?php if ($commentCount === 0): ?><p class="empty">还没有留言，来抢沙发~</p><?php endif; ?>

        <?php if (isset($_GET['submitted'])): ?>
          <div class="msg msg-ok"><?= fly_config('features.comment_audit', true) ? '留言已提交，经博主审核后显示，谢谢！' : '留言发布成功！' ?></div>
        <?php endif; ?>
        <?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

        <form class="c-form" method="post" action="<?= fly_e(fly_url('post', array('id' => $id))) ?>">
          <?= fly_csrf_field() ?>
          <input type="hidden" name="parent_id" id="parent_id" value="0">
          <label for="author">昵称</label>
          <input id="author" name="author" maxlength="100" value="<?= fly_e($_POST['author'] ?? '') ?>" placeholder="怎么称呼你？">
          <label for="content">留言内容 <span id="reply-to" style="color:#2563eb;font-weight:400"></span></label>
          <textarea id="content" name="content" maxlength="2000" placeholder="说点什么吧……"><?= fly_e($_POST['content'] ?? '') ?></textarea>
          <div class="form-ops">
            <button class="btn" type="submit">提交留言</button>
            <button class="btn btn-gray" type="button" id="cancel-reply" style="display:none" onclick="return fly_cancel_reply()">取消回复</button>
          </div>
          <p class="c-tip">留言经博主审核后显示，请文明发言。</p>
        </form>
      </section>
    </div>
    <?= fly_render('parts/sidebar') ?>
  </div>
</div>
<script>
function fly_reply(cid, author){
  document.getElementById('parent_id').value = cid;
  document.getElementById('reply-to').textContent = '（回复 @' + author + '）';
  document.getElementById('cancel-reply').style.display = 'inline-block';
  document.getElementById('content').focus();
  return false;
}
function fly_cancel_reply(){
  document.getElementById('parent_id').value = 0;
  document.getElementById('reply-to').textContent = '';
  document.getElementById('cancel-reply').style.display = 'none';
  return false;
}
</script>
<?php echo fly_render('parts/footer'); ?>
