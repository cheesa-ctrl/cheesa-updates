/* CHEESA legacy scripts (dibawa dari Header Content & Additional content in footer).
   Bila kode lama masih terpasang di pengaturan OJS, bagian yang sama di sini dilewati otomatis. */
if (!window.chCache) {

window.chInFrame = (window.self !== window.top);   // true bila halaman dimuat di dalam iframe
window.chBase = function () {
  var m = location.pathname.match(/^(.*?\/cheesa)(?:\/|$)/);
  return location.origin + (m ? m[1] : '/index.php/cheesa');
};
window.chCache = (function () {
  var mem = {}, S = null;
  try { var t = "__ch"; localStorage.setItem(t, t); localStorage.removeItem(t); S = localStorage; }
  catch (e) { try { S = sessionStorage; } catch (e2) { S = null; } }
  return {
    get: function (k, ttl) {
      try {
        var raw = S ? S.getItem("ch:" + k) : mem[k], o = raw ? JSON.parse(raw) : null;
        if (o && Date.now() - o.t < ttl) return o.v;
      } catch (e) {}
      return null;
    },
    set: function (k, v) {
      var raw = JSON.stringify({ t: Date.now(), v: v });
      try { if (S) S.setItem("ch:" + k, raw); else mem[k] = raw; } catch (e) { mem[k] = raw; }
    }
  };
})();

;

(function () {
  var MAX = 8;       // jumlah pengumuman terbaru yang ditampilkan di slider
  var DELAY = 12000; // jeda geser otomatis (ms): 12000 = 12 detik, isi 0 untuk mematikan
  var CACHE_MS = 3600000; // daftar pengumuman disimpan 1 jam (ms) sebelum diambil ulang dari server

  // Header/menu yang menempel (fixed/sticky) harus berada di atas slider, bukan tertimpa
  function liftHeader(wrap) {
    var els = document.body.getElementsByTagName("*");
    for (var k = 0; k < els.length; k++) {
      var el = els[k];
      if (wrap.contains(el) || el.contains(wrap)) continue;
      var cs = window.getComputedStyle(el);
      if (cs.position !== "fixed" && cs.position !== "sticky") continue;
      if (!el.offsetHeight) continue;
      var z = parseInt(cs.zIndex, 10);
      if (isNaN(z) || z < 100) el.style.zIndex = "1000";
    }
  }

  function init() {
    if (window.chInFrame) return;
    var sec = document.querySelector(".cmp_announcements");
    if (!sec || sec.getAttribute("data-ch-sl")) return;
    var sel = "article.obj_announcement_summary";
    var first = [].slice.call(sec.querySelectorAll(sel)).slice(0, MAX);
    if (!first.length) return;
    sec.setAttribute("data-ch-sl", "1");
    sec.classList.add("ch-sl-on");
    var isList = sec.tagName === "UL" || sec.tagName === "OL";

    var wrap = document.createElement("div"); wrap.className = "ch-sl";
    wrap.setAttribute("role", "region"); wrap.setAttribute("aria-roledescription", "carousel");
    wrap.setAttribute("aria-label", "Announcements");
    var vp = document.createElement("div"); vp.className = "ch-sl-vp";
    var tr = document.createElement("div"); tr.className = "ch-sl-tr";
    vp.appendChild(tr); wrap.appendChild(vp);

    function btn(cls, txt, label) {
      var b = document.createElement("button");
      b.type = "button"; b.className = "ch-sl-btn " + cls; b.innerHTML = txt; b.setAttribute("aria-label", label);
      return b;
    }
    var prev = btn("ch-sl-prev", "&#8249;", "Previous announcement");
    var next = btn("ch-sl-next", "&#8250;", "Next announcement");
    var dotsBox = document.createElement("div"); dotsBox.className = "ch-sl-dots";
    wrap.appendChild(prev); wrap.appendChild(next); wrap.appendChild(dotsBox);

    if (isList) sec.parentNode.insertBefore(wrap, sec);
    else {
      var h2 = sec.querySelector(":scope > h2");
      if (h2 && h2.nextSibling) sec.insertBefore(wrap, h2.nextSibling); else sec.appendChild(wrap);
    }

    var i = 0, n = 0, dots = [], timer = null;
    function go(k) {
      if (!n) return;
      i = (k + n) % n;
      tr.style.transform = "translateX(" + (-100 * i) + "%)";
      dots.forEach(function (d, j) { d.classList.toggle("on", j === i); });
    }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function start() {
      if (!DELAY || n < 2 || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
      stop(); timer = setInterval(function () { go(i + 1); }, DELAY);
    }
    function restart() { stop(); start(); }

    // bangun ulang semua slide dari daftar artikel
    function render(list) {
      tr.innerHTML = ""; dotsBox.innerHTML = ""; dots = []; n = list.length;
      list.forEach(function (a, k) {
        var s = document.createElement("div"); s.className = "ch-sl-slide";
        var h = a.querySelector("h2 a, h3 a, h4 a");
        if (h) {
          var t = document.createElement("div"); t.className = "ch-sl-title";
          var l = document.createElement("a");
          l.href = h.getAttribute("href"); l.textContent = h.textContent.replace(/\s+/g, " ").trim();
          t.appendChild(l); s.appendChild(t);
        }
        s.appendChild(a); tr.appendChild(s);
        var d = document.createElement("button");
        d.type = "button"; d.className = "ch-sl-dot"; d.setAttribute("aria-label", "Go to announcement " + (k + 1));
        d.addEventListener("click", function () { go(k); restart(); });
        dotsBox.appendChild(d); dots.push(d);
      });
      var multi = n > 1;
      prev.style.display = next.style.display = dotsBox.style.display = multi ? "" : "none";
      go(Math.min(i, n - 1)); restart();
    }

    prev.addEventListener("click", function () { go(i - 1); restart(); });
    next.addEventListener("click", function () { go(i + 1); restart(); });
    wrap.addEventListener("mouseenter", stop); wrap.addEventListener("mouseleave", start);
    wrap.addEventListener("focusin", stop); wrap.addEventListener("focusout", start);
    wrap.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") { go(i - 1); restart(); }
      if (e.key === "ArrowRight") { go(i + 1); restart(); }
    });
    var x0 = null;
    vp.addEventListener("touchstart", function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    vp.addEventListener("touchend", function (e) {
      if (x0 !== null) {
        var dx = e.changedTouches[0].clientX - x0;
        if (Math.abs(dx) > 40) go(dx < 0 ? i + 1 : i - 1);
      }
      x0 = null; start();
    });

    liftHeader(wrap);
    window.addEventListener("load", function () { liftHeader(wrap); });
    var rt = null;
    window.addEventListener("resize", function () { clearTimeout(rt); rt = setTimeout(function () { liftHeader(wrap); }, 250); });

    render(first); // tampil dulu dari isi halaman ini

    // Di beranda: ambil daftar lengkap dari halaman /announcement agar pengumuman baru ikut masuk
    if (!isList) {
      var a0 = first[0].querySelector("h2 a, h3 a, h4 a");
      if (!a0) return;
      var url = a0.href.replace(/\/announcement\/view\/\d+.*$/, "/announcement");
      var all = document.createElement("div"); all.className = "ch-sl-all";
      all.innerHTML = '<a href="' + url + '">All announcements &rarr;</a>';
      wrap.parentNode.insertBefore(all, wrap.nextSibling);

      // 1) pakai daftar tersimpan bila masih baru
      var cachedAnn = chCache.get("ann", CACHE_MS);
      if (cachedAnn) {
        var tmp = document.createElement("div");
        tmp.innerHTML = cachedAnn.join("");
        var cl = [].slice.call(tmp.querySelectorAll(sel));
        if (cl.length) render(cl);
        return;
      }
      // 2) bila baru saja dicoba (dan gagal), jangan minta lagi
      if (chCache.get("ann_try", CACHE_MS)) return;
      chCache.set("ann_try", 1);

      fetch(url, { credentials: "same-origin" }).then(function (r) { return r.text(); }).then(function (t) {
        var doc = new DOMParser().parseFromString(t, "text/html");
        var list = [].slice.call(doc.querySelectorAll(sel)).slice(0, MAX)
          .map(function (x) { return document.importNode(x, true); });
        if (list.length) {
          chCache.set("ann", list.map(function (x) { return x.outerHTML; }));
          render(list);
        }
      }).catch(function () {});
    }
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

;

(function () {
  var ARCHIVE_URL = chBase() + "/issue/archive";
  var ISSUE_BASE  = chBase() + "/issue/view/";
  var TITLE = "Journal Archive";
  var MAX_YEARS = 3;    // jumlah tahun terakhir yang ditampilkan
  var OPEN_ALL = false; // false = hanya tahun terbaru yang terbuka
  var CACHE_MS = 86400000; // data disimpan 24 jam (ms) sebelum diambil ulang dari server
  var RETRY_MS = 21600000; // bila pengambilan gagal, coba lagi paling cepat 6 jam kemudian
  // true = daftar terbitan diambil dari halaman /issue/archive (halaman ini dibuat server, jadi terbaca) paling cepat
  //        24 jam sekali per pengunjung, sehingga terbitan baru otomatis muncul tanpa mengubah FALLBACK.
  // false = tidak pernah ke server; data dari FALLBACK saja.
  var AUTO_FETCH = true;

  // Data cadangan [volume, nomor, tahun, id terbitan, sedang berjalan]
  // Sesuai situs sekarang. Tambahkan baris baru di depan saat ada terbitan baru bila AUTO_FETCH = false.
  var FALLBACK = [
    [1,1,2018,1,0]
  ];

  var SVG_ARCHIVE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm1 6h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9zm5 3a1 1 0 0 0 0 2h6a1 1 0 0 0 0-2H9z"/></svg>';
  var SVG_BOOK = '<svg class="ch-ar-book" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h13a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H6a1 1 0 0 0 0 2h12a1 1 0 0 1 0 2H6a3 3 0 0 1-3-3V5a2 2 0 0 1 2-2zm3 4v2h8V7H8zm0 4v2h6v-2H8z"/></svg>';
  var SVG_CHEV = '<svg class="ch-ar-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>';

  function fromFallback() {
    return FALLBACK.map(function (r) {
      return { vol: r[0], no: r[1], year: r[2], href: ISSUE_BASE + r[3], progress: !!r[4] };
    });
  }

  function parseIssues(doc) {
    var out = [];
    [].forEach.call(doc.querySelectorAll("ul.issues_archive li .obj_issue_summary"), function (b) {
      var a = b.querySelector("a.title"); if (!a) return;
      var series = b.querySelector(".series");
      var title = a.textContent.replace(/\s+/g, " ").trim();
      var txt = ((series ? series.textContent : "") + " " + title).replace(/\s+/g, " ");
      var m = txt.match(/Vol\.?\s*(\d+)\s*No\.?\s*(\d+)\s*\((\d{4})\)/i);
      var y = m ? +m[3] : ((txt.match(/(\d{4})/) || [])[1] || 0);
      out.push({
        vol: m ? +m[1] : 0, no: m ? +m[2] : 0, year: +y, href: a.href, label: m ? null : title,
        progress: /in progress/i.test(title)
      });
    });
    return out;
  }

  function loadAll(url, acc, depth) {
    return fetch(url, { credentials: "same-origin" }).then(function (r) { return r.text(); }).then(function (t) {
      var doc = new DOMParser().parseFromString(t, "text/html");
      acc = acc.concat(parseIssues(doc));
      var nx = doc.querySelector(".cmp_pagination a.next");
      if (nx && depth < 2) return loadAll(nx.href, acc, depth + 1);   // maksimal 3 halaman arsip
      return acc;
    });
  }

  function host() {
    var el = document.getElementById("ch-archive");
    if (el) return el;
    var sb = document.querySelector(".pkp_structure_sidebar") ||
             document.querySelector(".pkp_sidebar") ||
             document.querySelector("#sidebar");
    if (!sb) return null;
    el = document.createElement("div"); el.id = "ch-archive";
    sb.insertBefore(el, sb.firstChild);
    return el;
  }

  function render(el, items, url) {
    var years = {};
    items.forEach(function (it) { (years[it.year] = years[it.year] || []).push(it); });
    var keys = Object.keys(years).sort(function (a, b) { return b - a; }).slice(0, MAX_YEARS);

    var box = document.createElement("div"); box.className = "ch-ar";
    var head = document.createElement("div"); head.className = "ch-ar-head";
    head.innerHTML = SVG_ARCHIVE; head.appendChild(document.createTextNode(TITLE));
    var body = document.createElement("div"); body.className = "ch-ar-body";

    keys.forEach(function (y, idx) {
      var list = years[y].sort(function (a, b) { return a.vol - b.vol || a.no - b.no; });
      var sec = document.createElement("div"); sec.className = "ch-ar-year" + (OPEN_ALL || idx === 0 ? "" : " closed");
      var btn = document.createElement("button"); btn.type = "button"; btn.className = "ch-ar-btn";
      btn.setAttribute("aria-expanded", sec.classList.contains("closed") ? "false" : "true");
      var left = document.createElement("span");
      left.appendChild(document.createTextNode(+y ? y : "Other"));
      var c = document.createElement("span"); c.className = "ch-ar-count"; c.textContent = "(" + list.length + ")";
      left.appendChild(c); btn.appendChild(left);
      btn.insertAdjacentHTML("beforeend", SVG_CHEV);
      btn.addEventListener("click", function () {
        var closed = sec.classList.toggle("closed");
        btn.setAttribute("aria-expanded", closed ? "false" : "true");
      });
      var ul = document.createElement("ul"); ul.className = "ch-ar-list";
      list.forEach(function (it) {
        var li = document.createElement("li"), a = document.createElement("a");
        a.href = it.href;
        if (location.href.split("#")[0] === it.href) a.className = "on";
        a.insertAdjacentHTML("beforeend", SVG_BOOK);
        a.appendChild(document.createTextNode(it.label || ("Vol. " + it.vol + " No. " + it.no)));
        if (it.progress) { var t = document.createElement("span"); t.className = "ch-ar-tag"; t.textContent = "In Progress"; a.appendChild(t); }
        li.appendChild(a); ul.appendChild(li);
      });
      sec.appendChild(btn); sec.appendChild(ul); body.appendChild(sec);
    });

    var all = document.createElement("a"); all.className = "ch-ar-all"; all.href = url;
    all.innerHTML = "All Archive &rarr;";
    body.appendChild(all);

    box.appendChild(head); box.appendChild(body);
    el.innerHTML = ""; el.appendChild(box);
  }

  function init() {
    if (window.chInFrame) return;
    if (document.querySelector(".jw-volumes")) return;   // widget Published Volumes dipakai
    var el = host(); if (!el) return;
    var link = document.querySelector('a[href*="/issue/archive"]');
    var url = link ? link.href : ARCHIVE_URL;

    // Di halaman Archive sendiri: baca langsung dari halaman ini, tanpa meminta lagi ke server.
    // (Hanya halaman arsip pertama yang berisi terbitan terbaru, jadi cache ikut diperbarui dari sini.)
    if (/\/issue\/archive\/?$/.test(location.pathname)) {
      var own = parseIssues(document);
      if (own.length) { chCache.set("archive", own); render(el, own, url); return; }
    }

    var cached = chCache.get("archive", CACHE_MS);
    render(el, cached || fromFallback(), url);
    if (!AUTO_FETCH || cached) return;                                   // data tersimpan masih baru: tidak perlu ke server
    if (chCache.get("archive_try", RETRY_MS)) return;     // baru saja dicoba: jangan ulang
    chCache.set("archive_try", 1);

    loadAll(url, [], 0).then(function (items) {
      if (items.length) { chCache.set("archive", items); render(el, items, url); }
    }).catch(function () {});
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

;

(function () {
  var PAGE_URL = chBase() + "/countrystats/publicpage";
  var START_YEAR = 2018;     // tahun terbitan pertama; tahun akhir otomatis = tahun berjalan
  var MAX_ROWS = 12;         // jumlah negara yang ditampilkan
  var CACHE_MS = 86400000;   // data disimpan 24 jam (ms)
  var RETRY_MS = 21600000;   // bila pengambilan gagal, coba lagi paling cepat 6 jam kemudian
  var MAX_TRIES = 30;        // batas tunggu iframe: 30 x 0,5 detik = 15 detik

  var AUTO_FETCH = true;     // true = ambil data terbaru dari halaman countrystats
  var USE_IFRAME = true;     // true = baca tabel yang dibuat JavaScript lewat iframe tersembunyi

  // Data cadangan [negara, jumlah artikel] - tampil seketika, sebelum data terbaru terbaca.
  // Sesuai halaman countrystats saat ini: 6 artikel terbit, 1 negara.
  var FALLBACK = [
    ["Indonesia", 6]
  ];

  // Membaca tabel "Articles by Country" dari dokumen halaman sumber
  function parseRows(doc) {
    var out = [], rows = [], body = doc.getElementById("csArticlesBody");
    if (body) rows = body.querySelectorAll("tr");
    else {
      var tables = doc.querySelectorAll("table"), i;
      for (i = 0; i < tables.length; i++) {
        var head = (tables[i].querySelector("thead") || tables[i]).textContent;
        if (/Articles/i.test(head) && !/Authors/i.test(head)) { rows = tables[i].querySelectorAll("tbody tr"); break; }
      }
    }
    [].forEach.call(rows, function (tr) {
      var tds = tr.querySelectorAll("td");
      if (tds.length < 3) return;
      var cell = tr.querySelector(".cs-country-cell") || tds[1];
      var sp = cell.querySelectorAll("span");
      var name = (sp.length ? sp[sp.length - 1] : cell).textContent
        .replace(/[\u{1F1E6}-\u{1F1FF}]/gu, "").replace(/\s+/g, " ").trim();
      var cnt = tr.querySelector(".cs-count-bold") || tds[2];
      var n = parseInt(cnt.textContent.replace(/\D/g, ""), 10);
      if (name && n) out.push([name, n]);
    });
    return out;
  }

  // Memuat halaman sumber di iframe tersembunyi sampai tabelnya terisi
  function viaIframe(url) {
    return new Promise(function (resolve) {
      var f = document.createElement("iframe"), tries = 0, done = false, t;
      f.style.cssText = "position:absolute;width:0;height:0;border:0;visibility:hidden";
      f.setAttribute("aria-hidden", "true");
      f.setAttribute("tabindex", "-1");
      function finish(r) {
        if (done) return; done = true; clearInterval(t);
        if (f.parentNode) f.parentNode.removeChild(f);
        resolve(r);
      }
      t = setInterval(function () {
        tries++;
        try {
          var rows = parseRows(f.contentDocument);
          if (rows.length) return finish(rows);
        } catch (e) { return finish([]); }
        if (tries > MAX_TRIES) finish([]);
      }, 500);
      f.src = url;
      document.body.appendChild(f);
    });
  }

  function viaFetch(url) {
    return fetch(url, { credentials: "same-origin" })
      .then(function (r) { return r.text(); })
      .then(function (t) { return parseRows(new DOMParser().parseFromString(t, "text/html")); })
      .catch(function () { return []; });
  }

  function load() { return USE_IFRAME ? viaIframe(PAGE_URL) : viaFetch(PAGE_URL); }

  function cell(tag, cls, text) {
    var c = document.createElement(tag); if (cls) c.className = cls;
    if (text != null) c.textContent = text; return c;
  }

  function render(el, rows) {
    rows = rows.slice().sort(function (a, b) { return b[1] - a[1]; }).slice(0, MAX_ROWS);
    if (!rows.length) return;
    var max = rows[0][1];
    var wrap = cell("div", "ch-cs");
    wrap.appendChild(cell("p", "ch-cs-note", "Publications in " + START_YEAR + "-" + new Date().getFullYear() + "."));

    var table = document.createElement("table");
    var hr = document.createElement("tr");
    hr.appendChild(cell("th", "", "#")); hr.appendChild(cell("th", "", "Country"));
    hr.appendChild(cell("th", "ch-cs-v", "Articles")); hr.appendChild(cell("th", "", ""));
    var th = document.createElement("thead"); th.appendChild(hr); table.appendChild(th);

    var tb = document.createElement("tbody");
    rows.forEach(function (r, i) {
      var tr = document.createElement("tr");
      tr.appendChild(cell("td", "ch-cs-n", i + 1));
      tr.appendChild(cell("td", "", r[0]));
      tr.appendChild(cell("td", "ch-cs-v", r[1]));
      var bt = cell("td", "ch-cs-b"), track = cell("div", "ch-cs-track"), fill = cell("div", "ch-cs-fill");
      fill.style.width = Math.max(4, r[1] / max * 100) + "%";
      track.appendChild(fill); bt.appendChild(track); tr.appendChild(bt);
      tb.appendChild(tr);
    });
    table.appendChild(tb); wrap.appendChild(table);

    var all = cell("a", "ch-cs-all"); all.href = PAGE_URL; all.innerHTML = "Full Statistics &rarr;";
    wrap.appendChild(all);
    el.innerHTML = ""; el.appendChild(wrap);
  }

  // jalankan pengambilan data setelah halaman selesai dimuat, agar tidak menghambat tampilan awal
  function later(fn) {
    if (document.readyState === "complete") setTimeout(fn, 300);
    else window.addEventListener("load", function () { setTimeout(fn, 300); });
  }

  function init() {
    if (window.chInFrame) return;                      // jangan jalan di dalam iframe sendiri
    var el = document.getElementById("ch-country"); if (!el) return;

    var cached = chCache.get("country", CACHE_MS);
    render(el, cached || FALLBACK);                    // tampil seketika
    if (!AUTO_FETCH || cached) return;                 // data tersimpan masih baru: tidak perlu ke server
    if (chCache.get("country_try", RETRY_MS)) return;  // baru saja dicoba: jangan ulang
    chCache.set("country_try", 1);

    later(function () {
      load().then(function (rows) {
        if (rows.length) { chCache.set("country", rows); render(el, rows); }
      });
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

;

(function () {
  function searchAction() {
    if (typeof chBase === "function") return chBase() + "/search/search";
    var link = document.querySelector(".pkp_navigation_search_wrapper a, a.pkp_search_desktop");
    var m = (link ? link.href : location.href).match(/^(.*?\/search)(?:[\/?#].*)?$/);
    return m ? m[1] + "/search" : "";
  }
  // Koreksi otomatis: menu utama diukur lalu digeser sampai tepat di tengah bar,
  // apa pun lebar menu user (kiri) dan kotak search (kanan) atau sisa ruang bawaan tema.
  function centerMenu(row) {
    var ul = row.querySelector(".pkp_navigation_primary_wrapper > ul");
    if (!ul) return;
    ul.style.position = "relative";
    ul.style.left = "0px";
    if (!window.matchMedia("(min-width: 992px)").matches) { ul.style.left = ""; ul.style.position = ""; return; }
    var vis = [].filter.call(ul.children, function (li) { return li.getBoundingClientRect().width > 0; });
    if (!vis.length) return;
    var first = vis[0].getBoundingClientRect(), last = vis[vis.length - 1].getBoundingClientRect();
    var rr = row.getBoundingClientRect();
    var dx = (rr.left + rr.right) / 2 - (first.left + last.right) / 2;
    var l = row.querySelector(".ch-nav-left"), r = row.querySelector(".ch-nav-right");
    var lEl = l && (l.lastElementChild || l), rEl = r && (r.querySelector(".ch-search") || r.firstElementChild || r);
    var minLeft = lEl ? lEl.getBoundingClientRect().right + 8 : -1e9;
    var maxRight = rEl ? rEl.getBoundingClientRect().left - 8 : 1e9;
    if (first.left + dx < minLeft) dx = minLeft - first.left;
    if (last.right + dx > maxRight) dx = maxRight - last.right;
    ul.style.left = Math.round(dx) + "px";
  }
  function init() {
    var row = document.querySelector(".pkp_navigation_primary_row");
    if (!row) return;
    var primary = row.querySelector(".pkp_navigation_primary_wrapper");
    var right = row.querySelector(".ch-nav-right");

    // 1) susun ulang: kiri = user/login, tengah = menu utama, kanan = search
    if (!row.classList.contains("ch-nav-row")) {
      var user = document.getElementById("navigationUserWrapper") || document.querySelector(".pkp_navigation_user_wrapper");
      var search = row.querySelector(".pkp_navigation_search_wrapper") || document.querySelector(".pkp_navigation_search_wrapper");
      if (!user && !search) return;
      var left = document.createElement("div"); left.className = "ch-nav-left";
      right = document.createElement("div"); right.className = "ch-nav-right";
      if (user) left.appendChild(user);
      if (search) right.appendChild(search);
      row.insertBefore(left, primary || row.firstChild);   // kolom kiri & kanan selalu ada agar menu tepat di tengah
      row.appendChild(right);
      row.classList.add("ch-nav-row");
    }

    // 2) kotak Search gaya pill + tombol kuning (menggantikan link "Search")
    if (!right || right.querySelector(".ch-search")) return;
    var action = searchAction();
    if (!action) return;                                   // alamat tidak diketahui: biarkan link Search bawaan
    var form = document.createElement("form");
    form.className = "ch-search"; form.method = "get"; form.action = action;
    form.setAttribute("role", "search");
    var input = document.createElement("input");
    input.type = "search"; input.name = "query"; input.autocomplete = "off";
    input.placeholder = "Search articles\u2026";
    input.setAttribute("aria-label", "Search articles, authors or keywords");
    try { var q = new URLSearchParams(location.search).get("query"); if (q) input.value = q; } catch (e) {}
    var btn = document.createElement("button"); btn.type = "submit";
    var ic = document.createElement("i"); ic.className = "fa fa-search"; ic.setAttribute("aria-hidden", "true");
    var lb = document.createElement("span"); lb.textContent = "Search";
    btn.appendChild(ic); btn.appendChild(lb);
    form.appendChild(input); form.appendChild(btn);
    right.appendChild(form);
    right.classList.add("ch-has-form");
  }
  function boot() {
    init();
    var row = document.querySelector(".pkp_navigation_primary_row");
    if (!row) return;
    var t = null;
    function fix() { clearTimeout(t); t = setTimeout(function () { centerMenu(row); }, 60); }
    centerMenu(row);
    window.addEventListener("resize", fix);
    window.addEventListener("load", fix);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fix);
    setTimeout(fix, 500); setTimeout(fix, 1500);
    if (window.ResizeObserver) {
      var ro = new ResizeObserver(fix); ro.observe(row);
      var u = row.querySelector(".pkp_navigation_primary_wrapper > ul"); if (u) ro.observe(u);
    }
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();

;

(function () {
  'use strict';
  var d = document.documentElement, closed = false;
  function done() {
    if (closed) return;
    closed = true;
    var wait = Math.max(0, 600 - (window.performance ? performance.now() : 600));
    setTimeout(function () { d.classList.add('chl-done'); }, wait);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', done);
  else done();
  window.addEventListener('load', done);
})();

;

document.addEventListener('click', function (e) {
  var a = e.target.closest ? e.target.closest('.menu-info li.has-child > a') : null;
  var li = a ? a.parentElement : null;

  document.querySelectorAll('.menu-info li.has-child.open').forEach(function (x) {
    if (x !== li) x.classList.remove('open');
  });
  if (!a) return;

  e.preventDefault();                 // "Journal Policy" hanya membuka anak menu
  if (window.matchMedia('(max-width: 1099px), (hover: none)').matches) {
    li.classList.toggle('open');
  }
});

} else { window.chLegacyHeadSkipped = true; }

if (!window.chFlagFor) {

window.chBase = function () {
  var m = location.pathname.match(/^(.*?\/cheesa)(?:\/|$)/);
  return location.origin + (m ? m[1] : '/index.php/cheesa');
};
window.addEventListener('DOMContentLoaded', function () {
  // judul tab hanya diganti di beranda; halaman lain (artikel, dll) memakai judul bawaan OJS
  if (document.body.classList.contains('pkp_page_index'))
    document.title = 'CHEESA: Chemical Engineering Research Articles';
});

;

(function () {
  var C = {
    "indonesia":"id","malaysia":"my","singapore":"sg","singapura":"sg","thailand":"th","vietnam":"vn","viet nam":"vn",
    "philippines":"ph","filipina":"ph","brunei":"bn","brunei darussalam":"bn","cambodia":"kh","kamboja":"kh","laos":"la",
    "myanmar":"mm","timor-leste":"tl","timor leste":"tl","india":"in","pakistan":"pk","bangladesh":"bd","sri lanka":"lk",
    "nepal":"np","china":"cn","taiwan, province of china":"tw","taiwan province of china":"tw","republic of china":"tw",
    "korea, republic of":"kr","republic of korea":"kr","iran, islamic republic of":"ir","russian federation":"ru",
    "palestine":"ps","united kingdom of great britain":"gb","england":"gb","scotland":"gb","wales":"gb","hong kong":"hk",
    "taiwan":"tw","japan":"jp","jepang":"jp","korea":"kr","south korea":"kr",
    "korea selatan":"kr","saudi arabia":"sa","arab saudi":"sa","united arab emirates":"ae","uni emirat arab":"ae",
    "qatar":"qa","kuwait":"kw","oman":"om","bahrain":"bh","jordan":"jo","yordania":"jo","iraq":"iq","irak":"iq","iran":"ir",
    "turkey":"tr","turkiye":"tr","türkiye":"tr","turki":"tr","israel":"il","lebanon":"lb","libanon":"lb","egypt":"eg",
    "mesir":"eg","morocco":"ma","maroko":"ma","algeria":"dz","aljazair":"dz","tunisia":"tn","nigeria":"ng","ghana":"gh",
    "kenya":"ke","south africa":"za","afrika selatan":"za","united states":"us","usa":"us","amerika serikat":"us",
    "canada":"ca","kanada":"ca","mexico":"mx","meksiko":"mx","brazil":"br","brasil":"br","argentina":"ar","chile":"cl",
    "colombia":"co","kolombia":"co","peru":"pe","united kingdom":"gb","uk":"gb","inggris":"gb","ireland":"ie","irlandia":"ie",
    "france":"fr","prancis":"fr","germany":"de","jerman":"de","netherlands":"nl","belanda":"nl","belgium":"be","belgia":"be",
    "switzerland":"ch","swiss":"ch","austria":"at","italy":"it","italia":"it","spain":"es","spanyol":"es","portugal":"pt",
    "sweden":"se","swedia":"se","norway":"no","norwegia":"no","denmark":"dk","finland":"fi","finlandia":"fi","poland":"pl",
    "polandia":"pl","czech republic":"cz","czechia":"cz","hungary":"hu","hongaria":"hu","romania":"ro","rumania":"ro",
    "bulgaria":"bg","greece":"gr","yunani":"gr","serbia":"rs","croatia":"hr","kroasia":"hr","ukraine":"ua","ukraina":"ua",
    "russia":"ru","rusia":"ru","kazakhstan":"kz","uzbekistan":"uz","azerbaijan":"az","australia":"au","new zealand":"nz",
    "selandia baru":"nz","papua new guinea":"pg"
  };
  var names = Object.keys(C).sort(function (a, b) { return b.length - a.length; })
    .map(function (n) { return n.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); });
  var RE = new RegExp("(?:^|,\\s*)(" + names.join("|") + ")[\\s.;]*$", "i");
  var SKIP = /^(SCRIPT|STYLE|TEXTAREA|TABLE|H1|H2|H3|H4|H5|H6|TITLE|NOSCRIPT)$/;
  // teks bebas (abstrak, referensi, dst.) tidak diberi bendera walau kebetulan berakhir dengan nama negara
  var SKIP_SEL = ".item.abstract,.item.references,.item.keywords,.item.copyright,.cmp_announcements,.ch-sl,.ch-sl-all,.ch-au-list,.ch-cstats,.ch-userbar";
  var FLAG_SEL = 'img[src*="flagcdn.com"]:not(.ch-flag-one), img.jw-flag, img.ch-cflag, img.ch-dv-flag, img.ch-au-flag';
  function hasFlag(n) {
    var p = n.parentNode;
    if (!p || p.nodeType !== 1) return false;
    if (p.querySelector(FLAG_SEL)) return true;
    // teks di dalam elemen sebaris (b, span, a): periksa juga baris induknya
    if (/^(B|STRONG|SPAN|A|EM|I|SMALL)$/.test(p.tagName) && p.parentNode && p.parentNode.nodeType === 1 && p.parentNode.querySelector(FLAG_SEL)) return true;
    return false;
  }

  // dipakai juga oleh script kartu artikel: kembalikan {name, src} bila teks berakhir dengan nama negara
  window.chFlagFor = function (text) {
    var m = String(text || "").replace(/\s+/g, " ").trim().match(RE);
    return m ? { name: m[1], src: "https://flagcdn.com/w40/" + C[m[1].toLowerCase()] + ".png" } : null;
  };

  function skipped(node) {
    for (var p = node.parentNode; p && p !== document.body; p = p.parentNode)
      if (p.nodeType === 1 && (SKIP.test(p.tagName) || p.classList.contains("ch-flag-one") ||
          (p.matches && p.matches(SKIP_SEL)))) return true;
    return false;
  }

  function run() {
    document.querySelectorAll(".ch-flag, .ch-flag-one").forEach(function (el) { el.remove(); });
    var w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null), hits = [], n;
    while ((n = w.nextNode())) {
      var s = (n.nodeValue || "").replace(/\s+/g, " ").trim();
      if (!s || s.length > 250 || skipped(n) || hasFlag(n)) continue;
      var m = s.match(RE);
      if (!m) continue;
      hits.push([n, C[m[1].toLowerCase()], m[1]]);
    }
    hits.forEach(function (h) {
      var img = document.createElement("img");
      img.className = "ch-flag-one";
      img.src = "https://flagcdn.com/w40/" + h[1] + ".png";
      img.alt = h[2]; img.title = h[2]; img.loading = "lazy";
      h[0].parentNode.insertBefore(img, h[0].nextSibling);
      var nx = img.nextSibling;
      while (nx && nx.nodeType === 3 && !nx.nodeValue.trim()) { nx.nodeValue = ""; nx = nx.nextSibling; }
      if (nx && nx.nodeType === 1) nx.style.textDecoration = "none";
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run);
  else run();
})();

;

(function () {
  /* Pindahkan judul seksi (h2) menjadi label di pojok tiap kartu artikel */
  function run() {
    Array.prototype.forEach.call(document.querySelectorAll('.section'), function (sec) {
      var h2 = sec.querySelector(':scope > h2');
      if (!h2) return;
      var label = h2.textContent.replace(/\s+/g, ' ').trim();
      var cards = sec.querySelectorAll('.obj_article_summary');
      if (!label || !cards.length) return;
      Array.prototype.forEach.call(cards, function (c) {
        if (c.querySelector(':scope > .ch-pill')) return;
        var s = document.createElement('span');
        s.className = 'ch-pill';
        s.textContent = label;
        c.insertBefore(s, c.firstChild);
        c.classList.add('ch-has-pill');
      });
      sec.classList.add('ch-pilled');
    });
  }
  function start() { run(); setTimeout(run, 1200); setTimeout(run, 3000); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
  window.addEventListener('load', run);
})();

;

(function () {
  'use strict';

  function init() {
    var form = document.querySelector('.ch-form form');
    if (!form || form.getAttribute('data-ch-bound') === '1') return;
    form.setAttribute('data-ch-bound', '1');

    form.addEventListener('submit', function (e) {
      // Event ini hanya muncul bila validasi bawaan browser (kolom wajib, format) sudah lolos
      e.preventDefault();

      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; btn.textContent = 'Sending\u2026'; }

      function go() { window.location.href = window.chBase() + '/rolereg-thank'; }

      // Perangkap spam: bila terisi, anggap bot, jangan kirim apa pun
      var trap = form.elements['website'];
      if (trap && trap.value) { go(); return; }

      // Cadangan: bila server lambat, tetap lanjut ke halaman terima kasih
      var timer = setTimeout(go, 15000);

      fetch(form.action, {
        method: 'POST',
        mode: 'no-cors',
        body: new URLSearchParams(new FormData(form))
      }).then(function () {
        clearTimeout(timer);
        go();
      }).catch(function () {
        // Jaringan gagal: pakai cara kirim biasa
        clearTimeout(timer);
        form.submit();
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

;

(function () {
  // Isi tiap kolom menurut JUDUL blok (huruf besar/kecil tidak berpengaruh), urutan = urutan dari atas.
  // Beranda     : kiri = Additional Information, Journal Archive, ... | kanan = Submission Menu, ...
  // Halaman lain: Submission Menu PINDAH ke kiri, tepat di bawah Additional Information
  //               (sidebar kanan disembunyikan oleh CSS di halaman selain beranda).
  // Halaman artikel: tidak berubah (Article Info + Journal Archive), Submission Menu tetap di kanan (tersembunyi).
  var home    = document.body.classList.contains("pkp_page_index") || !!document.querySelector(".page_index_journal");
  var article = /\/article\/view\//.test(location.pathname);
  var subLeft = !home && !article;
  var CFG = window.CHEESA_CFG || {};
  var baseL = (CFG.left && CFG.left.length) ? CFG.left : ["additional information","journal archive","diversity of authors"];
  var baseR = (CFG.right && CFG.right.length) ? CFG.right : ["submission menu","cheesa profiles","additional menu","visitors"];
  var LEFT  = subLeft ? [baseL[0], "submission menu"].concat(baseL.slice(1)).filter(function (x, i, a) { return x && a.indexOf(x) === i; }) : baseL;
  var RIGHT = subLeft ? baseR.filter(function (x) { return x !== "submission menu"; }) : baseR;
  // Blok lain yang tidak cocok dengan daftar di atas ("other") ditaruh di bawah kolom kanan.

  function has(b, sel) { return (b.matches && b.matches(sel)) || !!b.querySelector(sel); }
  function key(b) {
    // 1) kenali dari ISI blok (paling pasti), 2) baru dari judul blok
    if (has(b, ".jw-volumes")) return "journal archive published volumes";
    if (has(b, "#ch-archive,.ch-ar")) return "journal archive";
    if (has(b, "#ch-country,.ch-cs")) return "diversity of authors";
    if (has(b, 'a[href$="/focusscope"]') || has(b, "#customblock-menu-tema-child")) return "additional information";
    if (has(b, "ul.ch-logos")) return "cheesa profiles";
    var t = b.querySelector(".title");
    return (t ? t.textContent : "").replace(/\s+/g, " ").trim().toLowerCase();
  }
  function pos(list, s) {
    if (!s) return -1;
    for (var i = 0; i < list.length; i++) if (s.indexOf(list[i]) > -1) return i;
    return -1;
  }
  function div(cls) { var d = document.createElement("div"); d.className = cls; return d; }

  function init() {
    var sb = document.querySelector(".pkp_structure_sidebar");
    var content = sb && sb.closest(".pkp_structure_content");
    if (!sb || !content || content.classList.contains("ch-3col")) return;
    // semua elemen anak sidebar dianggap blok (termasuk widget yang dipasang script tanpa pembungkus .pkp_block)
    var blocks = [].filter.call(sb.children, function (c) {
      return !/^(SCRIPT|STYLE)$/.test(c.tagName) && !/ch-sb-/.test(c.className);
    });
    if (!blocks.length) return;

    var L = [], R = [], O = [];
    blocks.forEach(function (b) {
      var s = key(b), l = pos(LEFT, s), r = pos(RIGHT, s);
      if (l > -1) {
        L.push([l, b]);
        // CSS: blok bertanda .ch-keep tidak disembunyikan di halaman selain beranda (tanpa bergantung pada ID blok).
        // Journal Archive: semua halaman selain beranda. Additional Information + Submission Menu: selain beranda & artikel.
        if (!home && (s.indexOf("journal archive") > -1 ||
            (subLeft && (s.indexOf("additional information") > -1 || s.indexOf("submission menu") > -1)))) b.classList.add("ch-keep");
      } else if (r > -1) R.push([r, b]); else O.push(b);
    });
    function byIdx(a, b) { return a[0] - b[0]; }
    L.sort(byIdx); R.sort(byIdx);

    var cl = div("ch-sb-left"), cr = div("ch-sb-right");
    L.forEach(function (x) { cl.appendChild(x[1]); });
    R.forEach(function (x) { cr.appendChild(x[1]); });
    O.forEach(function (b) { cr.appendChild(b); });
    sb.appendChild(cl); sb.appendChild(cr);
    content.classList.add("ch-3col");
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

;

(function () {
  var DEFAULT_TAB = "abstract";   // tab pertama yang terbuka: keywords | abstract | authors | references | license

  var ICO = function (d, cls) { return '<svg class="ch-ico' + (cls ? " " + cls : "") + '" viewBox="0 0 24 24" aria-hidden="true">' + d + '</svg>'; };
  var SVG = {
    cal:   ICO('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M9 15l2 2 4-4"/>'),
    book:  ICO('<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>'),
    tag:   ICO('<path d="M3 12l9-9h8v8l-9 9z"/><circle cx="16" cy="8" r="1.2"/>'),
    doc:   ICO('<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>'),
    quote: ICO('<path d="M7 8h3v5H6c0 2 1 3 3 3M15 8h3v5h-4c0 2 1 3 3 3"/>'),
    mail:  ICO('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>')
  };

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function txt(n) { return n ? n.textContent.replace(/\s+/g, " ").trim() : ""; }
  function mailOf(a) { return a ? (a.getAttribute("href") || "").replace(/^mailto:/i, "").split("?")[0].trim() : ""; }

  function init() {
    var art = document.querySelector("article.obj_article_details");
    if (!art || art.classList.contains("ch-art-ready")) return;
    var main = art.querySelector(".main_entry"), side = art.querySelector(".entry_details");
    if (!main) return;
    var authorsEl = main.querySelector(".item.authors"), doiEl = main.querySelector(".item.doi");

    /* ================= 1. KARTU KEPALA ================= */
    var head = el("div", "ch-art-head"), info = el("div", "ch-head-info");
    head.appendChild(info);

    // --- penulis: nama, nomor afiliasi, ikon email (corresponding), bendera (dari Author Biographies), ORCID
    if (authorsEl) {
      var lis = [].slice.call(authorsEl.querySelectorAll("ul.authors > li"));
      var bios = [].slice.call(main.querySelectorAll(".item.author_bios .sub_item"));
      var flags = bios.map(function (s) {
        var i = s.querySelector("img.ch-flag-one"); return i ? i.getAttribute("src") : "";
      });
      // Email dari kolom Biography di OJS: tautan mailto: atau alamat email yang diketik biasa.
      var EMAIL_RE = /[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/;
      var bioInfo = bios.map(function (s) {
        var v = s.querySelector(".value") || s, m = mailOf(v.querySelector('a[href^="mailto:"]'));
        if (!m) { var x = EMAIL_RE.exec(v.textContent); m = x ? x[0] : ""; }
        return { label: txt(s.querySelector(".label")).toLowerCase(), email: m };
      });

      // Sumber email, berurutan: (1) penanda template OJS <span class="ch-corr" data-email="...">
      // (2) tautan mailto: di <li> penulis, (3) email di Biography penulis itu (dicocokkan lewat nama)
      function emailOf(li) {
        var cr = li.querySelector(".ch-corr");
        if (cr) return cr.getAttribute("data-email") || "";
        var m = mailOf(li.querySelector('a[href^="mailto:"]'));
        if (m) return m;
        var nm = txt(li.querySelector(".name")).toLowerCase();
        for (var k = 0; k < bioInfo.length; k++) if (nm && bioInfo[k].label.indexOf(nm) > -1) return bioInfo[k].email;
        return "";
      }

      var affs = [], rors = [];
      var data = lis.map(function (li, i) {
        var a = li.querySelector(".affiliation"), t = "", ror = null;
        if (a) {
          var c = a.cloneNode(true);
          [].forEach.call(c.querySelectorAll("a"), function (x) { x.parentNode.removeChild(x); });
          t = txt(c); ror = a.querySelector("a");
        }
        var k = affs.indexOf(t);
        if (t && k < 0) { affs.push(t); rors.push(ror); k = affs.length - 1; }
        var o = li.querySelector(".orcid a");
        return { name: txt(li.querySelector(".name")), aff: t ? k : -1, orcid: o ? o.href : "", flag: flags.length === lis.length ? flags[i] : "", email: emailOf(li) };
      });
      var multi = affs.length > 1;
      var line = el("div", "ch-authors");
      data.forEach(function (d, i) {
        var s = el("span", "ch-au"), n = el("span", "ch-au-name"); n.textContent = d.name; s.appendChild(n);
        if (multi && d.aff >= 0) { var sp = el("sup"); sp.textContent = d.aff + 1; s.appendChild(sp); }
        if (d.email) {
          var m = el("a", "ch-au-mail", SVG.mail);
          m.href = "mailto:" + d.email; m.title = "Corresponding author: " + d.email;
          m.setAttribute("aria-label", "Email corresponding author " + d.name);
          s.appendChild(m);
        }
        if (d.flag) { var f = el("img", "ch-au-flag"); f.src = d.flag; f.alt = ""; f.loading = "lazy"; s.appendChild(f); }
        if (d.orcid) {
          var o = el("a", "ch-au-orcid"); o.href = d.orcid; o.target = "_blank"; o.rel = "noopener";
          o.title = "ORCID: " + d.orcid; o.setAttribute("aria-label", "ORCID " + d.name); o.textContent = "iD"; s.appendChild(o);
        }
        line.appendChild(s);
        if (i < data.length - 1) line.appendChild(document.createTextNode(", "));
      });
      info.appendChild(line);
      if (affs.length) {
        var ol = el("ol", "ch-affs");
        affs.forEach(function (t, i) {
          var li = el("li");
          if (multi) { var b = el("b"); b.textContent = i + 1; li.appendChild(b); }
          li.appendChild(document.createTextNode(t));
          if (rors[i]) { var r = rors[i].cloneNode(true); r.className = "ch-ror"; li.appendChild(document.createTextNode(" ")); li.appendChild(r); }
          ol.appendChild(li);
        });
        info.appendChild(ol);
      }
      var corr = data.filter(function (d) { return d.email; });
      if (corr.length) {
        var note = el("div", "ch-corr-note", SVG.mail);
        note.appendChild(document.createTextNode("Corresponding author: "));
        corr.forEach(function (d, i) {
          var a = el("a"); a.href = "mailto:" + d.email; a.textContent = d.email; note.appendChild(a);
          if (i < corr.length - 1) note.appendChild(document.createTextNode(", "));
        });
        info.appendChild(note);
      }
      authorsEl.classList.add("ch-hide");
    }

    // --- baris meta: Published | Issue | Section | DOI
    function cell(icon, label, node) {
      var c = el("div", "ch-meta-item", icon), b = el("div"), l = el("span", "ch-meta-label"), v = el("span", "ch-meta-val");
      l.textContent = label; v.appendChild(node); b.appendChild(l); b.appendChild(v); c.appendChild(b); return c;
    }
    var meta = el("div", "ch-meta");
    if (side) {
      var pub = side.querySelector(".item.published .value");
      if (pub) meta.appendChild(cell(SVG.cal, "Published", document.createTextNode(txt(pub))));
      [].forEach.call(side.querySelectorAll(".item.issue .sub_item"), function (s) {
        var lab = txt(s.querySelector(".label")).toLowerCase(), val = s.querySelector(".value");
        if (!val) return;
        if (lab === "issue") { var a = val.querySelector("a"); meta.appendChild(cell(SVG.book, "Issue", a ? a.cloneNode(true) : document.createTextNode(txt(val)))); }
        else if (lab === "section") meta.appendChild(cell(SVG.tag, "Section", document.createTextNode(txt(val))));
      });
    }
    var doiA = doiEl && doiEl.querySelector(".value a");
    if (doiA) {
      var da = doiA.cloneNode(false); da.href = doiA.href; da.textContent = doiA.href.replace(/^https?:\/\/(dx\.)?doi\.org\//, "");
      meta.appendChild(cell(SVG.doc, "DOI", da));
    }
    if (meta.children.length) info.appendChild(meta);
    if (doiEl) doiEl.classList.add("ch-hide");

    // --- tombol: Download PDF + Cite Article
    var acts = el("div", "ch-actions");
    if (side) [].slice.call(side.querySelectorAll(".galleys_links a")).forEach(function (a) { acts.appendChild(a); });
    var citeItem = side && side.querySelector(".item.citation");
    if (citeItem) {
      var cb = el("button", "ch-cite-btn", SVG.quote + "Cite Article"); cb.type = "button";
      acts.appendChild(cb);
      buildModal(citeItem, cb);
    }
    if (acts.children.length) info.appendChild(acts);

    main.insertBefore(head, main.firstChild);

    /* ================= 2. COVER ARTIKEL =================
       Selalu di bawah kartu penulis (di atas tab). Hanya cover milik artikel; OJS memakai cover TERBITAN
       sebagai pengganti bila artikel tidak punya cover (dibungkus tautan /issue/view/ atau bernama cover_issue_*),
       dan itu tidak ditampilkan: bila artikel tidak punya cover, bagian ini dibiarkan kosong. */
    var holder = el("div", "ch-cover-holder");
    head.parentNode.insertBefore(holder, head.nextSibling);
    var img = side && side.querySelector(".item.cover_image img");
    if (img) {
      var isIssueCover = (img.closest && img.closest('a[href*="/issue/view/"]')) || /cover_issue/i.test(img.getAttribute("src") || "");
      if (!isIssueCover) {
        var fig = el("figure", "ch-cover"), lnk = el("a");
        lnk.href = img.currentSrc || img.src; lnk.target = "_blank"; lnk.rel = "noopener"; lnk.title = "Open full-size image";
        lnk.appendChild(img); fig.appendChild(lnk);
        holder.appendChild(fig);
      }
    }

    /* ================= 3. TAB ================= */
    var defs = [
      { key: "keywords",   label: "Keywords",           el: main.querySelector(".item.keywords") },
      { key: "abstract",   label: "Abstract",           el: main.querySelector(".item.abstract") },
      { key: "fulltext",   label: "Full Text",          el: main.querySelector(".item.fulltext") },
      { key: "authors",    label: "Author Biographies", el: main.querySelector(".item.author_bios") },
      { key: "references", label: "References",         el: main.querySelector(".item.references") },
      { key: "license",    label: "Licenses",           el: side && side.querySelector(".item.copyright") }
    ].filter(function (d) { return d.el; });

    var kv = main.querySelector(".item.keywords .value");
    if (kv) {
      var words = kv.textContent.split(",").map(function (s) { return s.trim(); }).filter(Boolean);
      kv.textContent = "";
      words.forEach(function (w) { var s = el("span", "ch-chip"); s.textContent = w; kv.appendChild(s); });
    }
    var dl = main.querySelector(".item.downloads_chart");
    if (dl && side) side.insertBefore(dl, side.querySelector(".citations-container"));

    if (defs.length) {
      var bar = el("div", "ch-tabs"), wrap = el("div", "ch-panels"), tabs = [];
      bar.setAttribute("role", "tablist"); bar.setAttribute("aria-label", "Article details");
      defs.forEach(function (d) {
        var btn = el("button", "ch-tab"); btn.type = "button"; btn.id = "ch-tab-" + d.key;
        btn.setAttribute("role", "tab"); btn.setAttribute("aria-controls", "ch-panel-" + d.key);
        btn.appendChild(document.createTextNode(d.label));
        if (d.key === "references") {
          var n = d.el.querySelectorAll(".value p").length;
          if (n) { var c = el("span", "ch-count"); c.textContent = n; btn.appendChild(c); }
        }
        var panel = el("div", "ch-panel"); panel.id = "ch-panel-" + d.key;
        panel.setAttribute("role", "tabpanel"); panel.setAttribute("aria-labelledby", btn.id);
        panel.appendChild(d.el);
        btn.addEventListener("click", function () { activate(d.key); });
        btn.addEventListener("keydown", function (e) {
          var i = tabs.indexOf(btn), t = null;
          if (e.key === "ArrowRight") t = tabs[(i + 1) % tabs.length];
          else if (e.key === "ArrowLeft") t = tabs[(i - 1 + tabs.length) % tabs.length];
          if (t) { e.preventDefault(); t.focus(); t.click(); }
        });
        bar.appendChild(btn); wrap.appendChild(panel); tabs.push(btn);
      });
      var activate = function (key) {
        defs.forEach(function (d, i) {
          var on = d.key === key;
          tabs[i].setAttribute("aria-selected", on ? "true" : "false");
          tabs[i].tabIndex = on ? 0 : -1;
          wrap.children[i].hidden = !on;
        });
      };
      holder.parentNode.insertBefore(bar, holder.nextSibling);
      bar.parentNode.insertBefore(wrap, bar.nextSibling);
      var h = (location.hash || "").replace("#", "");
      activate(defs.some(function (d) { return d.key === h; }) ? h :
               (defs.some(function (d) { return d.key === DEFAULT_TAB; }) ? DEFAULT_TAB : defs[0].key));
    }

    /* ================= 4. BERSIHKAN KOLOM KANAN ================= */
    if (side) {
      [".item.galleys", ".item.cover_image", ".item.published", ".item.issue"].forEach(function (s) {
        var n = side.querySelector(s); if (n && n.parentNode) n.parentNode.removeChild(n);
      });
      if (!side.children.length) side.parentNode.removeChild(side);
    }
    art.classList.add("ch-art-ready");
  }

  /* ================= POP UP CITE ARTICLE ================= */
  function buildModal(citeItem, trigger) {
    var modal = el("div", "ch-modal"); modal.hidden = true;
    modal.setAttribute("role", "dialog"); modal.setAttribute("aria-modal", "true"); modal.setAttribute("aria-labelledby", "ch-modal-title");
    var box = el("div", "ch-modal-box"), hd = el("div", "ch-modal-head"), t = el("h2"); t.id = "ch-modal-title"; t.textContent = "Cite Article";
    var x = el("button", "ch-modal-x", "&times;"); x.type = "button"; x.setAttribute("aria-label", "Close");
    hd.appendChild(t); hd.appendChild(x);
    var body = el("div", "ch-modal-body"); body.appendChild(citeItem);      // dipindah (bukan disalin) supaya fitur "More Citation Formats" tetap jalan
    var ft = el("div", "ch-modal-foot"), copy = el("button", "ch-copy-btn", "Copy citation"); copy.type = "button"; ft.appendChild(copy);
    box.appendChild(hd); box.appendChild(body); box.appendChild(ft); modal.appendChild(box); document.body.appendChild(modal);

    var last = null;
    function open() { last = document.activeElement; modal.hidden = false; document.documentElement.classList.add("ch-modal-open"); x.focus(); }
    function close() { modal.hidden = true; document.documentElement.classList.remove("ch-modal-open"); if (last && last.focus) last.focus(); }
    trigger.addEventListener("click", open);
    x.addEventListener("click", close);
    modal.addEventListener("click", function (e) { if (e.target === modal) close(); });
    document.addEventListener("keydown", function (e) {
      if (modal.hidden) return;
      if (e.key === "Escape") { close(); return; }
      if (e.key === "Tab") {
        var f = [].filter.call(modal.querySelectorAll("a[href],button,[tabindex]:not([tabindex='-1'])"), function (n) { return n.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], lastEl = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
        else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
      }
    });
    copy.addEventListener("click", function () {
      var out = document.getElementById("citationOutput"), s = out ? (out.innerText || out.textContent).trim() : "";
      function done() { copy.textContent = "Copied!"; setTimeout(function () { copy.textContent = "Copy citation"; }, 1600); }
      function fallback() {
        var ta = document.createElement("textarea"); ta.value = s; ta.style.position = "fixed"; ta.style.opacity = "0";
        document.body.appendChild(ta); ta.select(); try { document.execCommand("copy"); done(); } catch (e) {} document.body.removeChild(ta);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(s).then(done, fallback); else fallback();
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

;

(function () {
  function run() {
    var ci = document.querySelector('.page_index_journal .current_issue') || document.querySelector('.current_issue');
    if (!ci || ci.querySelector('.ch-current-btn')) { return; }
    var host = ci.querySelector('.obj_issue_toc') || ci;
    var p = document.createElement('p');
    p.className = 'ch-current-btn';
    var a = document.createElement('a');
    a.className = 'ch-btn';
    a.href = window.chBase() + '/issue/current';
    a.textContent = 'See Current Issue \u2192';
    p.appendChild(a);
    host.appendChild(p);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); }
  else { run(); }
})();

;

(function () {
  function flag() {
    if (!document.body) { return; }
    if (document.querySelector('.page_index_journal')) { document.body.classList.add('ch-home'); }
    if (/\/article\/view\//.test(window.location.pathname)) { document.body.classList.add('ch-article-page'); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', flag); }
  else { flag(); }
})();

;

(function () {
  if (!/\/article\/view\//.test(window.location.pathname)) { return; }
  var done = false;

  function build() {
    if (done) { return true; }
    var meta = document.querySelector('.obj_article_details .ch-meta');
    var left = document.querySelector('.ch-sb-left');
    if (!meta || !left) { return false; }               // kartu kepala / kolom kiri belum dibuat
    done = true;

    var actions = document.querySelector('.obj_article_details .ch-actions');
    var aside = document.createElement('aside');  aside.className = 'ch-art-info';
    var head  = document.createElement('div');    head.className  = 'ch-art-info-title';
    var body  = document.createElement('div');    body.className  = 'ch-art-info-body';
    head.textContent = 'Article Info';
    aside.appendChild(head);
    aside.appendChild(body);
    body.appendChild(meta);
    if (actions) { body.appendChild(actions); }
    left.insertBefore(aside, left.firstChild);
    return true;
  }

  function start() {
    if (build()) { return; }
    var obs = new MutationObserver(function () {
      if (build()) { obs.disconnect(); }
    });
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(function () { obs.disconnect(); }, 10000);   // berhenti menunggu setelah 10 detik
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); }
  else { start(); }
})();

;

(function () {
  var items = [
    { label: 'Online ISSN', no: '2615-2347', url: 'https://portal.issn.org/resource/ISSN/2615-2347' },
    { label: 'Print ISSN',  no: '2614-8757', url: 'https://portal.issn.org/resource/ISSN/2614-8757' }
  ];
  function run() {
    var head = document.getElementById('headerNavigationContainer') || document.querySelector('.pkp_structure_head');
    if (!head || head.querySelector('.ch-issn')) { return; }
    var box = document.createElement('div');
    box.className = 'ch-issn';
    items.forEach(function (it) {
      var a = document.createElement('a');
      a.href = it.url; a.target = '_blank'; a.rel = 'noopener';
      a.title = it.label + ' ' + it.no + ' (open in ISSN portal)';
      a.innerHTML = it.label + ' : <b>' + it.no + '</b>';
      box.appendChild(a);
    });
    head.appendChild(box);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); }
  else { run(); }
})();

;

(function () {
  // v5: cache lama dibuang (v4 belum membaca ORCID dari daftar penulis di halaman artikel)
  var PREFIX = 'ch-art-v5:', TTL = 7 * 24 * 3600 * 1000;
  var GAP = 150;      // jeda (ms) setelah satu permintaan selesai
  var MAX_PAR = 3;    // permintaan sekaligus (turunkan ke 1-2 bila hosting membatasi)

  function txt(s) { return (s || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim(); }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function safeUrl(u, base) {
    try { var x = new URL(u, base); return /^https?:$/.test(x.protocol) ? x.href : ''; } catch (e) { return ''; }
  }
  function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  /* ---------- baca data dari halaman artikel (tag meta citation_*) ---------- */
  function parse(doc, pageUrl) {
    var authors = [], cur = null, doi = '', date = '';
    Array.prototype.forEach.call(doc.querySelectorAll('meta[name^="citation_"]'), function (m) {
      var n = m.getAttribute('name'), v = txt(m.getAttribute('content'));
      if (!v) return;
      if (n === 'citation_author') { cur = { name: v, aff: '', orcid: '' }; authors.push(cur); }
      else if (n === 'citation_author_institution' && cur && !cur.aff) { cur.aff = v; }
      else if (n === 'citation_author_orcid' && cur) { cur.orcid = v; }
      else if (n === 'citation_doi' && !doi) { doi = v.replace(/^https?:\/\/(dx\.)?doi\.org\//i, ''); }
      else if (n === 'citation_publication_date' && !date) { date = v.replace(/\//g, '-'); }
    });
    // ORCID cadangan: OJS tidak selalu menulis tag meta citation_author_orcid,
    // jadi dibaca juga dari daftar penulis di halaman artikel (ikon/tautan orcid.org)
    var pageAuthors = Array.prototype.map.call(doc.querySelectorAll('.item.authors ul.authors > li, .item.authors li'), function (li) {
      var nm = li.querySelector('.name'), o = li.querySelector('.orcid a[href], a[href*="orcid.org"]');
      return { name: txt(nm ? nm.textContent : '').toLowerCase(), orcid: o ? o.getAttribute('href') : '' };
    }).filter(function (x) { return x.name; });
    authors.forEach(function (a, i) {
      if (a.orcid) return;
      var low = a.name.toLowerCase(), k, hit = null;
      for (k = 0; k < pageAuthors.length; k++)
        if (pageAuthors[k].name === low || pageAuthors[k].name.indexOf(low) > -1 || low.indexOf(pageAuthors[k].name) > -1) { hit = pageAuthors[k]; break; }
      if (!hit && pageAuthors.length === authors.length) hit = pageAuthors[i];
      if (hit && hit.orcid) a.orcid = hit.orcid.replace(/^.*?(\d{4}-\d{4}-\d{4}-\d{3}[\dX]).*$/i, '$1');
    });
    authors.forEach(function (a) {
      if (!a.orcid) return;
      if (!/^https?:/i.test(a.orcid)) a.orcid = 'https://orcid.org/' + a.orcid;
      a.orcid = safeUrl(a.orcid, pageUrl);
    });

    // Negara + bendera: dibaca dari teks Author Biographies (biografi berakhir dengan nama negara),
    // lalu dari akhir teks afiliasi bila biografi tidak ada. Nama negara dibuang dari afiliasi agar tidak dobel.
    var bios = Array.prototype.map.call(doc.querySelectorAll('.item.author_bios .sub_item'), function (s) {
      var lab = s.querySelector('.label'), val = s.querySelector('.value') || s;
      return { label: txt(lab ? lab.textContent : '').toLowerCase(), text: txt(val.textContent) };
    });
    function dropCountry(s, name) { return txt(s.replace(new RegExp('(?:^|,\\s*)' + esc(name) + '[\\s.;]*$', 'i'), '')); }
    authors.forEach(function (a, i) {
      var low = a.name.toLowerCase(), b = null, k;
      for (k = 0; k < bios.length; k++)
        if (bios[k].label && (bios[k].label.indexOf(low) > -1 || low.indexOf(bios[k].label) > -1)) { b = bios[k]; break; }
      if (!b && bios.length === authors.length) b = bios[i];
      var f = window.chFlagFor && ((b && window.chFlagFor(b.text)) || (a.aff && window.chFlagFor(a.aff)));
      if (!f) return;
      a.flag = f.src; a.country = f.name;
      if (a.aff) a.aff = dropCountry(a.aff, f.name);
      else if (b && b.text.length <= 120) a.aff = dropCountry(b.text, f.name);
    });

    // Cover: hanya cover milik artikel. Cover TERBITAN (tautan /issue/view/ atau cover_issue_*) diabaikan.
    var img = doc.querySelector('.cover_image img'), cover = '';
    if (img) {
      var src = img.getAttribute('src') || '';
      var inIssue = img.closest && img.closest('a[href*="/issue/view/"]');
      if (!inIssue && !/cover_issue/i.test(src)) cover = safeUrl(src, pageUrl);
    }
    if (!date) { var pd = doc.querySelector('.item.published .value'); if (pd) date = txt(pd.textContent); }

    return { authors: authors, doi: doi, date: date, cover: cover };
  }

  /* ---------- buang baris statistik bawaan OJS (Views 0, tanpa Download) ---------- */
  function dropNativeStats(card) {
    var w = document.createTreeWalker(card, NodeFilter.SHOW_TEXT, null), n, tn = null;
    while ((n = w.nextNode())) {
      if (/Views:/.test(n.nodeValue) && !(n.parentNode.closest && n.parentNode.closest('.ch-stats'))) { tn = n; break; }
    }
    if (!tn) return;
    var KEEP = '.title, .authors, .consent, .galleys_links, .cover, a[href*="doi.org"]';
    var p = tn.parentNode;
    if (p !== card && !p.querySelector(KEEP)) {
      // statistik punya pembungkus sendiri: hapus pembungkusnya
      while (p.parentNode !== card && !p.parentNode.querySelector(KEEP)) p = p.parentNode;
      p.parentNode.removeChild(p);
      return;
    }
    // statistik bercampur dengan isi lain: hapus hanya teks, ikon, dan angka tebalnya
    var anchor = card.querySelector('.galleys_links');
    Array.prototype.slice.call(p.childNodes).forEach(function (node) {
      if (node.nodeType === 1 && node.classList.contains('ch-stats')) return;
      if (anchor) {
        if (node.contains(anchor)) return;
        if (!(anchor.compareDocumentPosition(node) & Node.DOCUMENT_POSITION_FOLLOWING)) return;
      }
      var isTxt = node.nodeType === 3 && /\||Views|Download|Published/.test(node.nodeValue);
      var isIco = node.nodeType === 1 && (node.matches('i.fa') || node.tagName === 'B');
      if (isTxt || isIco) p.removeChild(node);
    });
  }

  /* ---------- tempel ke kartu ---------- */
  function apply(card, d, href) {
    card.setAttribute('data-ch-filled', '1');
    var title = card.querySelector('.title');

    if (d.cover && !card.querySelector('.cover') && title) {
      var cv = el('div', 'cover'), a = el('a', 'file'), im = el('img');
      a.href = href; im.src = d.cover; im.alt = '';
      a.appendChild(im); cv.appendChild(a);
      title.parentNode.insertBefore(cv, title);
    }

    var box = card.querySelector('.meta .authors') || card.querySelector('.authors');
    if (box && d.authors.length) {
      box.textContent = '';
      d.authors.forEach(function (au) {
        var c = el('div', 'consent');
        c.appendChild(el('b', null, au.name + ','));
        if (au.aff) c.appendChild(document.createTextNode('\u00a0' + au.aff + (au.country ? ',' : '')));
        if (au.country) c.appendChild(document.createTextNode('\u00a0' + au.country + ' '));
        if (au.flag && /^https:\/\/flagcdn\.com\//.test(au.flag)) {
          var fl = el('img', 'ch-flag-one');
          fl.src = au.flag; fl.alt = au.country || ''; fl.title = au.country || ''; fl.loading = 'lazy';
          c.appendChild(fl);
        }
        if (au.orcid) {
          var o = el('a'), oi = el('img');
          o.href = au.orcid; o.target = '_blank'; o.rel = 'noopener';
          o.title = 'View ORCID Profile'; o.style.textDecoration = 'none';
          oi.src = 'https://pics.unipma.ac.id/ORCIDiD_icon16x16.webp'; oi.alt = 'ORCID';
          o.appendChild(document.createTextNode(' ')); o.appendChild(oi);
          c.appendChild(document.createTextNode(' ')); c.appendChild(o);
        }
        box.appendChild(c);
      });
    }

    if (d.doi && !card.querySelector('a[href*="doi.org"]')) {
      var p = el('p'), da = el('a'), di = el('img');
      da.href = 'https://doi.org/' + d.doi; da.target = '_blank'; da.rel = 'noopener';
      da.style.color = '#000099'; da.style.display = 'inline-block';
      di.src = 'https://pics.unipma.ac.id/icon-doi2.png'; di.alt = 'DOI';
      da.appendChild(di); da.appendChild(document.createTextNode(' ' + d.doi));
      p.appendChild(da);
      var ul = card.querySelector('.galleys_links');
      card.insertBefore(p, ul || null);
    }

    // Views & Download sudah ditampilkan plugin Article Statistics: di sini hanya "Published"
    if (d.date) {
      dropNativeStats(card);
      var s = el('div', 'ch-stats');
      s.appendChild(document.createTextNode('|| '));
      function item(icon, label, val, bold) {
        var i = el('i', 'fa fa-fw ' + icon); i.setAttribute('aria-hidden', 'true');
        s.appendChild(i); s.appendChild(document.createTextNode(' ' + label + ' '));
        if (bold) s.appendChild(el('b', null, val)); else s.appendChild(document.createTextNode(val));
        s.appendChild(document.createTextNode(' || '));
      }
      item('fa-calendar-check-o', 'Published:', d.date, false);
      card.appendChild(s);
    }
  }

  /* ---------- cache + antrean (3 permintaan sekaligus) ---------- */
  function cacheGet(k) {
    try {
      var r = JSON.parse(localStorage.getItem(PREFIX + k) || 'null');
      return r && Date.now() - r.t < TTL ? r.d : null;
    } catch (e) { return null; }
  }
  function cacheSet(k, d) { try { localStorage.setItem(PREFIX + k, JSON.stringify({ t: Date.now(), d: d })); } catch (e) {} }

  var queue = [], active = 0;
  function runJob(job) {
    active++;
    fetch(job.href, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
      .then(function (html) {
        var d = parse(new DOMParser().parseFromString(html, 'text/html'), job.href);
        cacheSet(job.href, d);
        apply(job.card, d, job.href);
      })
      .catch(function () {})
      .then(function () { setTimeout(function () { active--; pump(); }, GAP); });
  }
  function pump() {
    while (active < MAX_PAR && queue.length) {
      var job = queue.shift();
      var cached = cacheGet(job.href);
      if (cached) apply(job.card, cached, job.href); else runJob(job);
    }
  }
  function enqueue(card) {
    if (card.getAttribute('data-ch-queued')) return;
    card.setAttribute('data-ch-queued', '1');
    var a = card.querySelector('.title a');
    if (!a) return;
    queue.push({ card: card, href: a.href });
    pump();
  }

  function start() {
    var cards = Array.prototype.filter.call(document.querySelectorAll('.obj_article_summary'), function (c) {
      return !c.getAttribute('data-ch-filled') && c.querySelector('.title a') && !c.querySelector('script.ch-sum-data');
    });
    if (!cards.length) return;
    // yang sudah ada di cache langsung ditampilkan, tanpa antre
    cards = cards.filter(function (c) {
      var a = c.querySelector('.title a'), d = a && cacheGet(a.href);
      if (!d) return true;
      c.setAttribute('data-ch-queued', '1');
      apply(c, d, a.href);
      return false;
    });
    if (!cards.length) return;
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); enqueue(e.target); } });
      }, { rootMargin: '600px' });
      cards.forEach(function (c) { io.observe(c); });
    } else { cards.forEach(enqueue); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();

} else { window.chLegacyFootSkipped = true; }
