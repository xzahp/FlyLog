/* FlyLog V4.0 前端交互增强（head.php 以 defer 加载，存在才输出）
   - #142 回到顶部按钮
   - #127 文章正文图片懒加载兜底（原生 loading="lazy"）
   - #135 侧边栏搜索防抖（输入停顿后自动跳转搜索页）
   纯原生 JS，无依赖，兼容旧浏览器。 */
(function () {
  'use strict';

  function fly_escape_html(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ---------- 回到顶部 ----------
  var btn = document.createElement('button');
  btn.className = 'back-top';
  btn.setAttribute('type', 'button');
  btn.setAttribute('aria-label', '回到顶部');
  btn.textContent = '↑';
  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  document.body.appendChild(btn);
  var onScroll = function () {
    if (window.scrollY > 300) { btn.classList.add('show'); }
    else { btn.classList.remove('show'); }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ---------- 图片懒加载兜底 ----------
  var imgs = document.querySelectorAll('.post-body img:not([loading])');
  for (var i = 0; i < imgs.length; i++) {
    imgs[i].setAttribute('loading', 'lazy');
    imgs[i].setAttribute('decoding', 'async');
  }

  // ---------- #135/#141 侧边栏搜索：防抖自动跳转 + 标题联想下拉 ----------
  var box = document.querySelector('.side-search input[name="q"]');
  if (box && box.form) {
    var dt = null, st = null;
    var sug = document.createElement('div');
    sug.className = 'suggest';
    box.parentNode.appendChild(sug);
    var items = [], active = -1;
    function closeSug() { sug.classList.remove('show'); sug.innerHTML = ''; active = -1; }
    function renderSug(list) {
      items = list || [];
      if (!items.length) { closeSug(); return; }
      sug.innerHTML = items.map(function (it) {
        return '<a href="' + it.url + '">' + fly_escape_html(it.title) + '</a>';
      }).join('');
      sug.classList.add('show');
    }
    box.addEventListener('input', function () {
      var v = box.value.trim();
      if (dt) { clearTimeout(dt); }
      if (st) { clearTimeout(st); }
      if (v.length >= 1) {
        // 停顿后自动跳转搜索页（避免每键一次刷新）
        dt = setTimeout(function () { if (box.value.trim().length >= 1) { box.form.submit(); } }, 600);
        // 250ms 拉取标题联想
        if (window.fetch) {
          st = setTimeout(function () {
            fetch('?suggest=1&q=' + encodeURIComponent(v)).then(function (r) { return r.json(); }).then(function (d) {
              if (box.value.trim() === v) { renderSug(d.items); }
            }).catch(function () { closeSug(); });
          }, 250);
        }
      } else { closeSug(); }
    });
    box.addEventListener('keydown', function (e) {
      if (!sug.classList.contains('show') || !items.length) { return; }
      var links = sug.querySelectorAll('a');
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); e.preventDefault(); }
      else if (e.key === 'Enter' && active >= 0) { location.href = items[active].url; e.preventDefault(); }
      else if (e.key === 'Escape') { closeSug(); return; }
      else { return; }
      for (var i = 0; i < links.length; i++) { links[i].classList.remove('active'); }
      if (links[active]) { links[active].classList.add('active'); }
    });
    document.addEventListener('click', function (e) {
      if (sug.classList.contains('show') && !sug.contains(e.target) && e.target !== box) { closeSug(); }
    });
  }

  // ---------- #130 代码高亮（highlight.js 经 CDN 加载，失败则跳过，不影响页面） ----------
  if (window.hljs) {
    try { hljs.configure({ ignoreUnescapedHTML: true }); } catch (e) {}
    document.querySelectorAll('.post-body pre code').forEach(function (el) {
      try { hljs.highlightElement(el); } catch (e) {}
    });
  }

  // ---------- #137 复制带版权（仅 .post-body 选区；已含版权不重复追加） ----------
  document.addEventListener('copy', function (e) {
    if (!e.clipboardData) { return; }
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || sel.rangeCount === 0) { return; }
    var range = sel.getRangeAt(0);
    var c = range.commonAncestorContainer;
    var elc = c && c.nodeType === 3 ? c.parentElement : c;
    if (!elc || !elc.closest || !elc.closest('.post-body')) { return; }
    var txt = sel.toString();
    if (txt.indexOf('摘自《') !== -1) { return; }
    var title = (document.title.split(' - ')[0] || document.title).trim();
    e.clipboardData.setData('text/plain', txt + '\n\n—— 摘自《' + title + '》 @ ' + location.href);
    e.preventDefault();
  });

  // ---------- #139 标题点击复制 ----------
  var h1 = document.querySelector('.post-full > h1');
  if (h1) {
    h1.style.cursor = 'pointer';
    h1.title = '点击复制标题';
    h1.addEventListener('click', function () {
      var t = h1.textContent.replace(/📌.*/, '').trim();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t).then(function () { fly_toast('标题已复制'); }, function () {});
      }
    });
  }

  // ---------- 轻量 toast ----------
  function fly_toast(msg) {
    var el = document.getElementById('fly-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'fly-toast';
      el.style.cssText = 'position:fixed;left:50%;bottom:80px;transform:translateX(-50%);background:rgba(15,23,42,.92);color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;z-index:80;opacity:0;transition:opacity .25s;pointer-events:none';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.opacity = '0'; }, 1600);
  }
  window.fly_toast = fly_toast;
})();
