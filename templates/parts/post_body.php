<?php
/**
 * 文章页主体（正文 + 目录 + 标签 + 点赞收藏 + 评论区 + 互动脚本）
 * V10.0：从根 post.php 抽出来，默认模板与主题共用同一份，
 * 主题无需重写点赞/评论/收藏逻辑，天然兼容 V9.0 的全部交互功能。
 *
 * 变量：$post, $id, $notPublished, $pinBadge, $draftBadge, $catLine,
 *       $readMin, $tocData, $tagLine, $liked, $commentCount, $clist, $err
 */
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
        <?php $__bodyHtml = fly_apply_filters('the_content', (string)$post['content'], $post); ?>
        <div class="post-body"><?= $__bodyHtml /* 作者 HTML，已过 the_content 过滤器供插件加工 */ ?></div>
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
