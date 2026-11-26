<?php

/**
 * @file plugins/generic/cheesaGuard/classes/GuardScanner.inc.php
 *
 * Mesin pemindai + pembersih. Memeriksa konten langsung situs untuk tanda injeksi judi
 * online (judol)/peretasan, menyimpan "locator" presisi tiap temuan agar bisa dibersihkan,
 * dan menyediakan pembersih yang hanya membuang bagian berbahaya (skrip, iframe, teks
 * tersembunyi, tautan judi) sambil mempertahankan teks sah.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class GuardScanner {

	const STRONG = [
		'judol', 'gacor', 'maxwin', 'sbobet', 'togel', 'toto macau', 'toto slot', 'mahjong ways',
		'slot gacor', 'judi online', 'situs slot', 'situs judi', 'anti rungkad', 'deposit pulsa',
		'pola gacor', 'slot88', 'slot777', 'bandar togel', 'rtp live', 'rtp slot', 'scatter hitam',
		'zeus slot', 'akun pro', 'pragmatic play', 'pg soft', 'bonus new member', 'link alternatif',
		'slot online', 'situs gacor', 'daftar slot', 'link slot', 'bola gacor', 'gates of olympus',
		'nexus slot', 'server thailand', 'slot thailand', 'gampang menang', 'jp paus', 'slot dana',
	];

	const WEAK = [
		'slot', 'judi', 'casino', 'kasino', 'poker', 'jackpot', 'taruhan', 'bandar', 'gambling',
		'betting', 'spin', 'bet', 'raffi ahmad',
	];

	const INJECT = [
		'<script', '</script', 'eval(', 'base64_decode', 'document.write', 'string.fromcharcode',
		'<iframe', 'window.location', 'display:none', 'visibility:hidden', 'text-indent:-9999',
		'left:-9999', 'left: -9999', 'position:absolute;left:-', 'opacity:0', 'font-size:0',
		'<?php', 'fromcharcode', 'unescape(',
	];

	private $journalHosts;

	public function __construct($journalUrl = '') {
		$host = parse_url($journalUrl, PHP_URL_HOST);
		$this->journalHosts = array_filter([$host, 'unipma.ac.id']);
	}

	public function inspect($text) {
		if (!is_string($text) || trim($text) === '') return null;
		$low = mb_strtolower($text);

		$strong = []; foreach (self::STRONG as $k) { if (strpos($low, $k) !== false) $strong[] = $k; }
		$weak = []; foreach (self::WEAK as $k) { if (preg_match('/(?<![a-z])' . preg_quote($k, '/') . '(?![a-z])/', $low)) $weak[] = $k; }
		$inject = []; foreach (self::INJECT as $k) { if (strpos($low, $k) !== false) $inject[] = $k; }

		$extLink = false;
		if (preg_match_all('~https?://([a-z0-9.\-]+)~i', $low, $m)) {
			foreach ($m[1] as $h) {
				$own = false;
				foreach ($this->journalHosts as $jh) { if ($jh && strpos($h, mb_strtolower($jh)) !== false) { $own = true; break; } }
				if (!$own) { $extLink = true; break; }
			}
		}

		$strong = array_values(array_unique($strong));
		$weak = array_values(array_unique($weak));
		$inject = array_values(array_unique($inject));

		$flag = false; $sev = 'medium';
		if ($strong) { $flag = true; $sev = 'high'; }
		elseif (count($inject) >= 1 && ($weak || $extLink)) { $flag = true; $sev = 'high'; }
		elseif (in_array('<script', $inject) || in_array('<iframe', $inject) || in_array('eval(', $inject) || in_array('base64_decode', $inject)) { $flag = true; $sev = 'high'; }
		elseif (count($weak) >= 2 && $extLink) { $flag = true; $sev = 'medium'; }
		elseif (count($inject) >= 2) { $flag = true; $sev = 'medium'; }
		if (!$flag) return null;

		$signals = [];
		if ($strong) $signals[] = 'kata judi: ' . implode(', ', array_slice($strong, 0, 6));
		if ($weak && !$strong) $signals[] = 'kata lemah: ' . implode(', ', array_slice($weak, 0, 6));
		if ($inject) $signals[] = 'penanda injeksi: ' . implode(', ', array_slice($inject, 0, 6));
		if ($extLink) $signals[] = 'tautan ke domain luar';

		return ['severity' => $sev, 'signals' => $signals, 'snippet' => $this->snippet($text, $low, array_merge($strong, $weak, $inject))];
	}

	private function snippet($text, $low, $needles) {
		$pos = -1;
		foreach ($needles as $n) { $p = strpos($low, $n); if ($p !== false) { $pos = $p; break; } }
		if ($pos < 0) $pos = 0;
		$start = max(0, $pos - 60);
		$frag = substr($text, $start, 240);
		$frag = preg_replace('/\s+/', ' ', trim(strip_tags($frag)));
		return mb_substr($frag, 0, 200);
	}

	/**
	 * Pembersih: buang HANYA bagian berbahaya/spam, pertahankan teks sah.
	 */
	public static function clean($text) {
		$t = (string) $text;
		$t = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $t);
		$t = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $t);
		$t = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $t);
		$t = preg_replace('#<\?php\b.*?\?>#is', '', $t);
		$t = preg_replace('#<\?=.*?\?>#is', '', $t);
		// elemen dengan gaya tersembunyi (sembunyi dari pembaca -> hampir pasti spam SEO)
		$t = preg_replace('#<([a-z]+)\b[^>]*style\s*=\s*(["\'])[^"\']*(display\s*:\s*none|visibility\s*:\s*hidden|left\s*:\s*-?9999|text-indent\s*:\s*-?9999|opacity\s*:\s*0|font-size\s*:\s*0)[^"\']*\2[^>]*>.*?</\1>#is', '', $t);
		// tautan yang mengandung kata judi (kuat) -> buang seluruh anchor
		$t = preg_replace_callback('#<a\b[^>]*>.*?</a>#is', function ($m) {
			$a = mb_strtolower($m[0]);
			foreach (self::STRONG as $k) { if (strpos($a, $k) !== false) return ''; }
			return $m[0];
		}, $t);
		// atribut event & href javascript:
		$t = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $t);
		$t = preg_replace('#href\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', 'href="#"', $t);
		// rapikan spasi berlebih sisa pembuangan
		$t = preg_replace('/[ \t]{3,}/', ' ', $t);
		return $t;
	}

	/* ------------------------------------------------------------------ pemindaian */

	public function scan($contextId) {
		$out = [];
		foreach (['scanJournalSettings', 'scanAnnouncements', 'scanNavMenu', 'scanPublications', 'scanHtmlFiles'] as $m) {
			try { $this->$m($contextId, $out); }
			catch (\Throwable $e) { error_log('cheesaGuard ' . $m . ': ' . $e->getMessage()); }
		}
		return $out;
	}

	private function add(&$out, $area, $label, array $locator, $text) {
		$r = $this->inspect($text);
		if (!$r) return;
		$out[] = [
			'area' => $area, 'label' => $label, 'severity' => $r['severity'],
			'signals' => $r['signals'], 'snippet' => $r['snippet'],
			'locator' => $locator, 'fp' => md5(json_encode($locator)),
		];
	}

	private function scanJournalSettings($cid, &$out) {
		$rows = Capsule::table('journal_settings')->where('journal_id', $cid)->get(['setting_name', 'setting_value', 'locale']);
		foreach ($rows as $r) {
			$v = (string) $r->setting_value;
			if (strlen($v) < 4) continue;
			$loc = ['t' => 'journal_settings', 'col' => 'setting_value', 'k' => ['journal_id' => (int) $cid, 'setting_name' => $r->setting_name, 'locale' => (string) $r->locale]];
			$this->add($out, 'Pengaturan jurnal', $r->setting_name . ($r->locale ? " ({$r->locale})" : ''), $loc, $v);
		}
	}

	private function scanAnnouncements($cid, &$out) {
		if (!Capsule::schema()->hasTable('announcement_settings')) return;
		$ann = Capsule::table('announcements')->where('assoc_type', 256)->where('assoc_id', $cid)->pluck('announcement_id')->all();
		if (!$ann) $ann = Capsule::table('announcements')->pluck('announcement_id')->all();
		if (!$ann) return;
		$rows = Capsule::table('announcement_settings')->whereIn('announcement_id', $ann)
			->whereIn('setting_name', ['title', 'descriptionShort', 'description'])->get(['announcement_id', 'setting_name', 'setting_value', 'locale']);
		foreach ($rows as $r) {
			$loc = ['t' => 'announcement_settings', 'col' => 'setting_value', 'k' => ['announcement_id' => (int) $r->announcement_id, 'setting_name' => $r->setting_name, 'locale' => (string) $r->locale]];
			$this->add($out, 'Pengumuman', 'Announcement #' . $r->announcement_id . ' / ' . $r->setting_name, $loc, (string) $r->setting_value);
		}
	}

	private function scanNavMenu($cid, &$out) {
		if (!Capsule::schema()->hasTable('navigation_menu_item_settings')) return;
		$ids = Capsule::table('navigation_menu_items')->where('context_id', $cid)->pluck('navigation_menu_item_id')->all();
		if (!$ids) return;
		$rows = Capsule::table('navigation_menu_item_settings')->whereIn('navigation_menu_item_id', $ids)
			->whereIn('setting_name', ['content', 'title', 'remoteUrl'])->get(['navigation_menu_item_id', 'setting_name', 'setting_value', 'locale']);
		foreach ($rows as $r) {
			$v = (string) $r->setting_value;
			if (strlen($v) < 4) continue;
			$loc = ['t' => 'navigation_menu_item_settings', 'col' => 'setting_value', 'k' => ['navigation_menu_item_id' => (int) $r->navigation_menu_item_id, 'setting_name' => $r->setting_name, 'locale' => (string) $r->locale]];
			$this->add($out, 'Menu / Halaman statis', 'NavItem #' . $r->navigation_menu_item_id . ' / ' . $r->setting_name, $loc, $v);
		}
	}

	private function scanPublications($cid, &$out) {
		$subIds = Capsule::table('submissions')->where('context_id', $cid)->pluck('submission_id')->all();
		if (!$subIds) return;
		$pubIds = Capsule::table('publications')->whereIn('submission_id', $subIds)->limit(20000)->pluck('publication_id')->all();
		if (!$pubIds) return;
		$rows = Capsule::table('publication_settings')->whereIn('publication_id', $pubIds)
			->whereIn('setting_name', ['title', 'subtitle', 'abstract', 'prefix'])->limit(60000)->get(['publication_id', 'setting_name', 'setting_value', 'locale']);
		foreach ($rows as $r) {
			$v = (string) $r->setting_value;
			if (strlen($v) < 4) continue;
			$loc = ['t' => 'publication_settings', 'col' => 'setting_value', 'k' => ['publication_id' => (int) $r->publication_id, 'setting_name' => $r->setting_name, 'locale' => (string) $r->locale]];
			$this->add($out, 'Artikel (metadata)', 'Publication #' . $r->publication_id . ' / ' . $r->setting_name, $loc, $v);
		}
	}

	private function scanHtmlFiles($cid, &$out) {
		if (!Capsule::schema()->hasTable('files') || !Capsule::schema()->hasTable('submission_files')) return;
		$filesDir = Config::getVar('files', 'files_dir');
		if (!$filesDir) return;
		$rows = Capsule::table('submission_files as sf')
			->join('files as f', 'f.file_id', '=', 'sf.file_id')
			->join('submissions as s', 's.submission_id', '=', 'sf.submission_id')
			->where('s.context_id', $cid)
			->where(function ($q) { $q->where('f.mimetype', 'text/html')->orWhere('f.path', 'like', '%.html')->orWhere('f.path', 'like', '%.htm'); })
			->limit(500)->get(['f.path', 'sf.submission_id', 'sf.submission_file_id']);
		$base = realpath($filesDir);
		foreach ($rows as $r) {
			$full = rtrim($filesDir, '/') . '/' . ltrim((string) $r->path, '/');
			$rp = realpath($full);
			if (!$rp || !$base || strpos($rp, $base) !== 0) continue; // cegah path traversal
			if (!is_file($rp) || filesize($rp) > 2 * 1024 * 1024) continue;
			$content = @file_get_contents($rp, false, null, 0, 1024 * 1024);
			if ($content === false) continue;
			$loc = ['t' => 'file', 'path' => $rp];
			$this->add($out, 'Berkas galley HTML', 'File #' . $r->submission_file_id . ' (submission ' . $r->submission_id . ')', $loc, $content);
		}
	}
}
