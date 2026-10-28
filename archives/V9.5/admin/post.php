<?php
/** FlyLog V2.0 写文章 / 编辑文章（工具栏 + 置顶 + 定时发布 + 草稿） */
require __DIR__ . '/_init.php';
fly_admin_require('manage_posts');   // V6.0 #12(P1) 旧版只校验登录，无权限角色也能新建与修改文章

$db = fly_db();
$id = (int)($_GET['id'] ?? 0);
$post = $id ? fly_db()->table('posts')->where('id', $id)->one() : null;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $tagsInput = trim((string)($_POST['tags'] ?? ''));
    $content = (string)($_POST['content'] ?? '');
    $summary = trim((string)($_POST['summary'] ?? ''));
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $isDraft = isset($_POST['is_draft']) ? 1 : 0;
    $publishAt = trim((string)($_POST['publish_at'] ?? ''));

    // V7.0：选了分类树里的分类就以它为准；同时把分类名同步写回旧的 category 字段，
    // 这样老模板、老插件、导出的旧数据都还能正常拿到分类名。
    if ($categoryId > 0) {
        $catRow = fly_cat_resolve($categoryId);
        if ($catRow) {
            $category = $catRow['name'];
        } else {
            $categoryId = 0;
        }
    }

    if ($title === '' || $content === '') {
        $err = '标题和正文不能为空。';
    } elseif (fly_len($title) > 255 || fly_len($category) > 100) {
        $err = '标题不超过 255 字，分类不超过 100 字。';
    } else {
        if ($summary === '') {
            $summary = fly_excerpt($content, 120);
        }
        if ($publishAt !== '') {
            $publishAt = date('Y-m-d H:i:s', strtotime($publishAt));
        } else {
            $publishAt = null;
        }
        $data = array(
            'title' => $title, 'summary' => $summary, 'content' => $content,
            'category' => $category, 'is_pinned' => $isPinned, 'is_draft' => $isDraft,
            'publish_at' => $publishAt,
        );
        // V7.0：category_id 只有在列已迁移出来时才写，老库升级中途不会炸
        if (fly_db_has_column('posts', 'category_id')) {
            $data['category_id'] = $categoryId;
        }
        // V9.0：SEO 三件套。同样只在列已迁移出来时才写，且留空表示「回落到标题/自动摘要」，
        // 这样老文章一个字不改也完全不受影响。
        $seoMax = array('seo_title' => 255, 'seo_keywords' => 255, 'seo_desc' => 500);
        foreach ($seoMax as $seoCol => $seoLen) {
            if (!fly_db_has_column('posts', $seoCol)) {
                continue;
            }
            $seoVal = isset($_POST[$seoCol]) ? trim((string)$_POST[$seoCol]) : '';
            if (fly_len($seoVal) > $seoLen) {
                $seoVal = fly_excerpt($seoVal, $seoLen);
            }
            $data[$seoCol] = $seoVal;
        }
        if ($post) {
            BasePost::update($id, $data);
            fly_audit('post.update', 'post', $id);   // V7.0 第二批：审计
        } else {
            $id = BasePost::create($data);
            fly_audit('post.create', 'post', $id);   // V7.0 第二批：审计
        }
        // V7.0 标签：全量覆盖式写入（留空即清空该文章的所有标签）
        fly_tag_set($id, $tagsInput);
        // #231 Webhook：新文章推送（仅新建且非草稿时通知，避免每次编辑都轰炸）
        if (!$isDraft && empty($post)) {
            fly_notify(
                '新' . fly_term('post') . '发布',
                '《' . $title . '》已发布\n分类：' . ($category !== '' ? $category : '未分类')
                . '\n[查看](' . rtrim(fly_base_url(), '/') . fly_url('post', array('id' => $id)) . ')'
            );
        }
        fly_redirect('posts.php?saved=1');
    }
    // 出错时保留输入
    $post = array(
        'id' => $id, 'title' => $title, 'category' => $category, 'content' => $content,
        'summary' => $summary, 'is_pinned' => $isPinned, 'is_draft' => $isDraft, 'publish_at' => $publishAt,
        'category_id' => $categoryId,
        'seo_title' => isset($_POST['seo_title']) ? trim((string)$_POST['seo_title']) : '',
        'seo_keywords' => isset($_POST['seo_keywords']) ? trim((string)$_POST['seo_keywords']) : '',
        'seo_desc' => isset($_POST['seo_desc']) ? trim((string)$_POST['seo_desc']) : '',
    );
}

$title = $post['title'] ?? '';
$category = $post['category'] ?? '';
$categoryId = (int)($post['category_id'] ?? 0);
// 老文章还没回填 category_id 时，用分类名反查一次，编辑器里直接选中
if ($categoryId <= 0 && $category !== '') {
    $catRow = fly_cat_resolve($category);
    if ($catRow) {
        $categoryId = (int)$catRow['id'];
    }
}
$postTags = $post ? fly_tags_of((int)$post['id']) : array();
$tagsValue = implode(', ', array_map(function ($t) { return $t['name']; }, $postTags));
if (isset($_POST['tags'])) {
    $tagsValue = trim((string)$_POST['tags']);   // 提交出错时保留输入
}
$content = $post['content'] ?? '';
$summary = $post['summary'] ?? '';
$isPinned = !empty($post['is_pinned']);
$isDraft = !empty($post['is_draft']);
$publishAt = !empty($post['publish_at']) ? date('Y-m-d\TH:i', strtotime($post['publish_at'])) : '';
// V9.0：SEO 字段可视化编辑（留空 = 回落标题 / 自动摘要，前台自动处理）
$seoTitle = (string)($post['seo_title'] ?? '');
$seoKeywords = (string)($post['seo_keywords'] ?? '');
$seoDesc = (string)($post['seo_desc'] ?? '');
$seoReady = fly_db_has_column('posts', 'seo_title');

fly_admin_head($post ? '编辑文章' : '写新文章', 'posts');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h1 style="font-size:22px"><?= $post ? '编辑文章' : '写新文章' ?></h1>
  <a class="btn btn-gray" href="posts.php">&laquo; 返回文章列表</a>
</div>

<?php if ($err !== ''): ?><div class="msg msg-err"><?= fly_e($err) ?></div><?php endif; ?>

<form method="post" action="post.php<?= $post ? '?id=' . (int)$post['id'] : '' ?>">
  <?= fly_csrf_field() ?>
  <div class="panel">
    <label for="title">标题</label>
    <input id="title" name="title" maxlength="255" value="<?= fly_e($title) ?>" required>

    <div class="row">
      <div>
        <label for="category_id">分类（支持无限级子分类）</label>
        <select id="category_id" name="category_id">
          <?= fly_cat_options($categoryId) ?>
        </select>
        <p class="hint" style="margin-top:6px">没有想要的分类？<a href="categories.php" target="_blank">去分类管理新建 →</a></p>
      </div>
      <div>
        <label for="publish_at">定时发布（留空=立即）</label>
        <input id="publish_at" name="publish_at" type="datetime-local" value="<?= fly_e($publishAt) ?>">
      </div>
    </div>

    <label for="tags">标签（逗号 / 空格分隔，可直接造新词）</label>
    <input id="tags" name="tags" maxlength="500" value="<?= fly_e($tagsValue) ?>" placeholder="如：航拍, 入门教程, DJI">
    <p class="hint" style="margin-top:-8px">最多 20 个，每个不超过 50 字。留空即清空本文所有标签。</p>

    <label for="content">正文（支持 HTML）</label>
    <div class="toolbar">
      <button type="button" onclick="return flyIns('b')"><b>B</b> 加粗</button>
      <button type="button" onclick="return flyIns('i')"><i>I</i> 斜体</button>
      <button type="button" onclick="return flyIns('h')">H 标题</button>
      <button type="button" onclick="return flyIns('quote')">❝ 引用</button>
      <button type="button" onclick="return flyIns('ul')">• 列表</button>
      <button type="button" onclick="return flyIns('link')">🔗 链接</button>
      <button type="button" onclick="return flyIns('code')">&lt;/&gt; 代码</button>
      <button type="button" onclick="return flyIns('img')">🖼 图片</button>
      <button type="button" onclick="return flyIns('hr')">— 分割线</button>
    </div>
    <textarea id="content" name="content" style="min-height:320px;font-family:monospace"><?= fly_e($content) ?></textarea>

    <label for="summary">摘要（留空则自动生成）</label>
    <input id="summary" name="summary" maxlength="500" value="<?= fly_e($summary) ?>">

    <?php /* V9.0：SEO 字段可视化编辑（列已迁移才显示，老库升级中途不会出现空转面板） */ ?>
    <?php if ($seoReady): ?>
    <div class="seo-panel">
      <h4>🔍 SEO 设置（选填 · 留空则自动使用标题与摘要）</h4>

      <label for="seo_title">SEO 标题</label>
      <input id="seo_title" name="seo_title" maxlength="255" value="<?= fly_e($seoTitle) ?>" placeholder="留空则使用文章标题">
      <p class="seo-count" id="cnt-seo-title">已输入 0 字符</p>

      <label for="seo_keywords">关键词（逗号分隔）</label>
      <input id="seo_keywords" name="seo_keywords" maxlength="255" value="<?= fly_e($seoKeywords) ?>" placeholder="如：航拍教程, DJI Mini 4 Pro">
      <p class="seo-count" id="cnt-seo-kw">已输入 0 字符</p>

      <label for="seo_desc">SEO 描述</label>
      <textarea id="seo_desc" name="seo_desc" maxlength="500" style="min-height:70px" placeholder="留空则自动截取正文开头"><?= fly_e($seoDesc) ?></textarea>
      <p class="seo-count" id="cnt-seo-desc">已输入 0 字符</p>

      <div class="seo-preview">
        <div class="sp-title" id="sp-title">—</div>
        <div class="sp-url"><?= fly_e(rtrim(fly_base_url(), '/') . fly_url('post', array('id' => (int)($post['id'] ?? 0)))) ?></div>
        <div class="sp-desc" id="sp-desc">—</div>
      </div>
      <p class="hint" style="margin-top:8px">上方是搜索结果的模拟预览，随输入实时更新。红色数字表示超出建议长度。</p>
    </div>
    <?php endif; ?>

    <div style="margin-top:14px;display:flex;gap:20px;flex-wrap:wrap">
      <label style="font-weight:400;margin:0"><input type="checkbox" name="is_pinned" value="1" style="width:auto;margin-right:6px" <?= $isPinned ? 'checked' : '' ?>>📌 置顶这篇文章</label>
      <label style="font-weight:400;margin:0"><input type="checkbox" name="is_draft" value="1" style="width:auto;margin-right:6px" <?= $isDraft ? 'checked' : '' ?>>📝 存为草稿（前台不显示）</label>
    </div>

    <div class="form-ops">
      <button class="btn" type="submit">保存</button>
      <a class="btn btn-gray" href="posts.php">取消</a>
      <?php if ($post): ?>
        <a class="btn btn-gray" href="<?= fly_e(fly_url('post', array('id' => $post['id']))) ?>" target="_blank">查看</a>
        <?php /* V9.0 草稿预览：带一次性令牌的链接，未登录也能看，12 小时后自动作废 */ ?>
        <?php if (function_exists('fly_preview_token')): ?>
          <?php $__pvBase = fly_url('post', array('id' => $post['id'])); ?>
          <?php $__pvTok = fly_preview_token((int)$post['id']); ?>
          <?php if ($__pvTok !== ''): ?>
            <a class="btn btn-gray" href="<?= fly_e($__pvBase . (strpos($__pvBase, '?') === false ? '?' : '&') . 'preview=' . rawurlencode($__pvTok)) ?>" target="_blank">👁 预览（草稿也能看）</a>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</form>

<script>
function flyIns(type){
  var ta=document.getElementById('content');
  var s=ta.selectionStart,e=ta.selectionEnd,sel=ta.value.substring(s,e);
  var wrap={'b':['<strong>','</strong>'],'i':['<em>','</em>'],'h':['<h2>','</h2>'],
    'quote':['<blockquote>','</blockquote>'],'code':['<pre><code>','</code></pre>']};
  var rep;
  if(wrap[type]){rep=wrap[type][0]+sel+wrap[type][1];}
  else if(type==='ul'){rep='<ul>\n  <li>'+ (sel||'列表项') +'</li>\n</ul>';}
  else if(type==='link'){var u=prompt('链接地址：','https://');rep=u?'<a href="'+u+'">'+(sel||u)+'</a>':sel;}
  else if(type==='img'){var iu=prompt('图片地址：','https://');rep=iu?'<img src="'+iu+'" alt="'+(sel||'图片')+'">':sel;}
  else if(type==='hr'){rep='<hr>';}
  else{rep=sel;}
  ta.value=ta.value.substring(0,s)+rep+ta.value.substring(e);
  ta.focus();ta.selectionStart=s+rep.length;ta.selectionEnd=s+rep.length;
  return false;
}

/* V9.0 SEO 面板：输入即更新字数统计与搜索结果模拟预览 */
(function(){
  var st=document.getElementById('seo_title');
  if(!st){return;}
  var sk=document.getElementById('seo_keywords'), sd=document.getElementById('seo_desc');
  var t=document.getElementById('title'), c=document.getElementById('content'), sum=document.getElementById('summary');
  function val(el){return el?String(el.value||'').trim():'';}
  function setCount(id,n,limit){
    var el=document.getElementById(id); if(!el){return;}
    el.textContent='已输入 '+n+' 字符'+(limit?('（建议 ≤ '+limit+'）'):'');
    el.className=(limit&&n>limit)?'seo-count over':'seo-count';
  }
  function upd(){
    var title=val(st)||val(t)||'（未命名文章）';
    var desc=val(sd)||val(sum)||val(c).replace(/<[^>]*>/g,'').slice(0,120)||'（暂无描述）';
    document.getElementById('sp-title').textContent=title;
    document.getElementById('sp-desc').textContent=desc.slice(0,160);
    setCount('cnt-seo-title',val(st).length,60);
    setCount('cnt-seo-kw',val(sk).length,0);
    setCount('cnt-seo-desc',val(sd).length,160);
  }
  var els=[st,sk,sd,t,c,sum], evs=['input','change'];
  for(var i=0;i<els.length;i++){
    if(!els[i]){continue;}
    for(var j=0;j<evs.length;j++){ els[i].addEventListener(evs[j],upd); }
  }
  upd();
})();
</script>
<?php fly_admin_foot(); ?>
