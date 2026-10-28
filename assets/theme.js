/* FlyLog V8.0 主题切换（defer 加载，DOM 解析后绑定；head.php 内联脚本已提前设好 data-theme 防闪白） */
(function () {
  var KEY = 'fly_theme';
  var VALID = ['auto', 'light', 'dark', 'aurora', 'sunset'];
  function apply(t) { document.documentElement.setAttribute('data-theme', t); }
  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function set(t) { try { localStorage.setItem(KEY, t); } catch (e) {} apply(t); }
  window.flyTheme = { list: VALID, current: function () { return document.documentElement.getAttribute('data-theme'); }, set: set };
  // auto 主题下实时跟随系统偏好变化（系统切深/浅色时无需刷新即更新视觉）
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () { if (get() === 'auto' || get() === null) { if (window.flyThemeSyncSwitcher) window.flyThemeSyncSwitcher(); } };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
  }
})();
