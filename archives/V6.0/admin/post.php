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
    $content = (string)($_POST['content'] ?? '');
    $summary = trim((string)($_POST['summary'] ?? ''));
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $isDraft = isset($_POST['is_draft']) ? 1 : 0;
    $publishAt = trim((string)($_POST['publish_at'] ?? ''));

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
        if ($post) {
            BasePost::update($id, $data);
        } else {
            $id = BasePost::create($data);
        }
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
    );
}

$title = $post['title'] ?? '';
$category = $post['category'] ?? '';
$content = $post['content'] ?? '';
$summary = $post['summary'] ?? '';
$isPinned = !empty($post['is_pinned']);
$isDraft = !empty($post['is_draft']);
$publishAt = !empty($post['publish_at']) ? date('Y-m-d\TH:i', strtotime($post['publish_at'])) : '';

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
        <label for="category">分类</label>
        <input id="category" name="category" maxlength="100" value="<?= fly_e($category) ?>" placeholder="例如：公告 / 航拍">
      </div>
      <div>
        <label for="publish_at">定时发布（留空=立即）</label>
        <input id="publish_at" name="publish_at" type="datetime-local" value="<?= fly_e($publishAt) ?>">
      </div>
    </div>

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

    <div style="margin-top:14px;display:flex;gap:20px;flex-wrap:wrap">
      <label style="font-weight:400;margin:0"><input type="checkbox" name="is_pinned" value="1" style="width:auto;margin-right:6px" <?= $isPinned ? 'checked' : '' ?>>📌 置顶这篇文章</label>
      <label style="font-weight:400;margin:0"><input type="checkbox" name="is_draft" value="1" style="width:auto;margin-right:6px" <?= $isDraft ? 'checked' : '' ?>>📝 存为草稿（前台不显示）</label>
    </div>

    <div class="form-ops">
      <button class="btn" type="submit">保存</button>
      <a class="btn btn-gray" href="posts.php">取消</a>
      <?php if ($post): ?>
        <a class="btn btn-gray" href="<?= fly_e(fly_url('post', array('id' => $post['id']))) ?>" target="_blank">查看</a>
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
</script>
<?php fly_admin_foot(); ?>
