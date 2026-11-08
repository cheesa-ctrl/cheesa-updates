/**
 * Country Statistics Plugin — Dashboard JavaScript
 * Renders bar chart, donut chart, tables, search/filter, and tab switching.
 * No external dependencies.
 */
(function () {
  'use strict';

  var D = window.countryStatsData;
  if (!D) return;

  // Chart color palette
  var COLORS = [
    '#0d9488','#0891b2','#2563eb','#7c3aed','#c026d3',
    '#e11d48','#ea580c','#d97706','#65a30d','#059669',
    '#6366f1','#a855f7'
  ];

  // ---------------------------------------------------------------------------
  //  Utility
  // ---------------------------------------------------------------------------

  function formatCountry(d) {
    switch (D.countryDisplay) {
      case 'code': return d.code;
      case 'name': return d.name;
      case 'both':
      default:     return d.code + ' \u2014 ' + d.name;
    }
  }

  function shortCountry(d, maxLen) {
    var name = d.name;
    if (name.length > (maxLen || 18)) name = name.substring(0, maxLen || 18) + '\u2026';
    return d.code + ' \u2014 ' + name;
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  function pct(val, total) {
    return total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
  }

  function sortData(data, order) {
    var arr = data.slice();
    if (order === 'name_asc') {
      arr.sort(function (a, b) { return a.name.localeCompare(b.name); });
    } else {
      arr.sort(function (a, b) { return b.count - a.count; });
    }
    return arr;
  }

  // ---------------------------------------------------------------------------
  //  Bar Chart
  // ---------------------------------------------------------------------------

  function renderBarChart(data) {
    var el = document.getElementById('csBarChart');
    if (!el) return;

    var sorted = sortData(data, 'count_desc');
    var top12  = sorted.slice(0, 12);
    var max    = top12.length > 0 ? top12[0].count : 1;

    // Use shorter labels if container is narrow
    var containerW = el.parentElement ? el.parentElement.offsetWidth : 600;
    var useShort = containerW < 400;

    var html = '';
    for (var i = 0; i < top12.length; i++) {
      var d     = top12[i];
      var w     = (d.count / max) * 100;
      var color = COLORS[i % COLORS.length];
      var label = useShort ? shortCountry(d, 10) : formatCountry(d);

      html += '<div class="cs-bar-row">' +
        '<div class="cs-bar-country" title="' + escHtml(formatCountry(d)) + '">' + escHtml(label) + '</div>' +
        '<div class="cs-bar-track">' +
          '<div class="cs-bar-fill" style="width:' + w + '%;background:' + color + '">' +
            '<span>' + d.count + '</span>' +
          '</div>' +
        '</div>' +
      '</div>';
    }
    el.innerHTML = html;
  }

  // ---------------------------------------------------------------------------
  //  Donut Chart
  // ---------------------------------------------------------------------------

  function renderDonut(data, total) {
    var svg    = document.getElementById('csDonutChart');
    var legend = document.getElementById('csDonutLegend');
    if (!svg || !legend) return;

    var sorted = sortData(data, 'count_desc');
    var top8   = sorted.slice(0, 8);
    var topSum = 0;
    for (var i = 0; i < top8.length; i++) topSum += top8[i].count;
    var otherCount = total - topSum;

    var segments = [];
    for (var j = 0; j < top8.length; j++) {
      segments.push({
        label: formatCountry(top8[j]),
        value: top8[j].count,
        color: COLORS[j % COLORS.length]
      });
    }
    if (otherCount > 0) {
      segments.push({ label: 'Other', value: otherCount, color: '#94a3b8' });
    }

    var segTotal = 0;
    for (var k = 0; k < segments.length; k++) segTotal += segments[k].value;
    if (segTotal === 0) segTotal = 1;

    var cx = 100, cy = 100, r = 80, inner = 52;
    var angle = -Math.PI / 2;
    var paths = '';

    for (var s = 0; s < segments.length; s++) {
      var seg   = segments[s];
      var sweep = (seg.value / segTotal) * Math.PI * 2;
      if (sweep < 0.001) continue;

      var x1  = cx + r * Math.cos(angle);
      var y1  = cy + r * Math.sin(angle);
      var x2  = cx + r * Math.cos(angle + sweep);
      var y2  = cy + r * Math.sin(angle + sweep);
      var ix1 = cx + inner * Math.cos(angle + sweep);
      var iy1 = cy + inner * Math.sin(angle + sweep);
      var ix2 = cx + inner * Math.cos(angle);
      var iy2 = cy + inner * Math.sin(angle);
      var large = sweep > Math.PI ? 1 : 0;

      paths += '<path d="M ' + x1.toFixed(2) + ' ' + y1.toFixed(2) +
        ' A ' + r + ' ' + r + ' 0 ' + large + ' 1 ' + x2.toFixed(2) + ' ' + y2.toFixed(2) +
        ' L ' + ix1.toFixed(2) + ' ' + iy1.toFixed(2) +
        ' A ' + inner + ' ' + inner + ' 0 ' + large + ' 0 ' + ix2.toFixed(2) + ' ' + iy2.toFixed(2) +
        ' Z" fill="' + seg.color + '" stroke="#fff" stroke-width="2"/>';
      angle += sweep;
    }

    paths += '<text x="' + cx + '" y="' + (cy - 4) + '" class="cs-donut-center-text">' + D.totalCountries + '</text>';
    paths += '<text x="' + cx + '" y="' + (cy + 14) + '" class="cs-donut-center-label">countries</text>';

    svg.innerHTML = paths;

    var legendHtml = '';
    for (var l = 0; l < segments.length; l++) {
      var p = pct(segments[l].value, segTotal);
      legendHtml += '<div class="cs-donut-legend-item">' +
        '<span class="cs-donut-dot" style="background:' + segments[l].color + '"></span>' +
        '<span class="cs-donut-legend-label" title="' + escHtml(segments[l].label) + '">' + escHtml(segments[l].label) + '</span>' +
        '<span class="cs-donut-legend-pct">' + p + '%</span>' +
      '</div>';
    }
    legend.innerHTML = legendHtml;
  }

  // ---------------------------------------------------------------------------
  //  Tables
  // ---------------------------------------------------------------------------

  function renderTable(type, data, total) {
    var bodyId = type === 'authors' ? 'csAuthorsBody' : 'csArticlesBody';
    var body = document.getElementById(bodyId);
    if (!body) return;

    var searchId = type === 'authors' ? 'csSearchAuthors' : 'csSearchArticles';
    var searchEl = document.getElementById(searchId);
    var query = searchEl ? searchEl.value.toLowerCase() : '';

    var sorted = sortData(data, D.sortOrder);
    var max = sorted.length > 0 ? sorted[0].count : 1;

    var filtered = [];
    for (var i = 0; i < sorted.length; i++) {
      var d = sorted[i];
      if (query && d.name.toLowerCase().indexOf(query) === -1 && d.code.toLowerCase().indexOf(query) === -1) {
        continue;
      }
      filtered.push(d);
    }

    var html = '';
    for (var j = 0; j < filtered.length; j++) {
      var row  = filtered[j];
      var p    = pct(row.count, total);
      var barW = (row.count / max) * 100;

      html += '<tr>' +
        '<td style="color:var(--cs-text-muted);font-size:12px;">' + (j + 1) + '</td>' +
        '<td><div class="cs-country-cell">' +
          '<span class="cs-flag-placeholder">' + escHtml(row.code) + '</span>' +
          '<span>' + escHtml(row.name) + '</span>' +
        '</div></td>' +
        '<td><span class="cs-count-bold">' + row.count + '</span></td>' +
        '<td><div class="cs-mini-bar-bg"><div class="cs-mini-bar-fill" style="width:' + barW + '%"></div></div></td>' +
        '<td><span class="cs-pct">' + p + '%</span></td>' +
      '</tr>';
    }

    if (filtered.length === 0) {
      html = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--cs-text-muted);">No results</td></tr>';
    }

    body.innerHTML = html;
  }

  // ---------------------------------------------------------------------------
  //  Tab Switching
  // ---------------------------------------------------------------------------

  function initTabs() {
    var tabs = document.querySelectorAll('.cs-tab');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function () {
        var allTabs = document.querySelectorAll('.cs-tab');
        var allContents = document.querySelectorAll('.cs-tab-content');
        for (var j = 0; j < allTabs.length; j++) allTabs[j].classList.remove('active');
        for (var k = 0; k < allContents.length; k++) allContents[k].classList.remove('active');

        this.classList.add('active');
        var target = document.getElementById('cs-tab-' + this.getAttribute('data-tab'));
        if (target) target.classList.add('active');
      });
    }
  }

  // ---------------------------------------------------------------------------
  //  Search Handlers
  // ---------------------------------------------------------------------------

  function initSearch() {
    var searchAuthors  = document.getElementById('csSearchAuthors');
    var searchArticles = document.getElementById('csSearchArticles');

    if (searchAuthors) {
      searchAuthors.addEventListener('input', function () {
        renderTable('authors', D.authorsByCountry, D.totalAuthors);
      });
    }
    if (searchArticles) {
      searchArticles.addEventListener('input', function () {
        renderTable('articles', D.articlesByCountry, D.totalArticles);
      });
    }
  }

  // ---------------------------------------------------------------------------
  //  Filter Handlers (Admin only)
  // ---------------------------------------------------------------------------

  function initFilters() {
    if (!D.isAdmin || !D.fetchUrl) return;

    var timeFilter     = document.getElementById('csTimeFilter');
    var strategyFilter = document.getElementById('csStrategyFilter');

    function applyFilters() {
      var timeVal = timeFilter ? timeFilter.value : 'all';
      var stratVal = strategyFilter ? strategyFilter.value : 'orcid';

      var dateStart = null;
      if (timeVal === '12') {
        var d12 = new Date();
        d12.setMonth(d12.getMonth() - 12);
        dateStart = d12.toISOString().split('T')[0];
      } else if (timeVal === '24') {
        var d24 = new Date();
        d24.setMonth(d24.getMonth() - 24);
        dateStart = d24.toISOString().split('T')[0];
      }

      var url = D.fetchUrl;
      url += (url.indexOf('?') > -1 ? '&' : '?') + 'strategy=' + encodeURIComponent(stratVal);
      if (dateStart) url += '&dateStart=' + encodeURIComponent(dateStart);

      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
          try {
            var resp = JSON.parse(xhr.responseText);
            D.authorsByCountry  = resp.authorsByCountry;
            D.articlesByCountry = resp.articlesByCountry;
            D.totalArticles     = resp.totals.totalArticles;
            D.totalAuthors      = resp.totals.totalAuthors;
            D.totalCountries    = resp.totals.totalCountries;

            renderAll();
            updateCards();
          } catch (e) {
            console.error('CountryStats: failed to parse response', e);
          }
        }
      };
      xhr.send();
    }

    if (timeFilter)     timeFilter.addEventListener('change', applyFilters);
    if (strategyFilter) strategyFilter.addEventListener('change', applyFilters);
  }

  function updateCards() {
    var el;
    el = document.getElementById('csCardArticles');
    if (el) el.textContent = D.totalArticles;
    el = document.getElementById('csCardAuthors');
    if (el) el.textContent = D.totalAuthors;
    el = document.getElementById('csCardCountries');
    if (el) el.textContent = D.totalCountries;

    // Top country share
    var sorted = sortData(D.authorsByCountry, 'count_desc');
    var topShare = '';
    if (sorted.length > 0) {
      topShare = pct(sorted[0].count, D.totalAuthors) + '% (' + sorted[0].code + ')';
    }
    el = document.getElementById('csCardTopShare');
    if (el) el.textContent = topShare;
  }

  // ---------------------------------------------------------------------------
  //  Render All
  // ---------------------------------------------------------------------------

  function renderAll() {
    renderBarChart(D.authorsByCountry);
    renderDonut(D.authorsByCountry, D.totalAuthors);
    renderTable('authors',  D.authorsByCountry,  D.totalAuthors);
    renderTable('articles', D.articlesByCountry, D.totalArticles);
  }

  // ---------------------------------------------------------------------------
  //  Init
  // ---------------------------------------------------------------------------

  function init() {
    renderAll();
    initTabs();
    initSearch();
    initFilters();
  }

  // Run when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
