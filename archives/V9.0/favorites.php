<?php
/**
 * FlyLog V7.0 第二批 我的收藏（前台页面）
 * ------------------------------------------------------------------
 * 收藏数据存在浏览器 localStorage（匿名可用，无需登录）。
 * 本页读取收藏 id 列表，逐条向 /api/index.php?route=post 拉取标题与链接渲染。
 * 被删除/下线的文章会自动显示为「链接已失效」，不影响其余。
 */
require __DIR__ . '/config.php';

$bc = array(array('name' => '首页', 'url' => fly_url('home')), array('name' => '我的收藏'));
$meta = array('title' => '我的收藏 - ' . fly_config('site.name', 'FlyLog'), 'description' => '我收藏的文章', 'breadcrumb' => $bc);

echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => $bc));
?>
<div class="wrap">
  <div class="layout">
    <div class="main-col" id="fly-main">
      <h1 style="font-size:22px;margin-bottom:6px">⭐ 我的收藏</h1>
      <p class="hint" style="margin-bottom:18px">收藏保存在你当前浏览器本地（localStorage），不会跨设备同步，清理浏览器数据会丢失。点文章标题右侧的「取消收藏」即可移除。</p>
      <div id="fly-fav-list"><p class="empty">正在读取收藏…</p></div>
    </div>
    <?= fly_render('parts/sidebar') ?>
  </div>
</div>
<script>
(function(){
  var FAV_KEY = 'fly_favs';
  var box = document.getElementById('fly-fav-list');
  function getFavs(){ try { return JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch(e){ return []; } }
  function setFavs(a){ try { localStorage.setItem(FAV_KEY, JSON.stringify(a)); } catch(e){} }
  var ids = getFavs();
  if (!ids.length) {
    box.innerHTML = '<p class="empty">还没有收藏。打开任意文章，点正文下方的「⭐ 收藏」即可加入这里。</p>';
    return;
  }
  box.innerHTML = '<ul class="side-list" style="font-size:15px"></ul>';
  var ul = box.firstChild;
  var pending = ids.length;
  ids.forEach(function(id){
    fetch('<?= fly_e(fly_base_url()) ?>api/index.php?route=post&id=' + encodeURIComponent(id), {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        var li = document.createElement('li');
        if (d && d.code === 0 && d.data) {
          li.innerHTML = '<a href="' + encodeURI(d.data.url) + '">' + escapeHtml(d.data.title) + '</a> '
            + '<a class="fly-fav-rm" href="#" data-id="' + id + '" style="color:#9ca3af;font-size:12px;margin-left:6px">取消收藏</a>';
        } else {
          li.innerHTML = '<span style="color:#9ca3af">文章 #' + id + ' 链接已失效（可能已删除）</span> '
            + '<a class="fly-fav-rm" href="#" data-id="' + id + '" style="color:#9ca3af;font-size:12px;margin-left:6px">移除</a>';
        }
        ul.appendChild(li);
      })
      .catch(function(){
        var li = document.createElement('li');
        li.innerHTML = '<span style="color:#9ca3af">文章 #' + id + ' 读取失败</span>';
        ul.appendChild(li);
      })
      .then(function(){ if (--pending === 0) bindRemove(); });
  });
  function bindRemove(){
    var links = box.querySelectorAll('.fly-fav-rm');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function(e){
        e.preventDefault();
        var id = parseInt(this.getAttribute('data-id'), 10);
        var a = getFavs();
        var idx = a.indexOf(id);
        if (idx >= 0) { a.splice(idx, 1); setFavs(a); }
        var li = this.parentNode;
        if (li && li.parentNode) { li.parentNode.removeChild(li); }
        if (!getFavs().length) {
          box.innerHTML = '<p class="empty">收藏已清空。</p>';
        }
      });
    }
  }
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
})();
</script>
<?php echo fly_render('parts/footer'); ?>
