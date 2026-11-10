/* CHEESA Full Text — daftar isi aktif, ukuran huruf, pratinjau referensi, rumus TeX */
(function () {
  'use strict';
  var D = document;

  function ready(fn) { if (D.readyState !== 'loading') fn(); else D.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    var roots = D.querySelectorAll('.cft');
    if (!roots.length) return;
    [].forEach.call(roots, init);
    if (D.querySelector('.cft[data-cft-tex]')) loadMathJax();
  });

  function init(root) {
    // ukuran huruf (diingat per pembaca bila browser mengizinkan)
    var size = 1;
    try { size = parseFloat(localStorage.getItem('cftSize')) || 1; } catch (e) {}
    apply(size);
    root.addEventListener('click', function (e) {
      var b = e.target.closest('.cft-size');
      if (!b) return;
      size = Math.min(1.4, Math.max(.85, Math.round((size + parseFloat(b.getAttribute('data-cft-size')) * .075) * 1000) / 1000));
      apply(size);
      try { localStorage.setItem('cftSize', size); } catch (e2) {}
    });
    function apply(s) { root.style.setProperty('--cft-size', s + 'rem'); }

    // tautan di dalam teks: gulir halus + sorot sasaran (juga saat berada di dalam tab)
    root.addEventListener('click', function (e) {
      var a = e.target.closest('a[href^="#"]');
      if (!a) return;
      var t = D.getElementById(a.getAttribute('href').slice(1));
      if (!t || !root.contains(t)) return;
      e.preventDefault();
      t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      t.classList.remove('cft-flash'); void t.offsetWidth; t.classList.add('cft-flash');
      if (history.replaceState) history.replaceState(null, '', '#' + t.id);
    });

    refTips(root);
    activeToc(root);
  }

  /* pratinjau referensi saat kursor di atas [1] */
  function refTips(root) {
    var tip = null;
    function hide() { if (tip) { tip.remove(); tip = null; } }
    root.addEventListener('mouseover', function (e) {
      var a = e.target.closest('.cft-xref-bibr');
      if (!a) return;
      var ref = D.getElementById(a.getAttribute('href').slice(1));
      if (!ref) return;
      hide();
      tip = D.createElement('div'); tip.className = 'cft-tip'; tip.setAttribute('role', 'tooltip');
      tip.innerHTML = ref.innerHTML;   // isi referensi sudah disaring di server
      D.body.appendChild(tip);
      var r = a.getBoundingClientRect(), w = tip.offsetWidth;
      tip.style.left = Math.max(8, Math.min(window.scrollX + r.left, window.scrollX + D.documentElement.clientWidth - w - 8)) + 'px';
      tip.style.top = (window.scrollY + r.bottom + 6) + 'px';
    });
    root.addEventListener('mouseout', function (e) { if (e.target.closest('.cft-xref-bibr')) hide(); });
    window.addEventListener('scroll', hide, { passive: true });
  }

  /* tandai bagian yang sedang dibaca di daftar isi */
  function activeToc(root) {
    var links = root.querySelectorAll('.cft-toc a');
    if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    [].forEach.call(links, function (l) { map[l.getAttribute('href').slice(1)] = l; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        [].forEach.call(links, function (l) { l.classList.remove('is-active'); });
        var l = map[en.target.id]; if (l) l.classList.add('is-active');
      });
    }, { rootMargin: '0px 0px -70% 0px' });
    Object.keys(map).forEach(function (id) { var s = D.getElementById(id); if (s) io.observe(s); });
  }

  /* rumus TeX (bila ada) */
  function loadMathJax() {
    if (window.MathJax) return;
    window.MathJax = { tex: { inlineMath: [['\\(', '\\)']], displayMath: [['\\[', '\\]']] }, options: { processHtmlClass: 'cft' }, startup: { typeset: true } };
    var s = D.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js';
    s.async = true; s.id = 'cft-mathjax';
    D.head.appendChild(s);
  }
})();
