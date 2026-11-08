/* ==========================================================================
   CHEESA Theme — komponen tampilan
   1. Ikon menu utama + penanda menu aktif
   2. Kartu "Journal Issue" (cover terbitan) di kolom kiri beranda
   3. Panel tombol cepat (Submit Your Paper / Become a Reviewer / media sosial)
   4. Carousel logo indeksasi di beranda
   5. Tab Current Issue | Upcoming Issue
   6. Gambar pengganti (thumbnail) untuk kartu artikel tanpa cover
   7. Author Diversity: grafik batang bertumpuk + persentase
   Semua bagian aman dijalankan ulang (tidak membuat elemen ganda).
   ========================================================================== */
(function () {
  'use strict';
  var CFG = window.CHEESA_CFG || {};
  var D = document;
  D.documentElement.classList.add('ch-nv');

  function ready(fn) { if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', fn); else fn(); }
  function el(tag, cls, text) {
    var e = D.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function icon(name) { var i = el('i', 'fa ' + name); i.setAttribute('aria-hidden', 'true'); return i; }
  function base() {
    if (typeof window.chBase === 'function') return window.chBase();
    var m = location.pathname.match(/^(.*?\/index\.php\/[^\/]+)/);
    return location.origin + (m ? m[1] : '');
  }
  function safe(u) {
    try { var x = new URL(u, location.href); return /^(https?|mailto):$/.test(x.protocol) ? x.href : ''; } catch (e) { return ''; }
  }
  function isHome() {
    return D.body.classList.contains('pkp_page_index') || !!D.querySelector('.page_index_journal');
  }
  function isArticle() { return /\/article\/view\//.test(location.pathname); }
  function blockTitle(b) {
    var t = b.querySelector('.title');
    return (t ? t.textContent : '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  /* ---------- 1. Ikon menu + menu aktif ---------- */
  var NAV_ICONS = [
    [/home|beranda/i, 'fa-home'], [/regist|daftar/i, 'fa-user-plus'], [/author|penulis/i, 'fa-pencil-square-o'],
    [/current/i, 'fa-book'], [/issue|archive|arsip/i, 'fa-book'], [/editor|board|team|reviewer/i, 'fa-users'],
    [/announce|pengumuman|news/i, 'fa-bullhorn'], [/about|tentang/i, 'fa-info-circle'], [/contact|kontak/i, 'fa-envelope']
  ];
  function navIcons() {
    var here = location.href.split(/[?#]/)[0].replace(/\/$/, '');
    [].forEach.call(D.querySelectorAll('#navigationPrimary > li'), function (li) {
      var a = li.querySelector(':scope > a');
      if (!a) return;
      if (!a.querySelector('.fa, i, svg')) {
        var t = a.textContent.trim();
        for (var k = 0; k < NAV_ICONS.length; k++) {
          if (NAV_ICONS[k][0].test(t)) { a.insertBefore(icon(NAV_ICONS[k][1] + ' ch-nav-ico'), a.firstChild); break; }
        }
      }
      var hit = [].some.call(li.querySelectorAll('a[href]'), function (x) {
        var raw = (x.getAttribute('href') || '').trim();
        if (!raw || raw.charAt(0) === '#' || /^javascript:/i.test(raw)) return false;   // induk dropdown (href="#")
        return x.href.split(/[?#]/)[0].replace(/\/$/, '') === here;
      });
      if (!hit && /\/index$/.test(here) && /home|beranda/i.test(a.textContent)) hit = true;
      li.classList.toggle('ch-active', hit);
    });
  }

  /* ---------- kolom sidebar (dibuat script layout 3 kolom) ---------- */
  function columns() {
    var sb = D.querySelector('.pkp_structure_sidebar');
    return {
      sb: sb,
      left: D.querySelector('.ch-sb-left') || sb,
      right: D.querySelector('.ch-sb-right') || sb
    };
  }
  function makeBlock(cls, title) {
    var b = el('div', 'pkp_block ch-nv-block ' + cls);
    if (title) b.appendChild(el('h2', 'title', title));
    var c = el('div', 'content'); b.appendChild(c);
    return { block: b, content: c };
  }

  /* ---------- 2. Kartu "Journal Issue" (cover) ---------- */
  function coverCard() {
    if (!isHome() || D.querySelector('.ch-cover-card, .jw-issue')) return;   // .jw-issue = widget Journal Issue sudah dipasang
    var img = D.querySelector('.current_issue .cover img, .current_issue .obj_issue_toc .cover img');
    var cols = columns();
    if (!img || !cols.left) return;
    var link = D.querySelector('.current_issue .cover a, .current_issue .heading a');
    var title = D.querySelector('.current_issue .current_issue_title') || D.querySelector('.current_issue .heading .title') ||
                D.querySelector('.current_issue .heading h2');
    var b = makeBlock('ch-cover-card ch-keep', 'Journal Issue');
    var a = el('a', 'ch-cc-img');
    a.href = link ? link.href : base() + '/issue/current';
    var im = el('img'); im.src = img.currentSrc || img.src; im.alt = 'Cover of the current issue'; im.loading = 'lazy';
    a.appendChild(im); b.content.appendChild(a);
    var t = title ? title.textContent.replace(/\s+/g, ' ').trim() : (img.getAttribute('alt') || '');
    if (t) b.content.appendChild(el('div', 'ch-cc-title', t.length > 70 ? t.slice(0, 70) + '…' : t));
    cols.left.insertBefore(b.block, cols.left.firstChild);
  }

  /* ---------- 3. Panel tombol cepat ---------- */
  var SOCIAL = {
    instagram: 'fa-instagram', whatsapp: 'fa-whatsapp', facebook: 'fa-facebook', twitter: 'fa-twitter', x: 'fa-twitter',
    youtube: 'fa-youtube-play', linkedin: 'fa-linkedin', telegram: 'fa-telegram', email: 'fa-envelope', mail: 'fa-envelope',
    tiktok: 'fa-music', website: 'fa-globe'
  };
  function quickPanel() {
    if (D.querySelector('.ch-quick, .jw-actions') || isArticle()) return;   // .jw-actions = widget Quick Actions sudah dipasang
    var cols = columns();
    if (!cols.sb) return;
    var b = makeBlock('ch-quick ch-keep', null);
    var btns = [
      [CFG.submitUrl || base() + '/about/submissions', 'fa-paper-plane', 'Submit Your Paper'],
      [CFG.reviewerUrl || base() + '/rolereg', 'fa-user-circle', 'Become a Reviewer']
    ];
    btns.forEach(function (x) {
      var u = safe(x[0]); if (!u) return;
      var a = el('a', 'ch-q-btn'); a.href = u;
      a.appendChild(icon(x[1])); a.appendChild(D.createTextNode(' ' + x[2]));
      b.content.appendChild(a);
    });
    var soc = (CFG.social || []).filter(function (s) { return s[0] && safe(s[1]); });
    if (soc.length) {
      var row = el('div', 'ch-q-social');
      soc.forEach(function (s) {
        var a = el('a'); a.href = safe(s[1]);
        if (!/^mailto:/i.test(a.href)) { a.target = '_blank'; a.rel = 'noopener'; }
        a.title = s[0]; a.setAttribute('aria-label', s[0]);
        a.appendChild(icon(SOCIAL[s[0].toLowerCase()] || 'fa-link'));
        row.appendChild(a);
      });
      b.content.appendChild(row);
    }
    if (isHome()) cols.right.insertBefore(b.block, cols.right.firstChild);
    else {
      // halaman lain: kolom kanan disembunyikan, panel ditaruh di kolom kiri setelah blok pertama
      var first = cols.left.firstElementChild;
      cols.left.insertBefore(b.block, first ? first.nextSibling : null);
    }
  }

  /* ---------- 4. Carousel logo indeksasi ---------- */
  function indexingItems() {
    var list = (CFG.indexing || []).filter(function (r) { return r[0] || r[1]; }).map(function (r) {
      return { name: r[0], img: safe(r[1]), href: safe(r[2]) };
    });
    if (list.length) return list;
    var blocks = D.querySelectorAll('.pkp_structure_sidebar .pkp_block');
    for (var i = 0; i < blocks.length; i++) {
      var t = blockTitle(blocks[i]);
      if (!/profile|index/.test(t)) continue;
      return [].map.call(blocks[i].querySelectorAll('.content a[href]'), function (a) {
        var im = a.querySelector('img');
        var name = (a.getAttribute('title') || a.textContent || (im && im.alt) || '').replace(/\s+/g, ' ').trim()
          .replace(/^.*?\bin\s+/i, '');
        return { name: name, img: im ? safe(im.getAttribute('src')) : '', href: safe(a.href) };
      }).filter(function (x) { return x.name || x.img; });
    }
    return [];
  }
  function indexingStrip() {
    if (!isHome() || D.querySelector('.ch-idx')) return;
    var items = indexingItems();
    var home = D.querySelector('.page_index_journal');
    if (!items.length || !home) return;
    var box = el('section', 'ch-idx');
    box.setAttribute('aria-label', 'Indexed and abstracted in');
    box.appendChild(el('div', 'ch-idx-h', 'Indexed & Abstracted in'));
    var vp = el('div', 'ch-idx-vp'), tr = el('div', 'ch-idx-track');
    function add(it, clone) {
      var a = el(it.href ? 'a' : 'span', 'ch-idx-item' + (it.img ? ' has-img' : ''));
      if (it.href) { a.href = it.href; a.target = '_blank'; a.rel = 'noopener'; }
      a.title = it.name;
      if (clone) { a.setAttribute('aria-hidden', 'true'); a.tabIndex = -1; }
      if (it.img) { var im = el('img'); im.src = it.img; im.alt = clone ? '' : it.name; im.loading = 'lazy'; a.appendChild(im); }
      else {
        var mark = el('span', 'ch-idx-mark', it.name.charAt(0).toUpperCase());
        a.appendChild(mark); a.appendChild(el('span', 'ch-idx-name', it.name));
      }
      tr.appendChild(a);
    }
    items.forEach(function (it) { add(it, false); });
    if (items.length > 3) items.forEach(function (it) { add(it, true); });   // salinan agar putaran mulus
    else box.classList.add('ch-idx-static');
    tr.style.setProperty('--ch-idx-dur', Math.max(20, items.length * 4) + 's');
    vp.appendChild(tr); box.appendChild(vp);
    var after = D.querySelector('.ch-sl-all') || D.querySelector('.page_index_journal .cmp_announcements');
    if (after && after.parentNode) after.parentNode.insertBefore(box, after.nextSibling);
    else home.insertBefore(box, home.firstChild);
  }

  /* ---------- 5. Tab Current Issue | Upcoming Issue ---------- */
  function issueTabs() {
    var cur = D.querySelector('.page_index_journal .current_issue');
    if (!cur || cur.closest('.ch-itabs')) return;
    var up = D.querySelector('.ch-upcoming');
    var box = el('div', 'ch-itabs');
    var nav = el('div', 'ch-itabs-nav'); nav.setAttribute('role', 'tablist');
    var panels = el('div', 'ch-itabs-panels');
    cur.parentNode.insertBefore(box, cur);
    box.appendChild(nav); box.appendChild(panels);
    var defs = [{ id: 'current', label: 'Current Issue', ico: 'fa-calendar-check-o', node: cur }];
    if (up && CFG.upcoming !== false) defs.push({ id: 'upcoming', label: 'Upcoming Issue', ico: 'fa-calendar', node: up });
    var btns = [];
    defs.forEach(function (d, i) {
      var b = el('button', 'ch-itab'); b.type = 'button'; b.id = 'ch-itab-' + d.id;
      b.setAttribute('role', 'tab'); b.setAttribute('aria-controls', 'ch-ipanel-' + d.id);
      b.appendChild(icon(d.ico)); b.appendChild(D.createTextNode(' ' + d.label));
      var p = el('div', 'ch-ipanel'); p.id = 'ch-ipanel-' + d.id;
      p.setAttribute('role', 'tabpanel'); p.setAttribute('aria-labelledby', b.id);
      d.node.hidden = false; p.appendChild(d.node);
      nav.appendChild(b); panels.appendChild(p); btns.push(b);
      b.addEventListener('click', function () { show(i); });
      b.addEventListener('keydown', function (e) {
        var k = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (k) { e.preventDefault(); var n = (i + k + btns.length) % btns.length; btns[n].focus(); show(n); }
      });
    });
    function show(n) {
      btns.forEach(function (b, j) {
        var on = j === n;
        b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1;
        panels.children[j].hidden = !on;
      });
    }
    show(0);
    if (up && !up.parentNode.classList.contains('ch-ipanel')) up.parentNode.removeChild(up);
  }

  /* ---------- 6. Thumbnail pengganti pada kartu artikel ---------- */
  function thumbs() {
    [].forEach.call(D.querySelectorAll('.cmp_article_list .obj_article_summary, .ch-nv-cards .obj_article_summary'), function (card) {
      card.classList.add('ch-card');
      if (card.querySelector(':scope > .ch-thumb')) return;
      var pill = card.querySelector('.ch-pill');
      var sec = pill ? pill.textContent : (card.closest('.section') && card.closest('.section').querySelector('h2') || {}).textContent || '';
      var abbr = (sec || 'CHEESA').replace(/[^A-Za-z ]/g, ' ').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 3).toUpperCase() || 'CH';
      var a = el('a', 'ch-thumb');
      var link = card.querySelector('.title a');
      if (link) { a.href = link.href; a.tabIndex = -1; a.setAttribute('aria-hidden', 'true'); }
      a.appendChild(el('span', 'ch-thumb-t', abbr));
      var title = card.querySelector('.title');
      card.insertBefore(a, title || card.firstChild);
    });
  }

  /* ---------- 7. Author Diversity ---------- */
  var PALETTE = ['var(--nv-navy,#030271)', 'var(--nv-accent,#ed991f)', '#2f6fd6', '#c23b72', '#2a9d8f', '#8a8fa8'];
  function diversity(host) {
    var table = host.querySelector('table');
    if (!table || table.getAttribute('data-ch-dv')) return;
    table.setAttribute('data-ch-dv', '1');
    var rows = [].map.call(table.querySelectorAll('tbody tr'), function (tr) {
      var td = tr.querySelectorAll('td');
      return { tr: tr, name: td[1] ? td[1].textContent.trim() : '', n: td[2] ? parseInt(td[2].textContent, 10) || 0 : 0 };
    }).filter(function (r) { return r.name && r.n; });
    if (!rows.length) return;
    var total = rows.reduce(function (s, r) { return s + r.n; }, 0);
    var bar = el('div', 'ch-dv-bar'); bar.setAttribute('aria-hidden', 'true');
    var other = 0;
    rows.forEach(function (r, i) {
      var color = PALETTE[Math.min(i, PALETTE.length - 1)];
      if (i < PALETTE.length - 1) {
        var s = el('span'); s.style.width = (r.n / total * 100) + '%'; s.style.background = color; s.title = r.name;
        bar.appendChild(s);
      } else other += r.n;
      var nameCell = r.tr.querySelectorAll('td')[1];
      if (!nameCell.querySelector('.ch-dv-dot')) {
        var dot = el('span', 'ch-dv-dot'); dot.style.background = color;
        nameCell.insertBefore(dot, nameCell.firstChild);
        var f = window.chFlagFor && window.chFlagFor(r.name);
        if (f && /^https:\/\/flagcdn\.com\//.test(f.src)) {
          var img = el('img', 'ch-dv-flag'); img.src = f.src; img.alt = ''; img.loading = 'lazy';
          nameCell.insertBefore(img, dot.nextSibling);
        }
      }
      var pc = el('span', 'ch-dv-pct', Math.round(r.n / total * 100) + '%');
      r.tr.querySelectorAll('td')[2].appendChild(pc);
    });
    if (other) { var o = el('span'); o.style.width = (other / total * 100) + '%'; o.style.background = PALETTE[PALETTE.length - 1]; o.title = 'Other'; bar.appendChild(o); }
    table.parentNode.insertBefore(bar, table);
    var blk = host.closest('.pkp_block');
    var t = blk && blk.querySelector('.title');
    if (t) {
      var old = t.querySelector('.ch-dv-chip'); if (old) old.parentNode.removeChild(old);
      t.appendChild(el('span', 'ch-dv-chip', rows.length + (rows.length === 1 ? ' country' : ' countries')));
    }
  }
  function watchDiversity() {
    var host = D.getElementById('ch-country');
    if (!host) return;
    diversity(host);
    if (window.MutationObserver) new MutationObserver(function () { diversity(host); }).observe(host, { childList: true });
  }



  /* Ikon SVG untuk kartu artikel: tidak bergantung pada font FontAwesome / CSS lain di situs */
  var SVG_ICONS = {
    eye: 'M12 5C6.5 5 2.7 9.1 1.5 12c1.2 2.9 5 7 10.5 7s9.3-4.1 10.5-7C21.3 9.1 17.5 5 12 5zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
    download: 'M11 3h2v9.2l3.3-3.3 1.4 1.4L12 16l-5.7-5.7 1.4-1.4 3.3 3.3V3zM4 18h16v2.5H4z',
    file: 'M6 2h8l5 5v15H6V2zm7 1.5V8h4.5L13 3.5zM8.5 11.5h8v1.6h-8zm0 3.5h8v1.6h-8zm0 3.5h5v1.6h-5z',
    calendar: 'M7 2h2v2h6V2h2v2h3v17H4V4h3V2zM6 9v10h12V9H6zm4.2 7.6L7.6 14l1.1-1.1 1.5 1.5 3.9-3.9 1.1 1.1-5 5z'
  };
  function svgIcon(name) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = D.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '14'); svg.setAttribute('height', '14');
    svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false'); svg.setAttribute('class', 'ch-ico');
    var path = D.createElementNS(ns, 'path');
    path.setAttribute('d', SVG_ICONS[name]); path.setAttribute('fill', 'currentColor'); path.setAttribute('fill-rule', 'evenodd');
    svg.appendChild(path);
    return svg;
  }

  /* ---------- 8. Kartu artikel: negara & statistik dari server ---------- */
  function richCards() {
    [].forEach.call(D.querySelectorAll('.obj_article_summary'), function (card) {
      var src = card.querySelector('script.ch-sum-data');
      if (!src || card.classList.contains('ch-rich')) return;
      var d; try { d = JSON.parse(src.textContent); } catch (e) { return; }
      card.classList.add('ch-rich');
      card.setAttribute('data-ch-filled', '1'); card.setAttribute('data-ch-queued', '1');
      var title = card.querySelector('.title');
      var pages = card.querySelector('.pages');
      if (d.authors && d.authors.length) {
        var list = el('div', 'ch-au-list');
        d.authors.forEach(function (a) {
          var row = el('div', 'ch-au-row');
          row.appendChild(el('b', null, a.name));
          var place = [a.aff, a.country].filter(Boolean).join(', ');
          if (place) row.appendChild(D.createTextNode(', ' + place));
          if (a.cc && /^[A-Z]{2}$/.test(a.cc)) {
            var f = el('img', 'ch-cflag'); f.src = 'https://flagcdn.com/w40/' + a.cc.toLowerCase() + '.png';
            f.alt = a.country || a.cc; f.title = a.country || a.cc; f.loading = 'lazy'; row.appendChild(f);
          }
          if (a.orcid && safe(a.orcid)) row.appendChild(orcidLink(safe(a.orcid), a.name));
          list.appendChild(row);
        });
        card.insertBefore(list, title ? title.nextSibling : card.firstChild);
      }
      var st = el('div', 'ch-cstats');
      function item(ico, label, val) { var x = el('span'); x.appendChild(svgIcon(ico)); x.appendChild(D.createTextNode(' ' + label + ' ')); x.appendChild(el('b', null, String(val))); st.appendChild(x); }
      item('eye', 'Views', d.views || 0);
      item('download', 'Downloads', d.downloads || 0);
      // Pages tampil di samping label seksi (baris paling atas kartu); bila tidak ada seksi, tetap di deretan statistik
      var pg = pages && pages.textContent.trim();
      var secH = card.closest('.section') && card.closest('.section').querySelector(':scope > h2');
      var secName = secH ? secH.textContent.replace(/\s+/g, ' ').trim() : '';
      var oldPill = card.querySelector(':scope > .ch-pill');
      if (!secName && oldPill) secName = oldPill.textContent.trim();
      if (secName && !card.querySelector(':scope > .ch-card-top')) {
        var top = el('div', 'ch-card-top');
        top.appendChild(el('span', 'ch-sec-badge', secName));
        if (pg) { var pb = el('span', 'ch-pages-badge'); pb.appendChild(svgIcon('file')); pb.appendChild(D.createTextNode(' Pages ')); pb.appendChild(el('b', null, pg)); top.appendChild(pb); }
        card.insertBefore(top, title || card.firstChild);
        card.classList.add('ch-has-top');
      } else if (pg) item('file', 'Pages', pg);
      if (d.date) item('calendar', 'Published', d.date);
      if (d.doi) {
        var a = el('a', 'ch-cdoi'); a.href = 'https://doi.org/' + d.doi; a.target = '_blank'; a.rel = 'noopener';
        a.appendChild(el('span', 'ch-doi-ico', 'doi')); a.appendChild(D.createTextNode(' ' + d.doi)); st.appendChild(a);
      }
      var gl = card.querySelector('.galleys_links');
      card.insertBefore(st, gl || null);
    });
  }

  /* ---------- 9. Pencarian: selalu menuju halaman search OJS ---------- */
  function fixSearch() {
    var action = CFG.searchUrl || (function () { var a = D.querySelector('.pkp_navigation_search_wrapper a[href]'); return a ? a.href : base() + '/search/search'; })();
    var forms = D.querySelectorAll('form.ch-search');
    if (!forms.length) {
      var right = D.querySelector('.ch-nav-right') || D.querySelector('.pkp_navigation_primary_row');
      if (!right) return;
      var f = el('form', 'ch-search'); f.setAttribute('role', 'search');
      var inp = el('input'); inp.type = 'search'; inp.name = 'query'; inp.placeholder = 'Search articles…'; inp.setAttribute('aria-label', 'Search articles');
      var b = el('button'); b.type = 'submit'; b.appendChild(icon('fa-search')); b.appendChild(el('span', null, 'Search'));
      f.appendChild(inp); f.appendChild(b); right.appendChild(f);
      if (right.classList.contains('ch-nav-right')) right.classList.add('ch-has-form');
      forms = [f];
    }
    [].forEach.call(forms, function (f) {
      if (f.getAttribute('data-ch-fixed')) return;
      f.setAttribute('data-ch-fixed', '1');
      f.method = 'get'; f.action = action;
      var inp = f.querySelector('input'); if (inp) inp.name = 'query';
      if (!f.querySelector('button')) { var bb = el('button'); bb.type = 'submit'; bb.appendChild(icon('fa-search')); bb.appendChild(el('span', null, 'Search')); f.appendChild(bb); }
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = inp ? inp.value.trim() : '';
        location.href = action + (action.indexOf('?') > -1 ? '&' : '?') + 'query=' + encodeURIComponent(q);
      });
    });
  }

  /* ---------- 10. Menu Login / pengguna di kanan atas header (di samping ISSN) ----------
     Tautan dibuat dari data server (CFG.user), jadi selalu tampil walau menu pengguna bawaan OJS tersembunyi. */
  /* Tautan dari menu pengguna OJS (Settings > Website > Setup > Navigation Menus > User Navigation Menu),
     supaya perubahan di menu itu (mis. Register ke URL lain, item tambahan) ikut tampil. */
  function ojsUserLinks(loggedIn) {
    var nav = D.getElementById('navigationUser') || D.querySelector('.pkp_navigation_user');
    if (!nav) return null;
    var out = [];
    function item(a) {
      var t = (a.textContent || '').replace(/\s+/g, ' ').trim();
      var h = a.getAttribute('href') || '';
      return t ? [t.replace(/\s*\d+\s*$/, '') || t, /^javascript:/i.test(h) ? '' : a.href] : null;
    }
    try {
      if (loggedIn) {
        nav.querySelectorAll('li ul a').forEach(function (a) { var x = item(a); if (x && x[1]) out.push(x); });
      } else {
        // item utama + sub-item (mis. Register > Author, Editor/Reviewer) dari User Navigation Menu
        nav.querySelectorAll(':scope > li').forEach(function (li) {
          var a = li.querySelector(':scope > a'); if (!a) return;
          var x = item(a); if (!x) return;
          var kids = [];
          li.querySelectorAll(':scope > ul a').forEach(function (c) { var k = item(c); if (k && k[1]) kids.push(k); });
          if (kids.length) x[2] = kids;
          if (x[1] || kids.length) out.push(x);
        });
      }
    } catch (e) { return null; }
    return out.length ? out : null;
  }
  function userIcon(text, href) {
    var s = (text + ' ' + href).toLowerCase();
    if (/logout|sign ?out|signout|keluar/.test(s)) return 'fa-sign-out';
    if (/login|sign ?in|masuk/.test(s)) return 'fa-sign-in';
    var t = String(text).toLowerCase();
    if (/review|editor/.test(t)) return 'fa-users';
    if (/author|penulis/.test(t)) return 'fa-pencil-square-o';
    if (/regist|daftar|auth-reg|sign ?up/.test(s)) return 'fa-user-plus';
    if (/dashboard|submissions/.test(s)) return 'fa-tachometer';
    if (/profile|profil/.test(s)) return 'fa-id-card-o';
    if (/admin/.test(s)) return 'fa-cogs';
    return 'fa-angle-right';
  }

  /* daftar turun untuk tombol pengguna: klik untuk buka/tutup, Esc / klik di luar untuk menutup */
  function userDrop(li, btn, links) {
    var sub = el('ul', 'ch-user-sub'); sub.hidden = true;
    links.forEach(function (l) {
      var a = el('a'); a.href = l[1]; a.appendChild(icon(userIcon(l[0], l[1]))); a.appendChild(D.createTextNode(' ' + l[0]));
      var x = el('li'); x.appendChild(a); sub.appendChild(x);
    });
    function set(open) { sub.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false'); li.classList.toggle('is-open', open); }
    var hoverAt = 0;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!sub.hidden && Date.now() - hoverAt < 600) return;   // baru dibuka oleh hover: klik jangan menutupnya
      set(sub.hidden);
    });
    D.addEventListener('click', function (e) { if (!li.contains(e.target)) set(false); });
    D.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    if (window.matchMedia && window.matchMedia('(hover: hover) and (min-width: 992px)').matches) {
      var t; li.addEventListener('mouseenter', function () { clearTimeout(t); if (sub.hidden) { hoverAt = Date.now(); set(true); } });
      li.addEventListener('mouseleave', function () { t = setTimeout(function () { set(false); }, 220); });
    }
    return sub;
  }

  function userBar() {
    var head = D.getElementById('headerNavigationContainer') || D.querySelector('.pkp_structure_head');
    if (!head || D.querySelector('.ch-userbar')) return;
    var u = CFG.user;
    if (u) {
      var fromMenu = ojsUserLinks(!!u.name);
      if (fromMenu) u = { name: u.name, links: fromMenu };
    }
    var bar = el('div', 'ch-userbar');
    if (u && u.links && u.links.length) {
      var ul = el('ul', 'ch-user-menu');
      if (u.name) {
        // sudah login: tombol nama pengguna + daftar tautan
        var li = el('li', 'ch-user-drop');
        var btn = el('button', 'ch-user-btn'); btn.type = 'button'; btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-haspopup', 'true');
        btn.appendChild(icon('fa-user-circle')); btn.appendChild(D.createTextNode(' ' + u.name + ' ')); btn.appendChild(icon('fa-caret-down'));
        li.appendChild(btn); li.appendChild(userDrop(li, btn, u.links)); ul.appendChild(li);
      } else {
        u.links.forEach(function (l) {
          if (l[2] && l[2].length) {
            var g = el('li', 'ch-user-drop ch-user-group');
            var gb = el('button', 'ch-user-grp'); gb.type = 'button'; gb.setAttribute('aria-expanded', 'false'); gb.setAttribute('aria-haspopup', 'true');
            gb.appendChild(icon(userIcon(l[0], l[1]))); gb.appendChild(D.createTextNode(' ' + l[0] + ' ')); gb.appendChild(icon('fa-caret-down'));
            g.appendChild(gb); g.appendChild(userDrop(g, gb, l[2])); ul.appendChild(g);
            return;
          }
          var ic = userIcon(l[0], l[1]);
          var li2 = el('li'), a = el('a', ic === 'fa-sign-in' ? 'ch-user-login' : 'ch-user-reg');
          a.href = l[1]; a.appendChild(icon(ic)); a.appendChild(D.createTextNode(' ' + l[0]));
          li2.appendChild(a); ul.appendChild(li2);
        });
      }
      bar.appendChild(ul);
      D.body.classList.add('ch-has-userbar');   // menu pengguna bawaan disembunyikan agar tidak dobel
    } else {
      // cadangan: pindahkan menu pengguna bawaan OJS
      var user = D.getElementById('navigationUserWrapper') || D.querySelector('.pkp_navigation_user_wrapper');
      if (!user) return;
      bar.appendChild(user);
    }
    var issn = head.querySelector('.ch-issn');
    if (issn) { issn.appendChild(bar); topOfHeader(issn); }
    else { bar.classList.add('ch-userbar-solo'); head.insertBefore(bar, head.firstChild); }
    placeUserBar();
    if (window.matchMedia) {
      var mq = window.matchMedia('(min-width: 992px)');
      if (mq.addEventListener) mq.addEventListener('change', placeUserBar); else if (mq.addListener) mq.addListener(placeUserBar);
    }
  }
  /* di HP baris ISSN + Login tampil sebagai bilah tipis paling atas header */
  function topOfHeader(node) {
    var head = D.getElementById('headerNavigationContainer') || D.querySelector('.pkp_structure_head');
    if (head && node && head.firstElementChild !== node) head.insertBefore(node, head.firstChild);
  }
  /* baris ISSN bisa dibuat belakangan oleh script lain: pindahkan tombol ke sana */
  /* Layar lebar: tombol Login/pengguna di bar menu, sebelah kiri Home.
     HP/tablet: di bilah atas header (menu utama tersembunyi di balik tombol hamburger). */
  function placeUserBar() {
    var bar = D.querySelector('.ch-userbar');
    if (!bar) return;
    var wide = !window.matchMedia || window.matchMedia('(min-width: 992px)').matches;
    var left = D.querySelector('.ch-nav-left');
    var issn = D.querySelector('.ch-issn');
    if (wide && left) {
      if (bar.parentNode !== left) { left.appendChild(bar); }
      bar.classList.remove('ch-userbar-solo'); bar.classList.add('ch-userbar-nav');
    } else {
      bar.classList.remove('ch-userbar-nav');
      if (issn) { if (bar.parentNode !== issn) issn.appendChild(bar); bar.classList.remove('ch-userbar-solo'); topOfHeader(issn); }
      else { var head = D.getElementById('headerNavigationContainer') || D.querySelector('.pkp_structure_head'); if (head && bar.parentNode !== head) { bar.classList.add('ch-userbar-solo'); head.insertBefore(bar, head.firstChild); } }
    }
  }

  /* ---------- 11. Slider pengumuman: cadangan bila beranda tidak menampilkan pengumuman ---------- */
  function annFallback() {
    var home = D.querySelector('.page_index_journal');
    if (!home || D.querySelector('.ch-sl') || D.querySelector('.ch-ann-fb') || window.chInFrame) return;
    var url = CFG.announcementUrl || base() + '/announcement';
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (t) {
      if (!t || D.querySelector('.ch-sl')) return;
      var doc = new DOMParser().parseFromString(t, 'text/html');
      var items = [].slice.call(doc.querySelectorAll('.obj_announcement_summary')).slice(0, 6);
      if (!items.length) return;
      var wrap = el('div', 'ch-sl ch-ann-fb'); wrap.setAttribute('role', 'region'); wrap.setAttribute('aria-roledescription', 'carousel'); wrap.setAttribute('aria-label', 'Announcements');
      var vp = el('div', 'ch-sl-vp'), tr = el('div', 'ch-sl-tr'); vp.appendChild(tr); wrap.appendChild(vp);
      items.forEach(function (it) {
        var sl = el('div', 'ch-sl-slide');
        var h = it.querySelector('h2 a, h3 a, h4 a');
        if (h) { var tt = el('div', 'ch-sl-title'), ta = el('a', null, h.textContent.replace(/\s+/g, ' ').trim()); ta.href = h.href; tt.appendChild(ta); sl.appendChild(tt); }
        sl.appendChild(D.importNode(it, true)); tr.appendChild(sl);
      });
      var n = items.length, i = 0, timer = null, dots = el('div', 'ch-sl-dots');
      function go(k) { i = (k + n) % n; tr.style.transform = 'translateX(' + (-100 * i) + '%)'; [].forEach.call(dots.children, function (d, j) { d.classList.toggle('on', j === i); }); }
      function start() { clearInterval(timer); if (n > 1 && !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) timer = setInterval(function () { go(i + 1); }, 12000); }
      if (n > 1) {
        var pv = el('button', 'ch-sl-btn ch-sl-prev'); pv.type = 'button'; pv.innerHTML = '&#8249;'; pv.setAttribute('aria-label', 'Previous announcement');
        var nx = el('button', 'ch-sl-btn ch-sl-next'); nx.type = 'button'; nx.innerHTML = '&#8250;'; nx.setAttribute('aria-label', 'Next announcement');
        pv.addEventListener('click', function () { go(i - 1); start(); }); nx.addEventListener('click', function () { go(i + 1); start(); });
        items.forEach(function (x, k) { var d = el('button', 'ch-sl-dot'); d.type = 'button'; d.setAttribute('aria-label', 'Go to announcement ' + (k + 1)); d.addEventListener('click', function () { go(k); start(); }); dots.appendChild(d); });
        wrap.appendChild(pv); wrap.appendChild(nx); wrap.appendChild(dots);
        wrap.addEventListener('mouseenter', function () { clearInterval(timer); }); wrap.addEventListener('mouseleave', start);
      }
      var all = el('div', 'ch-sl-all'); var aa = el('a'); aa.href = url; aa.innerHTML = 'All announcements &rarr;'; all.appendChild(aa);
      home.insertBefore(all, home.firstChild); home.insertBefore(wrap, all);
      go(0); start();
      var idx = D.querySelector('.ch-idx'); if (idx && idx.parentNode === home) home.insertBefore(idx, all.nextSibling);
    }).catch(function () {});
  }

  /* ---------- 12. Published Volumes menggantikan Journal Archive ---------- */
  function archiveDedupe() {
    var vol = D.querySelector('.jw-volumes');
    if (!vol) return;
    [].forEach.call(D.querySelectorAll('#ch-archive'), function (a) {
      var blk = a.closest('.pkp_block') || a;
      if (blk.contains(vol)) return;
      if (blk.parentNode && blk.parentNode === vol.parentNode) blk.parentNode.removeChild(blk);
      else if (blk.parentNode) { blk.parentNode.replaceChild(vol, blk); }   // widget mengambil posisi Journal Archive
    });
    vol.classList.add('ch-keep');
  }

  /* ---------- 13. Blok sidebar kiri per halaman (opsi tema) ---------- */
  function blockKey(b) {
    var k = [];
    if (b.matches('.ch-art-info')) k.push('article info');
    if (b.matches('.ch-quick, .jw-actions')) k.push('actions');
    if (b.matches('.jw-volumes') || b.querySelector('#ch-archive, .ch-ar')) k.push('published volumes', 'journal archive');
    if (b.querySelector('a[href$="/focusscope"]')) k.push('additional information');
    if (b.querySelector('#ch-country, .ch-cs') || b.matches('.jw-diversity')) k.push('diversity of authors', 'author diversity');
    var t = b.querySelector(':scope > .title, :scope > h2');
    if (t) k.push(t.textContent.replace(/\s+/g, ' ').trim().toLowerCase());
    return k.join(' | ');
  }
  function pagePath() {
    var b = (CFG.base || '').replace(/\/index\/?$/, '');
    var p = location.href.split(/[?#]/)[0];
    if (b && p.indexOf(b) === 0) p = p.slice(b.length);
    else { var m = location.pathname.match(/\/index\.php\/[^\/]+\/(.*)$/); p = m ? m[1] : ''; }
    return p.replace(/^\/+|\/+$/g, '').toLowerCase();
  }
  function sidebarRules() {
    var rules = CFG.sidebarPages || [];
    if (!rules.length || isHome()) return;
    var left = D.querySelector('.ch-sb-left'), right = D.querySelector('.ch-sb-right');
    if (!left || !right) return;
    var path = pagePath(), best = null, bestLen = -1;
    rules.forEach(function (r) {
      r[0].forEach(function (pg) {
        var ok = pg === '*' || path === pg || path.indexOf(pg + '/') === 0;
        var len = pg === '*' ? 0 : pg.length;
        if (ok && len > bestLen) { best = r[1]; bestLen = len; }
      });
    });
    if (!best) return;
    var pool = [].slice.call(left.children).concat([].slice.call(right.children));
    var used = [];
    best.forEach(function (name) {
      for (var i = 0; i < pool.length; i++) {
        if (used.indexOf(pool[i]) > -1) continue;
        if (blockKey(pool[i]).indexOf(name) > -1) { used.push(pool[i]); break; }
      }
    });
    pool.forEach(function (b) { if (used.indexOf(b) < 0) { b.classList.remove('ch-keep'); right.appendChild(b); } });
    used.forEach(function (b) { b.classList.add('ch-keep'); left.appendChild(b); });
    D.body.classList.add('ch-sb-ruled');
  }

  /* ---------- 14. Halaman artikel: kepala (hero) modern ---------- */
  function metaVal(label) {
    var out = '';
    [].forEach.call(D.querySelectorAll('.ch-meta .ch-meta-item'), function (it) {
      var l = it.querySelector('.ch-meta-label'), v = it.querySelector('.ch-meta-val');
      if (l && v && l.textContent.trim().toLowerCase() === label) out = v.textContent.replace(/\s+/g, ' ').trim();
    });
    return out;
  }

  /* data penulis & DOI dari server (metadata OJS), bukan dari Author Biographies */
  function articleData() {
    if (articleData.d !== undefined) return articleData.d;
    var d = null;
    try { var j = D.getElementById('ch-article-data'); if (j) d = JSON.parse(j.textContent); } catch (e) {}
    articleData.d = d;
    return d;
  }
  function articleDoi() {
    var d = articleData();
    var doi = (d && d.doi) || '';
    if (!doi) { var m = D.querySelector('meta[name="citation_doi"]'); if (m) doi = m.getAttribute('content') || ''; }
    if (!doi) doi = metaVal('doi');
    return doi.replace(/^https?:\/\/(dx\.)?doi\.org\//i, '').trim();
  }
  function orcidLink(url, name) {
    var o = el('a', 'ch-orcid'); o.href = url; o.target = '_blank'; o.rel = 'noopener';
    o.title = 'ORCID: ' + url.replace(/^https?:\/\/(www\.)?orcid\.org\//, ''); o.setAttribute('aria-label', 'ORCID iD ' + name);
    var im = el('img'); im.src = 'https://orcid.org/assets/vectors/orcid.logo.icon.svg'; im.alt = 'ORCID iD'; im.width = 16; im.height = 16;
    im.onerror = function () { o.textContent = 'iD'; o.classList.add('ch-orcid-txt'); };
    o.appendChild(im); return o;
  }
  function flagImg(cc, name) {
    var f = el('img', 'ch-cflag'); f.src = 'https://flagcdn.com/w40/' + cc.toLowerCase() + '.png';
    f.alt = name || cc; f.title = name || cc; f.loading = 'lazy'; return f;
  }

  /* Article Info: Views, Downloads (statistik OJS) dan sitasi Dimensions */
  function metaCell(cls, ico, label, valNode) {
    var c = el('div', 'ch-meta-item ' + cls); c.appendChild(icon(ico));
    var b = el('div'); b.appendChild(el('span', 'ch-meta-label', label));
    var v = el('span', 'ch-meta-val'); v.appendChild(valNode); b.appendChild(v); c.appendChild(b);
    return c;
  }
  function articleMetrics(d) {
    var meta = D.querySelector('.ch-meta');
    if (!meta || meta.querySelector('.ch-meta-stats')) return;
    var row = el('div', 'ch-meta-stats');
    row.appendChild(metaCell('ch-m-views', 'fa-eye', 'Views', D.createTextNode(String(d.views || 0))));
    row.appendChild(metaCell('ch-m-dl', 'fa-download', 'Downloads', D.createTextNode(String(d.downloads || 0))));
    var doi = articleDoi();
    if (doi) {
      var link = el('a', 'ch-dim-link', '…');
      link.href = 'https://badge.dimensions.ai/details/doi/' + encodeURIComponent(doi); link.target = '_blank'; link.rel = 'noopener';
      link.title = 'Citations in Dimensions';
      row.appendChild(metaCell('ch-m-cite', 'fa-quote-right', 'Citations (Dimensions)', link));
      fetch('https://metrics-api.dimensions.ai/doi/' + encodeURIComponent(doi))
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { link.textContent = j && typeof j.times_cited === 'number' ? String(j.times_cited) : '0'; })
        .catch(function () {
          // cadangan: badge resmi Dimensions
          link.textContent = '';
          var sp = el('span', '__dimensions_badge_embed__'); sp.setAttribute('data-doi', doi); sp.setAttribute('data-style', 'small_rectangle');
          link.appendChild(sp);
          if (!D.querySelector('script[src*="badge.dimensions.ai"]')) { var sc = el('script'); sc.async = true; sc.src = 'https://badge.dimensions.ai/badge.js'; D.head.appendChild(sc); }
          else if (window.__dimensions_embed && window.__dimensions_embed.addBadges) window.__dimensions_embed.addBadges();
        });
    }
    meta.appendChild(row);
  }

  function articleAuthors(head) {
    var d = articleData();
    if (!d || !d.authors || !d.authors.length || head.getAttribute('data-ch-au')) return;
    head.setAttribute('data-ch-au', '1');
    var line = head.querySelector('.ch-authors'), affList = head.querySelector('.ch-affs');
    // email korespondensi yang sudah ditemukan script lama dipertahankan (dicocokkan lewat nama)
    var mails = {};
    if (line) [].forEach.call(line.querySelectorAll('.ch-au'), function (au) {
      var n = au.querySelector('.ch-au-name'), m = au.querySelector('.ch-au-mail');
      if (n && m) mails[n.textContent.trim().toLowerCase()] = m;
    });
    var affs = [];
    d.authors.forEach(function (a) {
      var key = [a.aff, a.country].filter(Boolean).join(', ');
      a._k = 0;
      if (key) { var i = affs.map(function (x) { return x.key; }).indexOf(key); if (i < 0) { affs.push({ key: key, cc: a.cc, country: a.country }); i = affs.length - 1; } a._k = i + 1; }
    });
    var multi = affs.length > 1;
    if (!line) { line = el('div', 'ch-authors'); head.insertBefore(line, head.firstChild); }
    line.innerHTML = '';
    d.authors.forEach(function (a, i) {
      var s = el('span', 'ch-au'); s.appendChild(el('span', 'ch-au-name', a.name));
      if (multi && a._k) s.appendChild(el('sup', null, String(a._k)));
      var m = mails[a.name.trim().toLowerCase()];
      if (a.corr) {
        // penanda penulis korespondensi (Primary Contact)
        var cm = el(a.email ? 'a' : 'span', 'ch-corr-mark');
        if (a.email) cm.href = 'mailto:' + a.email;
        cm.title = 'Corresponding author' + (a.email ? ': ' + a.email : '');
        cm.setAttribute('aria-label', cm.title);
        cm.appendChild(icon('fa-envelope'));
        s.appendChild(cm);
      } else if (m) s.appendChild(m);
      if (a.cc && /^[A-Z]{2}$/.test(a.cc)) s.appendChild(flagImg(a.cc, a.country));
      if (a.orcid && safe(a.orcid)) s.appendChild(orcidLink(safe(a.orcid), a.name));
      line.appendChild(s);
      if (i < d.authors.length - 1) line.appendChild(D.createTextNode(', '));
    });
    if (affs.length) {
      if (!affList) { affList = el('ol', 'ch-affs'); line.parentNode.insertBefore(affList, line.nextSibling); }
      affList.innerHTML = '';
      affs.forEach(function (x, i) {
        var li = el('li');
        if (multi) li.appendChild(el('b', null, String(i + 1)));
        var t = el('span', 'ch-aff-t', x.key); li.appendChild(t);
        if (x.cc && /^[A-Z]{2}$/.test(x.cc)) li.appendChild(flagImg(x.cc, x.country));
        affList.appendChild(li);
      });
    }
    // cukup ikon email di samping nama; baris "Corresponding author: ..." tidak ditampilkan
    var oldNote = head.querySelector('.ch-corr-note'); if (oldNote) oldNote.parentNode.removeChild(oldNote);
    articleMetrics(d);
    // DOI juga di panel Article Info bila belum ada
    var doi = articleDoi(), meta = D.querySelector('.ch-meta');
    if (doi && meta && !metaVal('doi')) {
      var c = el('div', 'ch-meta-item ch-meta-doi'); c.appendChild(icon('fa-link'));
      var b = el('div'); b.appendChild(el('span', 'ch-meta-label', 'DOI'));
      var v = el('span', 'ch-meta-val'), a = el('a', null, doi); a.href = 'https://doi.org/' + doi; a.target = '_blank'; a.rel = 'noopener';
      v.appendChild(a); b.appendChild(v); c.appendChild(b); meta.appendChild(c);
    }
  }


  /* tanggal proses, editor, hak cipta & lisensi di bawah kepala artikel */
  var CC_NAMES = { 'by': 'Attribution', 'by-sa': 'Attribution-ShareAlike', 'by-nd': 'Attribution-NoDerivatives', 'by-nc': 'Attribution-NonCommercial',
    'by-nc-sa': 'Attribution-NonCommercial-ShareAlike', 'by-nc-nd': 'Attribution-NonCommercial-NoDerivatives' };
  function licenseName(url) {
    var m = String(url || '').match(/creativecommons\.org\/licenses\/([a-z\-]+)\/(\d\.\d)/i);
    return m && CC_NAMES[m[1].toLowerCase()] ? 'Creative Commons ' + CC_NAMES[m[1].toLowerCase()] + ' ' + m[2] + ' International License' : '';
  }
  function articleFacts(hero) {
    var d = articleData(), f = d && d.facts;
    if (!f || D.querySelector('.ch-art-facts')) return;
    var box = el('section', 'ch-art-facts'); box.setAttribute('aria-label', 'Article history and license');
    var steps = [['Received', f.received], ['Revised', f.revised], ['Accepted', f.accepted], ['Available online', f.online],
                 ['Published', f.published]].filter(function (x) { return x[1]; });
    if (steps.length) {
      var tl = el('ol', 'ch-tl');
      steps.forEach(function (x) { var li = el('li'); li.appendChild(el('span', 'ch-tl-l', x[0])); li.appendChild(el('b', null, x[1])); tl.appendChild(li); });
      var r1 = el('div', 'ch-fact ch-fact-dates'); r1.appendChild(icon('fa-clock-o')); r1.appendChild(tl); box.appendChild(r1);
    }
    if (f.editors && f.editors.length) {
      var r2 = el('div', 'ch-fact'); r2.appendChild(icon('fa-user-circle-o'));
      var p2 = el('p'); p2.appendChild(el('span', 'ch-fact-l', 'Editor' + (f.editors.length > 1 ? 's' : '') + ': ')); p2.appendChild(D.createTextNode(f.editors.join(', ')));
      r2.appendChild(p2); box.appendChild(r2);
    }
    if (f.copyrightHolder || f.licenseUrl) {
      var r3 = el('div', 'ch-fact'); r3.appendChild(icon('fa-unlock-alt'));
      var p3 = el('p');
      if (f.copyrightHolder) p3.appendChild(D.createTextNode('Copyright © ' + (f.copyrightYear ? f.copyrightYear + ' ' : '') + f.copyrightHolder));
      var lic = safe(f.licenseUrl);
      if (lic) {
        if (f.copyrightHolder) p3.appendChild(el('br'));
        p3.appendChild(D.createTextNode('This work is licensed under a '));
        var la = el('a', null, licenseName(lic) || lic); la.href = lic; la.target = '_blank'; la.rel = 'license noopener';
        p3.appendChild(la); p3.appendChild(D.createTextNode('.'));
        var cc = String(lic).match(/licenses\/([a-z\-]+)\/(\d\.\d)/i);
        if (cc) { var bi = el('img', 'ch-cc-badge'); bi.src = 'https://i.creativecommons.org/l/' + cc[1].toLowerCase() + '/' + cc[2] + '/88x31.png'; bi.alt = 'CC ' + cc[1].toUpperCase(); bi.loading = 'lazy'; r3.appendChild(bi); }
      }
      r3.insertBefore(p3, r3.querySelector('.ch-cc-badge'));
      box.appendChild(r3);
    }
    if (box.children.length) hero.parentNode.insertBefore(box, hero.nextSibling);
  }
  /* afiliasi lebih dari 3: tampilkan 3, sisanya dibuka lewat tombol */
  function collapseAffs(head) {
    var list = head.querySelector('.ch-affs');
    if (!list || list.children.length <= 3 || head.querySelector('.ch-affs-toggle')) return;
    list.classList.add('ch-affs-collapsed');
    var b = el('button', 'ch-affs-toggle'); b.type = 'button'; b.setAttribute('aria-expanded', 'false');
    function label() { var open = !list.classList.contains('ch-affs-collapsed'); b.textContent = open ? 'View less affiliations ▴' : 'View all affiliations (' + list.children.length + ') ▾'; b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    b.addEventListener('click', function () { list.classList.toggle('ch-affs-collapsed'); label(); });
    label(); list.parentNode.insertBefore(b, list.nextSibling);
  }

  function articleHero() {
    var art = D.querySelector('article.obj_article_details');
    var h1 = art && art.querySelector('.page_title');
    var head = art && art.querySelector('.ch-art-head');
    if (!h1 || !head || art.querySelector('.ch-art-hero')) return;
    var hero = el('header', 'ch-art-hero');
    var kick = el('div', 'ch-hero-kick');
    var sec = metaVal('section'), iss = metaVal('issue');
    if (sec) kick.appendChild(el('span', 'ch-hero-sec', sec));
    if (iss) kick.appendChild(el('span', 'ch-hero-iss', iss));
    hero.appendChild(kick);
    var cmk = art.querySelector('.cmk-wrap');
    if (cmk && cmk.getAttribute('data-position') !== 'details') { cmk.classList.add('ch-cmk'); kick.appendChild(cmk); }
    h1.parentNode.insertBefore(hero, h1);
    hero.appendChild(h1);
    hero.appendChild(head);
    articleAuthors(head);
    var chips = el('div', 'ch-hero-chips');
    var pub = metaVal('published'), doi = articleDoi();
    /* chip Published di bawah afiliasi dihapus: tanggal terbit tampil di kotak riwayat naskah */
    if (chips.children.length) hero.appendChild(chips);
    collapseAffs(head);
    articleFacts(hero);
  }

  /* ---------- 15. Halaman statis: kepala halaman + daftar isi ---------- */
  function staticPage() {
    if (isHome() || isArticle() || D.querySelector('.ch-page-hero')) return;
    var main = D.querySelector('.pkp_structure_main');
    var page = main && main.querySelector('.page');
    if (!page || page.matches('.page_issue, .page_issue_archive, .page_search, .page_announcements, .page_submissions_list') ||
        page.querySelector('.obj_issue_toc, .issues_archive, .cmp_article_list')) return;
    var h1 = page.querySelector(':scope > h1, :scope > .page_title, :scope > .pkp_page_title');
    if (!h1) return;
    page.classList.add('ch-static');
    var hero = el('div', 'ch-page-hero');
    var bc = page.querySelector(':scope > .cmp_breadcrumbs');
    if (bc) hero.appendChild(bc);
    page.insertBefore(hero, page.firstChild);
    hero.appendChild(h1);
    // judul bagian: h2/h3, atau paragraf yang isinya hanya teks tebal (mis. "1. General Author Guidelines")
    var heads = [].filter.call(page.querySelectorAll('h2, h3, p'), function (n) {
      if (hero.contains(n)) return false;
      if (/^H[23]$/.test(n.tagName)) return true;
      var s = n.querySelector(':scope > strong:only-child, :scope > b:only-child');
      return !!s && s.textContent.trim() === n.textContent.trim() && n.textContent.trim().length < 90;
    });
    heads.forEach(function (n, i) { n.classList.add('ch-sec-h'); if (!n.id) n.id = 'ch-sec-' + (i + 1); });
    if (heads.length >= 3) {
      var toc = el('nav', 'ch-page-toc'); toc.setAttribute('aria-label', 'On this page');
      toc.appendChild(el('span', 'ch-toc-label', 'On this page'));
      heads.forEach(function (n) {
        var a = el('a', null, n.textContent.replace(/\s+/g, ' ').trim().replace(/^\d+[\.\)]\s*/, ''));
        a.href = '#' + n.id; toc.appendChild(a);
      });
      hero.parentNode.insertBefore(toc, hero.nextSibling);
    }
  }


  /* ---------- 16. Ikon pada submenu dropdown ---------- */
  var SUB_ICONS = [
    [/about the journal|tentang jurnal/i, 'fa-info-circle'], [/submission/i, 'fa-paper-plane'], [/privacy/i, 'fa-shield'],
    [/contact|kontak/i, 'fa-envelope'], [/editorial|editor/i, 'fa-users'], [/reviewer/i, 'fa-user-circle'],
    [/current|terkini/i, 'fa-book'], [/archive|arsip/i, 'fa-archive'], [/guideline|pedoman/i, 'fa-file-text-o'],
    [/fee|biaya/i, 'fa-money'], [/copyright|license/i, 'fa-copyright'], [/regist|author$|penulis/i, 'fa-user-plus'],
    [/credit/i, 'fa-id-badge'], [/graphical|abstract/i, 'fa-picture-o'], [/template/i, 'fa-download'],
    [/announce/i, 'fa-bullhorn'], [/statistic/i, 'fa-bar-chart'], [/policy|kebijakan/i, 'fa-gavel']
  ];
  function subIcons() {
    [].forEach.call(D.querySelectorAll('#navigationPrimary ul a'), function (a) {
      if (a.querySelector('.ch-sub-ico')) return;
      var t = a.textContent.trim(), cls = 'fa-angle-right';
      for (var k = 0; k < SUB_ICONS.length; k++) if (SUB_ICONS[k][0].test(t)) { cls = SUB_ICONS[k][1]; break; }
      var w = el('span', 'ch-sub-ico'); w.appendChild(icon(cls));
      a.insertBefore(w, a.firstChild);
      a.classList.add('ch-sub-link');
    });
  }


  /* ---------- 17. Additional Content beranda: bagian selebar halaman di atas footer ---------- */
  function addlFull() {
    if (!isHome() || D.querySelector('.ch-addl-full')) return;
    var addl = D.querySelector('.page_index_journal .additional_content');
    var foot = D.querySelector('.pkp_structure_footer_wrapper');
    if (!addl || !foot || !addl.textContent.replace(/\s+/g, '') && !addl.querySelector('img, canvas, svg, iframe, table')) return;
    var band = el('section', 'ch-addl-full'); band.setAttribute('aria-label', 'Journal highlights');
    var inner = el('div', 'ch-addl-inner');
    inner.appendChild(addl); band.appendChild(inner);
    foot.parentNode.insertBefore(band, foot);
    // grafik yang digambar script perlu menyesuaikan lebar baru
    setTimeout(function () { try { window.dispatchEvent(new Event('resize')); } catch (e) {} }, 50);
  }

  /* ---------- jalankan ---------- */
  function run() {
    userBar();
    fixSearch();
    navIcons();
    subIcons();
    issueTabs();
    indexingStrip();
    thumbs();
    coverCard();
    quickPanel();
    watchDiversity();
    richCards();
    archiveDedupe();
    sidebarRules();
    articleHero();
    staticPage();
    annFallback();
    addlFull();
    D.documentElement.classList.add('ch-nv-ready');
  }
  ready(function () {
    run();
    // kartu artikel & label seksi dibuat bertahap oleh script lain
    setTimeout(thumbs, 1300); setTimeout(thumbs, 3200);
    // panel Article Info & kepala artikel dibuat bertahap oleh script lain
    [600, 1800, 4000].forEach(function (t) { setTimeout(function () { archiveDedupe(); sidebarRules(); articleHero(); placeUserBar(); }, t); });
  });
})();
