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
        // #231 Webhook：新留言推送到企业微信/钉钉（未配置则静默跳过）
        fly_notify(
            '新' . fly_term('comment') . ($status === 0 ? '待审核' : ''),
            '文章 #' . $id . ' 收到新' . fly_term('comment') . '：' . fly_excerpt($content, 80)
            . '\n作者：' . $author
            . '\n[前往后台审核](' . rtrim(fly_base_url(), '/') . '/admin/comments.php)'
        );
        fly_redirect(fly_url('post', array('id' => $id)) . '?submitted=1');
    }
}

// ---------- 读取文章 + 阅读量 ----------
// V9.0 草稿预览：管理员已登录、或带有效预览令牌时放行（能看到草稿 / 定时未到的文章）；
// 普通访客走原逻辑，find() 第二个参数为 false，草稿依旧是 404，不会被意外泄露。
$isPreview = function_exists('fly_preview_allowed') ? fly_preview_allowed($id) : false;
$post = BasePost::find($id, $isPreview);
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

// V7.0：分类走分类树（有层级面包屑），老库回落字符串
$catLine = fly_cat_label($post);
$tagLine = fly_tag_links($id);   // V7.0 标签
// V7.0 第二批：当前访客是否已赞过（按 IP 去重）
$liked = fly_post_like_state((int)$id, fly_client_ip());
$readMin = fly_reading_time($post['content']);
$tocData = fly_build_toc($post['content']);
$post['content'] = fly_process_content($tocData['content']);   // #156 外链 nofollow + #172 中英文空格
$post['content'] = fly_auto_internal_links($post['content'], $id);   // #V8.5 文章内链自动解析
$pinBadge = !empty($post['is_pinned']) ? '<span class="pin">📌 置顶</span>' : '';
$draftBadge = !empty($post['is_draft']) ? '<span class="tag tag-draft">草稿</span>' : '';
// V9.0：尚未对外可见（草稿 或 定时发布时间未到）——只有预览态才可能看到，需要给出醒目提示
$notPublished = !empty($post['is_draft'])
    || (!empty($post['publish_at']) && strtotime((string)$post['publish_at']) > time());

$bc = array(array('name' => '首页', 'url' => fly_url('home')));
// V7.0：文章页面包屑插入分类祖先链（首页 > 父分类 > 子分类 > 标题）
foreach (fly_cat_path((int)($post['category_id'] ?? 0)) as $c) {
    $bc[] = array('name' => $c['name'], 'url' => fly_cat_url($c));
}
$bc[] = array('name' => $post['title']);
$ogImg = fly_og_image(array('first_image' => fly_first_image($post['content'])));

// V9.0：SEO 字段优先级最高，留空则回落「文章标题 - 站点名」+ 自动摘要，老文章零影响。
// 说明：SEO 标题不再追加站点名——后台预览里看到什么，搜索结果就是什么（所见即所得）。
// 列可能还没迁移出来（老库升级中途），用 ?? '' 兜底，不会报 undefined index。
$seoTitle = trim((string)($post['seo_title'] ?? ''));
$seoDesc = trim((string)($post['seo_desc'] ?? ''));
$seoKeywords = trim((string)($post['seo_keywords'] ?? ''));

$meta = array(
    // 不再在这里 fly_e：fly_tpl_seo 输出时统一转义，避免标题里出现 &amp; 这类双重转义
    'title' => ($seoTitle !== '') ? $seoTitle : ($post['title'] . ' - ' . fly_config('site.name', 'FlyLog')),
    'description' => ($seoDesc !== '') ? $seoDesc : fly_excerpt($post['content'], 120),
    'type' => 'article',
    'first_image' => fly_first_image($post['content']),
    'breadcrumb' => $bc,
    'jsonld' => array(
        '@type' => 'BlogPosting',
        'headline' => $post['title'],
        'datePublished' => $post['created_at'],
        'author' => array('@type' => 'Person', 'name' => fly_config('site.name', 'FlyLog')),
    ),
);
if ($seoKeywords !== '') {
    $meta['keywords'] = $seoKeywords;   // 留空则用站点默认关键词（fly_tpl_seo 已兜底）
}
// V9.0：草稿预览页禁止搜索引擎收录，避免未定稿内容被抓走（head 模板据此输出 robots）
if (!empty($post['is_draft'])) {
    $meta['noindex'] = true;
}
if ($ogImg !== '') {
    $meta['jsonld']['image'] = $ogImg;   // #161 文章结构化数据封面
}

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col" id="fly-main">
      <?php /* V9.0：预览态专用提示条——仅当你能看到这篇未发布内容时出现 */ ?>
      <?php if ($notPublished): ?>
      <div class="draft-tip">
        📝 <b>预览模式</b>：<?= !empty($post['is_draft']) ? '这是草稿' : '这篇文章的定时发布时间还没到' ?>，
        普通访客访问会显示「文章不存在」。本页已标记 noindex，搜索引擎不会收录；发布后本条提示自动消失。
      </div>
      <?php endif; ?>
      <article class="post-full">
        <h1><?= fly_e($post['title']) ?><?= $pinBadge ?><?= $draftBadge ?></h1>
        <p class="meta"><?= fly_e($post['created_at']) ?><?= $catLine ?> · 阅读 <?= (int)$post['views'] ?> · 约 <?= (int)$readMin ?> 分钟</p>
        <?php if (!empty($tocData['toc'])): ?>
        <nav class="toc" aria-label="文章目录">
          <div class="toc-title">📑 目录</div>
          <ul>
            <?php foreach ($tocData['toc'] as $t): ?>
              <li class="lvl-<?= (int)$t['level'] ?>"><a href="#<?= fly_e($t['id']) ?>"><?= fly_e($t['text']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
        <?php endif; ?>
        <div class="post-body"><?= $post['content'] /* 作者书写的 HTML，已由编辑器产生 */ ?></div>
        <?php if ($tagLine !== ''): ?>
        <div class="post-tags">
          <span class="tags-label">标签：</span><?= $tagLine ?>
        </div>
        <?php endif; ?>
        <div class="post-actions">
          <button id="fly-like" class="fly-act-btn" type="button"
                  data-id="<?= (int)$id ?>" data-csrf="<?= fly_e(fly_csrf_token()) ?>"
                  data-liked="<?= $liked ? '1' : '0' ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
            👍 点赞 <span id="fly-like-n"><?= (int)($post['likes'] ?? 0) ?></span>
          </button>
          <button id="fly-fav" class="fly-act-btn" type="button" data-id="<?= (int)$id ?>">
            ⭐ <span id="fly-fav-t">收藏</span>
          </button>
        </div>
        <a class="back-link" href="<?= fly_e(fly_url('home')) ?>">&laquo; 返回首页</a>
      </article>

      <section class="comments">
        <h3><?= fly_e(fly_term('comment')) ?>板（<?= $commentCount ?>）</h3>
        <?= $clist ?>
        <?php if ($commentCount === 0): ?><p class="empty">还没有<?= fly_e(fly_term('comment')) ?>，来抢沙发~</p><?php endif; ?>

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
<script>
/* V7.0 第二批：点赞（AJAX）+ 收藏（localStorage，客户端） */
(function(){
  var likeBtn = document.getElementById('fly-like');
  if (likeBtn) {
    likeBtn.addEventListener('click', function(){
      var id = likeBtn.getAttribute('data-id');
      var fd = new FormData();
      fd.append('id', id);
      fd.append('_csrf', likeBtn.getAttribute('data-csrf'));
      fetch('<?= fly_e(fly_base_url()) ?>api/like.php', {method:'POST', body: fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d && d.code === 0) {
            document.getElementById('fly-like-n').textContent = d.likes;
            likeBtn.setAttribute('data-liked', d.liked ? '1' : '0');
            likeBtn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
            likeBtn.classList.toggle('active', d.liked);
          }
        }).catch(function(){});
    });
  }
  /* 收藏：纯客户端，存 localStorage（匿名也能用，换设备不同步） */
  var favBtn = document.getElementById('fly-fav');
  if (favBtn) {
    var FAV_KEY = 'fly_favs';
    function getFavs(){ try { return JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch(e){ return []; } }
    function setFavs(a){ try { localStorage.setItem(FAV_KEY, JSON.stringify(a)); } catch(e){} }
    function syncFav(){
      var a = getFavs();
      var id = parseInt(favBtn.getAttribute('data-id'), 10);
      var on = a.indexOf(id) >= 0;
      favBtn.classList.toggle('active', on);
      document.getElementById('fly-fav-t').textContent = on ? '已收藏' : '收藏';
    }
    syncFav();
    favBtn.addEventListener('click', function(){
      var a = getFavs();
      var id = parseInt(favBtn.getAttribute('data-id'), 10);
      var i = a.indexOf(id);
      if (i >= 0) { a.splice(i, 1); } else { a.push(id); }
      setFavs(a); syncFav();
    });
  }
})();
</script>
<?php echo fly_render('parts/footer'); ?>
