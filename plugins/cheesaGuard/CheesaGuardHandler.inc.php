<?php

/**
 * @file plugins/generic/cheesaGuard/CheesaGuardHandler.inc.php
 *
 * Halaman pindai keamanan. Read-only; menjalankan GuardScanner lalu melaporkan temuan,
 * menandai yang BARU sejak pindai terakhir (sinyal injeksi yang baru muncul).
 */

import('classes.handler.Handler');
use Illuminate\Database\Capsule\Manager as Capsule;

class CheesaGuardHandler extends Handler {

	/** @var CheesaGuardPlugin */
	static $plugin;

	function __construct() {
		parent::__construct();
		$this->addRoleAssignment([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], ['index', 'scan', 'fix', 'restore']);
	}

	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	private function json($data) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	function index($args, $request) {
		$this->setupTemplate($request);
		$plugin = self::$plugin;
		$tm = TemplateManager::getManager($request);
		$tm->assign([
			'pageTitle' => 'CHEESA Security Scan',
			'cgName' => $request->getContext() ? $request->getContext()->getLocalizedName() : 'CHEESA',
			'cgDash' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'submissions'),
			'cgScanUrl' => $plugin->actionUrl($request, 'scan'),
			'cgFixUrl' => $plugin->actionUrl($request, 'fix'),
			'cgRestoreUrl' => $plugin->actionUrl($request, 'restore'),
			'cgLog' => array_slice($plugin->auditLog(), 0, 10),
		]);
		return $tm->display($plugin->getTemplateResource('page.tpl'));
	}

	function scan($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		$plugin = self::$plugin;
		$context = $request->getContext();
		if (!$context) return $this->json(['error' => 'Konteks jurnal tidak ditemukan.']);
		$cid = (int) $context->getId();

		@set_time_limit(300);
		require_once(__DIR__ . '/classes/GuardScanner.inc.php');
		$scanner = new GuardScanner($request->getDispatcher()->url($request, ROUTE_PAGE, null, 'index'));
		try {
			$findings = $scanner->scan($cid);
		} catch (\Throwable $e) {
			return $this->json(['error' => 'Gagal memindai: ' . $e->getMessage()]);
		}

		// tandai yang BARU sejak pindai terakhir
		$baseline = $plugin->baseline($cid);
		$baseSet = array_fill_keys($baseline, 1);
		$now = [];
		$high = 0; $newCount = 0;
		foreach ($findings as &$f) {
			$f['isNew'] = !isset($baseSet[$f['fp']]);
			if ($f['isNew']) $newCount++;
			if ($f['severity'] === 'high') $high++;
			$now[] = $f['fp'];
		}
		unset($f);

		// urutkan: BARU dulu, lalu severity high dulu
		usort($findings, function ($a, $b) {
			if ($a['isNew'] !== $b['isNew']) return $a['isNew'] ? -1 : 1;
			$rank = ['high' => 0, 'medium' => 1];
			return ($rank[$a['severity']] ?? 2) <=> ($rank[$b['severity']] ?? 2);
		});

		$plugin->saveBaseline($cid, $now);

		// simpan peta locator (server-side, tepercaya) untuk aksi bersihkan/pulihkan
		$findings = array_slice($findings, 0, 500);
		$map = [];
		$backups = $plugin->backups($cid);
		foreach ($findings as &$f) {
			$map[$f['fp']] = ['locator' => $f['locator'], 'label' => $f['label'], 'area' => $f['area'], 'severity' => $f['severity']];
			$f['fixed'] = isset($backups[$f['fp']]);
			unset($f['locator']); // jangan kirim locator ke klien
		}
		unset($f);
		$plugin->saveScanMap($cid, $map);
		$plugin->audit('Scan: ' . count($findings) . ' temuan (' . $high . ' tinggi, ' . $newCount . ' baru).', $request);

		return $this->json([
			'total' => count($findings),
			'high' => $high,
			'new' => $newCount,
			'findings' => $findings,
			'time' => date('Y-m-d H:i'),
		]);
	}

	private function flushCache() {
		try { import('lib.pkp.classes.cache.CacheManager'); CacheManager::getManager()->flush(); } catch (\Throwable $e) {}
	}

	function fix($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		$plugin = self::$plugin;
		$context = $request->getContext();
		if (!$context) return $this->json(['error' => 'Konteks jurnal tidak ditemukan.']);
		$cid = (int) $context->getId();
		$fp = (string) $request->getUserVar('fp');
		$map = $plugin->scanMap($cid);
		if (!isset($map[$fp])) return $this->json(['error' => 'Temuan tidak ditemukan. Jalankan Scan lagi dulu.']);
		require_once(__DIR__ . '/classes/GuardScanner.inc.php');
		$loc = $map[$fp]['locator'];
		$allow = ['journal_settings', 'announcement_settings', 'navigation_menu_item_settings', 'publication_settings'];
		$backups = $plugin->backups($cid);
		try {
			if (($loc['t'] ?? '') === 'file') {
				$path = (string) ($loc['path'] ?? '');
				$base = realpath(Config::getVar('files', 'files_dir'));
				$rp = realpath($path);
				if (!$rp || !$base || strpos($rp, $base) !== 0 || !is_file($rp)) return $this->json(['error' => 'Berkas tidak ditemukan / di luar folder files.']);
				$orig = (string) file_get_contents($rp);
				$cleaned = GuardScanner::clean($orig);
				if ($cleaned === $orig) return $this->json(['nochange' => true, 'message' => 'Tidak ada bagian berbahaya yang bisa dibuang otomatis dari berkas ini. Periksa/hapus manual.']);
				@copy($rp, $rp . '.cheesaGuard.bak');
				file_put_contents($rp, $cleaned, LOCK_EX);
				$backups[$fp] = ['type' => 'file', 'path' => $rp];
			} elseif (in_array(($loc['t'] ?? ''), $allow, true) && ($loc['col'] ?? '') === 'setting_value' && !empty($loc['k'])) {
				$q = Capsule::table($loc['t']);
				foreach ($loc['k'] as $col => $val) $q->where($col, $val);
				$row = $q->first([$loc['col']]);
				if (!$row) return $this->json(['error' => 'Baris tidak ditemukan (mungkin sudah berubah). Scan lagi.']);
				$orig = (string) $row->{$loc['col']};
				$cleaned = GuardScanner::clean($orig);
				if ($cleaned === $orig) return $this->json(['nochange' => true, 'message' => 'Tidak ada bagian berbahaya yang bisa dibuang otomatis. Kalau seluruh teks ini spam, hapus manual lewat OJS.']);
				$backups[$fp] = ['type' => 'db', 't' => $loc['t'], 'col' => $loc['col'], 'k' => $loc['k'], 'orig' => $orig];
				$q2 = Capsule::table($loc['t']);
				foreach ($loc['k'] as $col => $val) $q2->where($col, $val);
				$q2->update([$loc['col'] => $cleaned]);
				$this->flushCache();
			} else {
				return $this->json(['error' => 'Jenis temuan ini belum bisa dibersihkan otomatis.']);
			}
			$plugin->saveBackups($cid, $backups);
			$plugin->audit('Bersihkan: ' . $map[$fp]['label'], $request);
			return $this->json(['ok' => true]);
		} catch (\Throwable $e) {
			return $this->json(['error' => 'Gagal membersihkan: ' . $e->getMessage()]);
		}
	}

	function restore($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		$plugin = self::$plugin;
		$context = $request->getContext();
		if (!$context) return $this->json(['error' => 'Konteks jurnal tidak ditemukan.']);
		$cid = (int) $context->getId();
		$fp = (string) $request->getUserVar('fp');
		$backups = $plugin->backups($cid);
		if (!isset($backups[$fp])) return $this->json(['error' => 'Tidak ada cadangan untuk temuan ini.']);
		$b = $backups[$fp];
		try {
			if (($b['type'] ?? '') === 'file') {
				$bak = $b['path'] . '.cheesaGuard.bak';
				if (is_file($bak)) copy($bak, $b['path']);
			} else {
				$q = Capsule::table($b['t']);
				foreach ($b['k'] as $col => $val) $q->where($col, $val);
				$q->update([$b['col'] => $b['orig']]);
				$this->flushCache();
			}
			unset($backups[$fp]);
			$plugin->saveBackups($cid, $backups);
			$plugin->audit('Pulihkan temuan: ' . $fp, $request);
			return $this->json(['ok' => true]);
		} catch (\Throwable $e) {
			return $this->json(['error' => 'Gagal memulihkan: ' . $e->getMessage()]);
		}
	}
}
