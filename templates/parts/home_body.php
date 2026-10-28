<?php
/**
 * 首页主体（文章列表 + 分页 + 侧边栏）
 * V10.0：从根 index.php 抽出来的可复用片段 —— 默认模板与主题共用同一份，
 * 主题只要 include 它就能拿到完整功能（搜索命中、分页、侧栏），不必重写。
 * 变量：$items（已渲染的列表HTML）、$commentHits、$q、$page、$totalPages、$urlFor
 */
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
