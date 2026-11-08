<?php

/**
 * @file plugins/generic/journalWidgets/JwRender.inc.php
 *
 * Pembuat HTML untuk setiap widget. Fungsi murni: hanya menerima data (array) dan
 * mengembalikan HTML yang sudah di-escape, sehingga mudah diuji dan dipratinjau.
 */

class JwRender {

	public static function e($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	/** URL aman: hanya http(s), mailto, path relatif / anchor. */
	public static function u($s) {
		$s = trim((string) $s);
		if ($s === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $s)) return '#';
		return self::e($s);
	}

	public static function ico($name) {
		$name = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $name));
		if ($name === '') return '';
		if (strpos($name, 'fa-') !== 0) $name = 'fa-' . $name;
		return '<i class="fa ' . $name . '" aria-hidden="true"></i>';
	}

	public static function block($id, $title, $inner, $extra = '') {
		if ($inner === '' || $inner === null) return '';
		$t = $title === null
			? '<h2 class="title jw-notitle">' . self::e(ucfirst($id)) . '</h2>'
			: '<h2 class="title">' . self::e($title) . $extra . '</h2>';
		return '<div class="pkp_block jw-block jw-' . self::e($id) . '">' . $t . '<div class="content">' . $inner . '</div></div>';
	}

	/* ---------------- 1. Journal Issue (cover terbitan terbaru) ---------------- */
	public static function issue($d) {
		if (!$d) return '';
		$img = $d['cover']
			? '<img src="' . self::u($d['cover']) . '" alt="' . self::e('Cover: ' . $d['title']) . '" loading="lazy">'
			: '<span class="jw-cover-ph"><b>' . self::e($d['journal']) . '</b><i>' . self::e($d['title']) . '</i></span>';
		$h = '<a class="jw-cover" href="' . self::u($d['url']) . '">' . $img . '</a>'
			. '<a class="jw-issue-title" href="' . self::u($d['url']) . '">' . self::e($d['title']) . '</a>';
		if (!empty($d['archive'])) $h .= '<a class="jw-btn jw-btn-ghost" href="' . self::u($d['archive']) . '">' . self::ico('archive') . ' All Issues</a>';
		return self::block('issue', 'Journal Issue', $h);
	}

	/* ---------------- 2. Tombol cepat ---------------- */
	public static function actions($d) {
		$h = '';
		foreach ($d['buttons'] as $i => $b) {
			if (!$b['url']) continue;
			$h .= '<a class="jw-btn' . ($i === 0 ? ' jw-btn-solid' : '') . '" href="' . self::u($b['url']) . '">' . self::ico($b['icon']) . ' ' . self::e($b['label']) . '</a>';
		}
		if ($d['social']) {
			$h .= '<div class="jw-social">';
			foreach ($d['social'] as $s) {
				$ext = !preg_match('#^mailto:#i', $s['url']);
				$h .= '<a href="' . self::u($s['url']) . '" title="' . self::e($s['name']) . '" aria-label="' . self::e($s['name']) . '"'
					. ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . self::ico($s['icon']) . '</a>';
			}
			$h .= '</div>';
		}
		return self::block('actions', null, $h);
	}

	/* ---------------- 3. Journal Menu berikon ---------------- */
	public static function menu($d) {
		if (!$d) return '';
		$h = '<ul class="jw-menu">';
		foreach ($d as $m) {
			$h .= '<li><a href="' . self::u($m['url']) . '"' . ($m['ext'] ? ' target="_blank" rel="noopener"' : '') . '>'
				. self::ico($m['icon']) . '<span>' . self::e($m['label']) . '</span></a></li>';
		}
		return self::block('menu', 'Journal Menu', $h . '</ul>');
	}

	/* ---------------- 4. Author Diversity ---------------- */
	public static function diversity($d) {
		if (!$d || empty($d['rows'])) return '';
		$colors = ['var(--jw-primary)', 'var(--jw-accent)', '#2f6fd6', '#c23b72', '#2a9d8f', '#8a8fa8'];
		$bar = '<div class="jw-dv-bar" aria-hidden="true">';
		$list = '<ul class="jw-dv-list">';
		foreach ($d['rows'] as $i => $r) {
			$c = $colors[min($i, count($colors) - 1)];
			$bar .= '<span style="width:' . round($r['pct'], 2) . '%;background:' . $c . '" title="' . self::e($r['name']) . '"></span>';
			$flag = $r['code'] ? '<img class="jw-flag" src="https://flagcdn.com/w40/' . self::e(strtolower($r['code'])) . '.png" alt="" loading="lazy">' : '';
			$list .= '<li><span class="jw-dot" style="background:' . $c . '"></span>' . $flag
				. '<span class="jw-dv-name">' . self::e($r['name']) . '</span><b>' . self::e(round($r['pct'])) . '%</b></li>';
		}
		$bar .= '</div>';
		$list .= '</ul>';
		$h = $bar . $list;
		if (!empty($d['more'])) $h .= '<a class="jw-btn jw-btn-solid" href="' . self::u($d['more']) . '">View Details ' . self::ico('bar-chart') . '</a>';
		$chip = ' <span class="jw-chip">' . (int) $d['countries'] . ' ' . ($d['countries'] == 1 ? 'country' : 'countries') . '</span>';
		return self::block('diversity', 'Author Diversity', $h, $chip);
	}

	/* ---------------- 5. News (pengumuman) ---------------- */
	public static function news($d) {
		if (!$d || empty($d['items'])) return '';
		$h = '';
		foreach ($d['items'] as $n) {
			$h .= '<div class="jw-news"><a class="jw-news-t" href="' . self::u($n['url']) . '">' . self::e($n['title']) . '</a>'
				. '<div class="jw-meta">' . self::ico('calendar') . ' ' . self::e($n['date']) . '</div>'
				. ($n['text'] ? '<p>' . self::e($n['text']) . '</p>' : '') . '</div>';
		}
		if (!empty($d['all'])) $h .= '<a class="jw-more" href="' . self::u($d['all']) . '">All announcements ' . self::ico('angle-right') . '</a>';
		return self::block('news', 'News', $h);
	}

	/* ---------------- 6. Keywords cloud ---------------- */
	public static function keywords($d) {
		if (!$d) return '';
		$max = 1; $min = PHP_INT_MAX;
		foreach ($d as $k) { $max = max($max, $k['n']); $min = min($min, $k['n']); }
		$h = '<div class="jw-cloud">';
		foreach ($d as $k) {
			$lvl = $max === $min ? 3 : 1 + (int) round(4 * ($k['n'] - $min) / ($max - $min));
			$h .= '<a class="jw-kw jw-kw-' . $lvl . '" href="' . self::u($k['url']) . '" title="' . self::e($k['n'] . ' article(s)') . '">' . self::e($k['kw']) . '</a> ';
		}
		return self::block('keywords', 'Keywords', $h . '</div>');
	}

	/* ---------------- 7. Most Accessed ---------------- */
	public static function popular($d) {
		if (!$d) return '';
		$h = '';
		foreach ($d as $i => $a) {
			$h .= '<div class="jw-pop"><span class="jw-rank">' . ($i + 1) . '</span><div>'
				. '<a class="jw-pop-t" href="' . self::u($a['url']) . '">' . self::e($a['title']) . '</a>'
				. '<div class="jw-meta"><span>' . self::ico('line-chart') . ' ' . self::e(number_format((int) $a['views'])) . '</span>'
				. ($a['date'] ? '<span>' . self::e($a['date']) . '</span>' : '') . '</div>'
				. ($a['authors'] ? '<div class="jw-meta jw-au">' . self::ico('user') . ' ' . self::e($a['authors']) . '</div>' : '')
				. '</div></div>';
		}
		return self::block('popular', 'Most Accessed', $h);
	}

	/* ---------------- 8. Editorial Board ---------------- */
	public static function editorial($d) {
		if (!$d || empty($d['groups'])) return '';
		$h = '';
		foreach ($d['groups'] as $g) {
			if ($g['role'] !== '') $h .= '<h3 class="jw-role">' . self::e($g['role']) . '</h3>';
			foreach ($g['people'] as $p) {
				$name = $p['url'] ? '<a href="' . self::u($p['url']) . '" target="_blank" rel="noopener">' . self::e($p['name']) . '</a>' : '<b>' . self::e($p['name']) . '</b>';
				$h .= '<div class="jw-person">' . $name . ($p['aff'] ? '<span>' . self::e($p['aff']) . '</span>' : '') . '</div>';
			}
		}
		if (!empty($d['more'])) $h .= '<a class="jw-more" href="' . self::u($d['more']) . '">' . self::ico('arrow-circle-right') . ' Read more</a>';
		return self::block('editorial', 'Editorial Board', $h);
	}

	/* ---------------- 9. Published Volumes (per tahun) ---------------- */
	public static function volumes($d) {
		if (!$d || empty($d['years'])) return '';
		$h = '';
		if (!empty($d['range'])) $h .= '<div class="jw-range">' . self::e($d['range']) . '</div>';
		$first = true;
		foreach ($d['years'] as $y => $items) {
			$h .= '<details class="jw-year"' . ($first ? ' open' : '') . '><summary>' . self::e($y) . '<span class="jw-count">' . count($items) . '</span></summary><ul>';
			foreach ($items as $it) $h .= '<li><a href="' . self::u($it['url']) . '">' . self::ico('book') . ' ' . self::e($it['label']) . '</a></li>';
			$h .= '</ul></details>';
			$first = false;
		}
		if (!empty($d['archive'])) $h .= '<a class="jw-more" href="' . self::u($d['archive']) . '">All issues ' . self::ico('angle-right') . '</a>';
		return self::block('volumes', 'Published Volumes', $h);
	}

	/* ---------------- 10. Visitors (statistik OJS per negara) ---------------- */
	public static function visitors($d) {
		if (!$d || (!$d['total'] && empty($d['rows']))) return '';
		$h = '<div class="jw-vis"><div class="jw-vis-h"><b>Visitors</b><span>' . self::e($d['period']) . '</span></div>';
		if (!empty($d['rows'])) {
			$h .= '<div class="jw-vis-grid">';
			foreach ($d['rows'] as $r) {
				$h .= '<div class="jw-vis-row"><img class="jw-flag" src="https://flagcdn.com/w40/' . self::e(strtolower($r['code'])) . '.png" alt="" loading="lazy">'
					. '<span title="' . self::e($r['name']) . '">' . self::e(strtoupper($r['code'])) . '</span><b>' . self::e(number_format((int) $r['n'])) . '</b></div>';
			}
			$h .= '</div>';
		}
		$h .= '<div class="jw-vis-total"><span>Total views &amp; downloads</span><b>' . self::e(number_format((int) $d['total'])) . '</b></div>';
		$h .= '<div class="jw-vis-note">Source: OJS usage statistics</div></div>';
		return self::block('visitors', 'Visitors', $h);
	}

	/* ---------------- 11. Google Scholar Citation (isi manual) ---------------- */
	public static function scholar($d) {
		if (!$d || ($d['all'] === '' && empty($d['years']))) return '';
		$h = '<table class="jw-gs"><thead><tr><th></th><th>All</th><th>' . self::e($d['sinceLabel']) . '</th></tr></thead><tbody>';
		foreach ([['Citations', 'all', 'since'], ['h-index', 'h', 'hSince'], ['i10-index', 'i10', 'i10Since']] as $r) {
			if ($d[$r[1]] === '' && $d[$r[2]] === '') continue;
			$h .= '<tr><td>' . $r[0] . '</td><td>' . self::e($d[$r[1]]) . '</td><td>' . self::e($d[$r[2]]) . '</td></tr>';
		}
		$h .= '</tbody></table>';
		if (!empty($d['years'])) {
			$max = max(1, max(array_values($d['years'])));
			$h .= '<div class="jw-gs-chart" role="img" aria-label="Citations per year">';
			foreach ($d['years'] as $y => $n) {
				$h .= '<div class="jw-gs-col" title="' . self::e($y . ': ' . $n) . '"><i style="height:' . max(3, round($n / $max * 100)) . '%"></i><span>' . self::e(substr($y, -2)) . '</span></div>';
			}
			$h .= '</div>';
		}
		$foot = '';
		if ($d['updated'] !== '') $foot .= '<span>Updated ' . self::e($d['updated']) . '</span>';
		if ($d['url'] !== '') $foot .= '<a href="' . self::u($d['url']) . '" target="_blank" rel="noopener">' . self::ico('google') . ' Scholar profile</a>';
		if ($foot) $h .= '<div class="jw-gs-foot">' . $foot . '</div>';
		return self::block('scholar', 'Google Scholar Citation', $h);
	}

	/* ---------------- 13. Scopus Citation (isi manual dari halaman sumber Scopus) ---------------- */
	public static function scopus($d) {
		if (!$d || ($d['citeScore'] === '' && $d['citations'] === '' && empty($d['years']))) return '';
		$h = '';
		if ($d['citeScore'] !== '') {
			$h .= '<div class="jw-sc-hero"><div class="jw-sc-score"><b>' . self::e($d['citeScore']) . '</b><span>CiteScore'
				. ($d['citeYear'] !== '' ? ' ' . self::e($d['citeYear']) : '') . '</span></div>';
			if ($d['quartile'] !== '') $h .= '<span class="jw-sc-q">' . self::e($d['quartile']) . '</span>';
			$h .= '</div>';
		}
		$cells = [['Citations', $d['citations']], ['Documents', $d['docs']], ['h-index', $d['h']], ['SJR', $d['sjr']], ['SNIP', $d['snip']]];
		$tiles = '';
		foreach ($cells as $c) if ($c[1] !== '') $tiles .= '<div><b>' . self::e($c[1]) . '</b><span>' . $c[0] . '</span></div>';
		if ($tiles) $h .= '<div class="jw-sc-tiles">' . $tiles . '</div>';
		if (!empty($d['years'])) {
			$max = max(1, max(array_values($d['years'])));
			$h .= '<div class="jw-gs-chart jw-sc-chart" role="img" aria-label="Scopus citations per year">';
			foreach ($d['years'] as $y => $n) {
				$h .= '<div class="jw-gs-col" title="' . self::e($y . ': ' . $n) . '"><i style="height:' . max(3, round($n / $max * 100)) . '%"></i><span>' . self::e(substr($y, -2)) . '</span></div>';
			}
			$h .= '</div>';
		}
		$foot = '';
		if ($d['updated'] !== '') $foot .= '<span>Updated ' . self::e($d['updated']) . '</span>';
		if ($d['url'] !== '') $foot .= '<a href="' . self::u($d['url']) . '" target="_blank" rel="noopener">' . self::ico('external-link') . ' Scopus source</a>';
		if ($foot) $h .= '<div class="jw-gs-foot">' . $foot . '</div>';
		return self::block('scopus', 'Scopus Citation', $h);
	}

	/* ---------------- 12. Poster / Flyer ---------------- */
	public static function poster($d) {
		if (!$d || $d['img'] === '') return '';
		$img = '<img src="' . self::u($d['img']) . '" alt="' . self::e($d['alt']) . '" loading="lazy">';
		$h = $d['url'] !== '' ? '<a class="jw-poster" href="' . self::u($d['url']) . '" target="_blank" rel="noopener">' . $img . '</a>' : '<div class="jw-poster">' . $img . '</div>';
		return self::block('poster', $d['title'] !== '' ? $d['title'] : 'Poster', $h);
	}
}
