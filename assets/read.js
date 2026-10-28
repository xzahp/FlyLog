/* ============================================================
   FlyLog V8.5 阅读体验增强（独立文件，自带 ?v=）
   纯前端：阅读进度条 / 图片懒加载 / 锚点平滑滚动 / 悬浮TOC滚动高亮 /
          图片灯箱 / 代码高亮+复制 / 字号调节 / 纯文字模式 /
          复制防抄袭 / 外链跳转提示 / 移动端抽屉 / 返回顶部 / 主题平滑过渡
   依赖：可选 highlight.js（window.hljs）、window.flyTheme
   ============================================================ */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  var SITE = (window.FLY_SITE_NAME || doc.title || 'FlyLog');

  /* ---------- 主题高亮样式随主题切换（light=github / 其它=github-dark） ---------- */
  function applyHlTheme() {
    var link = doc.getElementById('fly-hl-style');
    if (!link) return;
    var t = root.getAttribute('data-theme') || 'auto';
    var dark = (t === 'dark' || t === 'aurora' || t === 'sunset' ||
      (t === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches));
    link.href = fly_asset_base('assets/highlight-' + (dark ? 'github-dark' : 'github') + '.min.css');
  }
  function fly_asset_base(p) { return p; }

  /* ---------- 主题平滑过渡：data-theme 变化时短暂加 .fly-theme-anim ---------- */
  if (window.MutationObserver) {
    var mo = new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        if (m.attributeName === 'data-theme') {
          root.classList.add('fly-theme-anim');
          applyHlTheme();
          setTimeout(function () { root.classList.remove('fly-theme-anim'); }, 360);
        }
      });
    });
    mo.observe(root, { attributes: true, attributeFilter: ['data-theme'] });
  }

  doc.addEventListener('DOMContentLoaded', function () {
    applyHlTheme();

    /* ===== 阅读进度条 ===== */
    var prog = doc.createElement('div');
    prog.className = 'fly-progress';
    doc.body.appendChild(prog);
    function onScroll() {
      var h = root.scrollHeight - root.clientHeight;
      var p = h > 0 ? (root.scrollTop / h) * 100 : 0;
      prog.style.width = p + '%';
      // 返回顶部显隐
      if (bt) { bt.classList.toggle('show', root.scrollTop > 400); }
    }

    /* ===== 返回顶部 ===== */
    var bt = doc.createElement('button');
    bt.className = 'fly-backtop'; bt.innerHTML = '↑'; bt.title = '返回顶部';
    bt.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    doc.body.appendChild(bt);

    /* ===== 图片懒加载（原生 loading=lazy + 淡入） ===== */
    var body = doc.querySelector('.post-body');
    if (body) {
      var imgs = body.querySelectorAll('img');
      for (var i = 0; i < imgs.length; i++) {
        var im = imgs[i];
        if (!im.getAttribute('loading')) { im.setAttribute('loading', 'lazy'); }
        if (im.complete) continue;
        im.classList.add('fly-lazy');
        (function (el) {
          el.addEventListener('load', function () { el.classList.add('loaded'); });
          el.addEventListener('error', function () { el.classList.add('loaded'); });
        })(im);
      }
    }

    /* ===== 锚点平滑滚动（TOC 与正文内 #hash，补偿固定头部高度） ===== */
    function headH() { var h = doc.querySelector('.site-head'); return h ? h.offsetHeight + 12 : 70; }
    doc.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a') : null;
      if (!a) return;
      var href = a.getAttribute('href') || '';
      if (href.charAt(0) === '#' && href.length > 1) {
        var target = doc.getElementById(href.slice(1));
        if (target) {
          e.preventDefault();
          var y = target.getBoundingClientRect().top + root.scrollTop - headH();
          window.scrollTo({ top: y, behavior: 'smooth' });
          if (history.replaceState) history.replaceState(null, '', href);
        }
      }
    });

    /* ===== 悬浮 TOC 滚动高亮（IntersectionObserver） ===== */
    var toc = doc.querySelector('.toc');
    if (toc) {
      var tocLinks = {};
      toc.querySelectorAll('a[href^="#"]').forEach(function (l) {
        var id = l.getAttribute('href').slice(1);
        if (id) tocLinks[id] = l;
      });
      var heads = Object.keys(tocLinks).map(function (id) { return doc.getElementById(id); }).filter(Boolean);
      if ('IntersectionObserver' in window && heads.length) {
        var cur = null;
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (en) {
            var id = en.target.id;
            if (en.isIntersecting) {
              if (cur) cur.classList.remove('active');
              cur = tocLinks[id]; if (cur) cur.classList.add('active');
            }
          });
        }, { rootMargin: '-80px 0px -65% 0px', threshold: 0 });
        heads.forEach(function (h) { io.observe(h); });
      }
      // 移动端 TOC 折叠
      var tt = toc.querySelector('.toc-title');
      if (tt) { tt.addEventListener('click', function () { toc.classList.toggle('open'); }); }
    }

    /* ===== 图片灯箱 ===== */
    if (body) {
      var allImgs = Array.prototype.slice.call(body.querySelectorAll('img'));
      var lb = doc.createElement('div'); lb.className = 'fly-lb';
      lb.innerHTML = '<button class="fly-lb-close" aria-label="关闭">×</button>' +
        '<button class="fly-lb-btn fly-lb-prev" aria-label="上一张">‹</button>' +
        '<img class="fly-lb-img" alt=""><button class="fly-lb-btn fly-lb-next" aria-label="下一张">›</button>' +
        '<div class="fly-lb-count"></div>';
      doc.body.appendChild(lb);
      var lbImg = lb.querySelector('.fly-lb-img'), lbCount = lb.querySelector('.fly-lb-count');
      var lbIdx = 0;
      function showLb(idx) {
        if (!allImgs.length) return;
        lbIdx = (idx + allImgs.length) % allImgs.length;
        lbImg.src = allImgs[lbIdx].currentSrc || allImgs[lbIdx].src;
        lbCount.textContent = (lbIdx + 1) + ' / ' + allImgs.length;
        lb.classList.add('open');
      }
      body.addEventListener('click', function (e) {
        var im = e.target.closest ? e.target.closest('img') : null;
        if (im) { e.preventDefault(); showLb(allImgs.indexOf(im)); }
      });
      lb.addEventListener('click', function (e) {
        var t = e.target;
        if (t === lb || t.classList.contains('fly-lb-close')) { lb.classList.remove('open'); }
        else if (t.classList.contains('fly-lb-prev')) { showLb(lbIdx - 1); }
        else if (t.classList.contains('fly-lb-next')) { showLb(lbIdx + 1); }
      });
      doc.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape') lb.classList.remove('open');
        else if (e.key === 'ArrowLeft') showLb(lbIdx - 1);
        else if (e.key === 'ArrowRight') showLb(lbIdx + 1);
      });
    }

    /* ===== 代码高亮 + 复制按钮 ===== */
    var pres = doc.querySelectorAll('.post-body pre');
    if (window.hljs) {
      for (var p = 0; p < pres.length; p++) {
        var code = pres[p].querySelector('code');
        if (code && !pres[p].dataset.hl) {
          try { window.hljs.highlightElement(code); pres[p].dataset.hl = '1'; } catch (e) {}
        }
      }
    }
    for (var q = 0; q < pres.length; q++) {
      var pr = pres[q];
      if (pr.parentNode && pr.parentNode.classList && pr.parentNode.classList.contains('fly-code-wrap')) continue;
      var wrap = doc.createElement('div'); wrap.className = 'fly-code-wrap';
      pr.parentNode.insertBefore(wrap, pr); wrap.appendChild(pr);
      var cb = doc.createElement('button'); cb.className = 'fly-copy-btn'; cb.type = 'button'; cb.textContent = '复制';
      cb.addEventListener('click', (function (src) {
        return function () {
          var txt = src.innerText || '';
          var done = function () { cb.textContent = '已复制'; setTimeout(function () { cb.textContent = '复制'; }, 1500); };
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(done, function () { fallbackCopy(txt); done(); });
          } else { fallbackCopy(txt); done(); }
        };
      })(pr));
      wrap.appendChild(cb);
    }
    function fallbackCopy(txt) {
      var ta = doc.createElement('textarea'); ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
      doc.body.appendChild(ta); ta.select(); try { doc.execCommand('copy'); } catch (e) {} doc.body.removeChild(ta);
    }

    /* ===== 浮层阅读工具栏（字号 A-/A+ + 纯文字模式） ===== */
    if (body) {
      var toolbar = doc.createElement('div'); toolbar.className = 'fly-read-toolbar';
      var fsMinus = doc.createElement('button'); fsMinus.className = 'fly-rt-btn'; fsMinus.innerHTML = 'A−'; fsMinus.title = '减小字号';
      var fsPlus = doc.createElement('button'); fsPlus.className = 'fly-rt-btn'; fsPlus.innerHTML = 'A+'; fsPlus.title = '增大字号';
      var textBtn = doc.createElement('button'); textBtn.className = 'fly-rt-btn'; textBtn.innerHTML = '文'; textBtn.title = '纯文字模式';
      toolbar.appendChild(fsMinus); toolbar.appendChild(fsPlus); toolbar.appendChild(textBtn);
      doc.body.appendChild(toolbar);
      setTimeout(function () { toolbar.classList.add('show'); }, 600);

      var FS_KEY = 'fly_font', TXT_KEY = 'fly_text', fs = 16;
      try { fs = parseInt(localStorage.getItem(FS_KEY), 10) || 16; } catch (e) {}
      function applyFs() { root.style.setProperty('--fly-fs', fs + 'px'); }
      applyFs();
      fsMinus.addEventListener('click', function () { fs = Math.max(14, fs - 1); applyFs(); try { localStorage.setItem(FS_KEY, fs); } catch (e) {} });
      fsPlus.addEventListener('click', function () { fs = Math.min(22, fs + 1); applyFs(); try { localStorage.setItem(FS_KEY, fs); } catch (e) {} });

      var textOn = false;
      try { textOn = localStorage.getItem(TXT_KEY) === '1'; } catch (e) {}
      function applyText() { root.classList.toggle('fly-text-only', textOn); textBtn.classList.toggle('on', textOn); }
      applyText();
      textBtn.addEventListener('click', function () { textOn = !textOn; applyText(); try { localStorage.setItem(TXT_KEY, textOn ? '1' : '0'); } catch (e) {} });
    }

    /* ===== 移动端抽屉式侧边栏（汉堡按钮） ===== */
    var head = doc.querySelector('.head-inner');
    var side = doc.querySelector('.side-col, .fly-side-col');
    if (head && side && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
      var ham = doc.createElement('button'); ham.className = 'fly-hamburger'; ham.innerHTML = '☰'; ham.setAttribute('aria-label', '菜单');
      head.appendChild(ham);
      var overlay = doc.createElement('div'); overlay.style.cssText = 'position:fixed;inset:0;z-index:9970;display:none';
      doc.body.appendChild(overlay);
      ham.addEventListener('click', function () { doc.body.classList.add('fly-drawer-open'); overlay.style.display = 'block'; });
      overlay.addEventListener('click', function () { doc.body.classList.remove('fly-drawer-open'); overlay.style.display = 'none'; });
    }

    /* ===== 外链跳转提示（即将离开本站） ===== */
    var lm = doc.createElement('div'); lm.className = 'fly-leave-modal';
    lm.innerHTML = '<div class="fly-leave-box"><p>即将离开本站，前往：<br><a class="fly-leave-url" target="_blank" rel="noopener"></a></p>' +
      '<div class="fly-leave-ops"><button class="fly-leave-go">继续前往</button><button class="fly-leave-cancel">取消</button></div></div>';
    doc.body.appendChild(lm);
    var lmUrl = lm.querySelector('.fly-leave-url'), lmGo = lm.querySelector('.fly-leave-go');
    var pending = null;
    lmGo.addEventListener('click', function () { lm.classList.remove('open'); if (pending) { window.open(pending, '_blank'); } });
    lm.querySelector('.fly-leave-cancel').addEventListener('click', function () { lm.classList.remove('open'); });
    doc.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a') : null;
      if (!a) return;
      var href = a.getAttribute('href') || '';
      if (!/^https?:\/\//i.test(href)) return;
      var host = ''; try { host = new URL(href).hostname; } catch (e) {}
      if (!host || host === location.hostname) return;
      if (a.getAttribute('target') === '_blank' && a.getAttribute('rel') && a.getAttribute('rel').indexOf('noopener') >= 0) return;
      e.preventDefault(); pending = href; lmUrl.textContent = href; lmUrl.href = href; lm.classList.add('open');
    });

    /* ===== 复制防抄袭提示（复制 >50 字自动加原文链接 + 版权） ===== */
    doc.addEventListener('copy', function (e) {
      var sel = (window.getSelection ? window.getSelection() : '').toString();
      if (!sel) return;
      var len = (typeof sel.length !== 'undefined') ? sel.length : (function (s) { var n = 0; for (var k = 0; k < s.length; k++) { n += s.charCodeAt(k) > 255 ? 1 : 0.5; } return n; })(sel);
      if (len <= 50) return;
      var add = '\n\n—— 本文来自《' + SITE + '》\n原文链接：' + location.href + '\n© ' + SITE + ' 版权所有，转载请注明出处。';
      try {
        if (e.clipboardData) { e.clipboardData.setData('text/plain', sel + add); e.preventDefault(); }
      } catch (err) {}
    });

    /* ===== 滚动监听 ===== */
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  });
})();
