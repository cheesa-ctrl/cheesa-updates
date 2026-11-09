<?php

/**
 * @file plugins/generic/cheesaControlPanel/CheesaControlPanelPlugin.inc.php
 *
 * CHEESA Control Panel (OJS 3.3)
 * Memperbarui CHEESA Theme, Journal Widgets, Crossmark Button, dan panel ini sendiri langsung dari OJS,
 * tanpa mengunggah ulang plugin. Paket diambil dari manifest bertanda tangan digital (mis. di GitHub).
 *
 * Melihat daftar, memasang update / plugin baru, dan rollback: Site Admin serta Journal Manager / Journal Editor
 * jurnal ini. Hanya paket bertanda tangan kunci CHEESA yang bisa dipasang. Link pemulihan darurat: hanya Site Admin.
 * (karena mengganti kode di server).
 */

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(__DIR__ . '/classes/CheesaUpdater.inc.php');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class CheesaControlPanelPlugin extends GenericPlugin {

	/** Alamat update bawaan (repo GitHub cheesa-ctrl/cheesa-updates). Bisa diganti di panel. */
	const DEFAULT_MANIFEST = 'https://raw.githubusercontent.com/cheesa-ctrl/cheesa-updates/main/manifest.json';

	/** Paket yang dikenal panel (nama folder => kategori & label). */
	const KNOWN = [
		'cheesaNovelty' => ['themes', 'CHEESA Theme'],
		'journalWidgets' => ['generic', 'Journal Portal Widgets'],
		'crossmarkButton' => ['generic', 'Crossmark Button'],
		'cheesaControlPanel' => ['generic', 'CHEESA Control Panel'],
	];

	public function register($category, $path, $mainContextId = null) {
		return parent::register($category, $path, $mainContextId);
	}

	public function getDisplayName() { return 'CHEESA Control Panel'; }

	public function getDescription() {
		return 'Cek dan pasang update CHEESA Theme serta plugin pendukungnya langsung dari OJS (tanpa upload ulang). Paket diverifikasi dengan tanda tangan digital.' . CheesaLicense::notice(CheesaLicense::checkRequest(__DIR__));
	}

	/* ---------------- updater ---------------- */

	private function updater($token = '') {
		$plugins = Core::getBaseDir() . '/plugins';
		$files = rtrim(Config::getVar('files', 'files_dir'), '/\\');
		$key = @file_get_contents(__DIR__ . '/cheesa-public-key.pem');
		$fetch = function ($url) use ($token) {
			if (!preg_match('#^https://#i', $url)) throw new Exception('Alamat update harus https://');
			// manifest jangan diambil dari cache CDN
			if (preg_match('#\.(json|sig)$#', $url)) $url .= '?t=' . time();
			try {
				$opts = ['timeout' => 60, 'http_errors' => true];
				// repo GitHub privat: token hanya dikirim ke GitHub
				if ($token && preg_match('#^https://(raw\.githubusercontent\.com|api\.github\.com|github\.com)/#i', $url)) $opts['headers'] = ['Authorization' => 'token ' . $token];
				$res = Application::get()->getHttpClient()->request('GET', $url, $opts);
			} catch (\Throwable $e) {
				$clean = preg_replace('#\?t=\d+$#', '', $url);
				if (strpos($e->getMessage(), '404') !== false) {
					throw new Exception('Berkas belum ada di GitHub: ' . $clean . ' — unggah isi folder "2-unggah-ke-github" ke repo, tunggu ±5 menit, lalu klik Cek update.');
				}
				throw new Exception('Gagal mengunduh ' . $clean . ' — ' . $e->getMessage());
			}
			return (string) $res->getBody();
		};
		$u = new CheesaUpdater($plugins, $plugins . '/.cheesacp', $files . '/cheesaControlPanel/backups', $key, $fetch);
		$u->ojsInstaller = function ($newDir, $category, $name, $isNew) {
			// OJS 3.3: PluginHelper::installPlugin($path) / upgradePlugin($category, $plugin, $path) melempar Exception bila gagal,
			// menyalin folder ke plugins/, menjalankan install.xml / upgrade.xml, dan mencatat versi.
			import('lib.pkp.classes.plugins.PluginHelper');
			$helper = new PluginHelper();
			try {
				$isNew ? $helper->installPlugin($newDir) : $helper->upgradePlugin($category, $name, $newDir);
			} catch (\Throwable $e) {
				throw new Exception('Installer OJS: ' . $e->getMessage());
			}
		};
		return $u;
	}

	private function contextId($request) {
		$c = $request->getContext();
		return $c ? (int) $c->getId() : CONTEXT_SITE;
	}

	/** Daftar paket: versi terpasang, versi tersedia, status. */
	private function rows(CheesaUpdater $u, $manifest) {
		$rows = [];
		$names = array_unique(array_merge(array_keys(self::KNOWN), $manifest ? array_keys($manifest['packages']) : []));
		foreach ($names as $name) {
			$pkg = $manifest && isset($manifest['packages'][$name]) ? $manifest['packages'][$name] : null;
			$cat = $pkg ? $pkg['category'] : self::KNOWN[$name][0];
			$installed = $u->installedVersion($cat, $name);
			$available = $pkg ? $pkg['version'] : null;
			if (!$installed && !$available) continue;
			$rows[] = [
				'name' => $name,
				'label' => isset(self::KNOWN[$name]) ? self::KNOWN[$name][1] : ($pkg && !empty($pkg['label']) ? $pkg['label'] : $name),
				'category' => $cat,
				'installed' => $installed,
				'available' => $available,
				'released' => $pkg && isset($pkg['released']) ? $pkg['released'] : '',
				'canUpdate' => $available && CheesaUpdater::isNewer($available, $installed),
				'backups' => count($u->backups($name)),
			];
		}
		return $rows;
	}

	/** Status HTTP beranda jurnal (0 bila tidak bisa dicek, mis. server tidak boleh memanggil dirinya sendiri). */
	private function healthCheck($request) {
		try {
			$url = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'index');
			$res = Application::get()->getHttpClient()->request('GET', $url, ['timeout' => 30, 'http_errors' => false]);
			return (int) $res->getStatusCode();
		} catch (\Throwable $e) {
			return 0;
		}
	}

	/** Kode & link pemulihan darurat (recover.php), dibuat sekali dan disimpan di files_dir. */
	private function recoveryUrl($request) {
		$dir = rtrim(Config::getVar('files', 'files_dir'), '/\\') . '/cheesaControlPanel';
		$file = $dir . '/recovery.key';
		if (!is_file($file)) {
			if (!is_dir($dir)) @mkdir($dir, 0775, true);
			@file_put_contents($file, bin2hex(random_bytes(20)));
		}
		$key = is_file($file) ? trim(file_get_contents($file)) : '';
		if (strlen($key) < 32) return '';
		return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/recover.php?token=' . $key;
	}

	/** Catat versi baru di tabel versions OJS + bersihkan cache supaya perubahan langsung terlihat. */
	private function afterInstall($request, $category, $name, $registerVersion) {
		if ($registerVersion) try {
			import('lib.pkp.classes.site.VersionCheck');
			$info = VersionCheck::parseVersionXML(Core::getBaseDir() . "/plugins/$category/$name/version.xml");
			if ($info && isset($info['version'])) DAORegistry::getDAO('VersionDAO')->insertVersion($info['version'], true);
		} catch (\Throwable $e) {
			error_log('cheesaControlPanel version: ' . $e->getMessage());
		}
		try {
			$tm = TemplateManager::getManager($request);
			$tm->clearTemplateCache();
			$tm->clearCssCache();
		} catch (\Throwable $e) {}
		if (function_exists('opcache_reset')) @opcache_reset();
	}

	/* ---------------- tampilan pengaturan ---------------- */

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $request->getRouter();
		array_unshift($actions, new LinkAction('settings',
			new AjaxModal($router->url($request, null, null, 'manage', null, ['verb' => 'panel', 'plugin' => $this->getName(), 'category' => 'generic']), $this->getDisplayName()),
			'Buka Panel', null));
		return $actions;
	}

	public function manage($args, $request) {
		$verb = (string) $request->getUserVar('verb');
		if (!in_array($verb, ['panel', 'saveUrl', 'check', 'update', 'rollback'], true)) return parent::manage($args, $request);
		if ($why = CheesaLicense::checkRequest(__DIR__)) {
			return new JSONMessage(true, '<div style="padding:12px;border-left:4px solid #c62828;background:#fdecec">CHEESA Control Panel tidak aktif: ' . htmlspecialchars($why, ENT_QUOTES, 'UTF-8') . '. Plugin ini hanya untuk jurnal CHEESA.</div>');
		}

		$cid = $this->contextId($request);
		$siteAdmin = Validation::isSiteAdmin();
		$user = $request->getUser();
		// boleh memasang: Site Admin, atau Journal Manager / Journal Editor (peran manager) di jurnal ini
		$isAdmin = $siteAdmin || ($user && $cid && $user->hasRole([ROLE_ID_MANAGER], $cid));
		$msg = ''; $err = '';

		if ($verb !== 'panel') {
			if (!$request->checkCSRF()) return new JSONMessage(false, 'Sesi kedaluwarsa, muat ulang halaman.');
			if ($verb === 'saveUrl') {
				$url = trim((string) $request->getUserVar('manifestUrl'));
				if ($url !== '' && !preg_match('#^https://\S+/manifest\.json$#i', $url)) $err = 'Alamat harus https:// dan berakhiran /manifest.json';
				else {
					$tok = trim((string) $request->getUserVar('githubToken'));
					if ($tok === '-') $this->updateSetting($cid, 'githubToken', '', 'string');   // hapus token
					elseif ($tok !== '') $this->updateSetting($cid, 'githubToken', $tok, 'string');
					$this->updateSetting($cid, 'manifestUrl', $url === self::DEFAULT_MANIFEST ? '' : $url, 'string'); $msg = $url === '' ? 'Alamat update dikembalikan ke bawaan.' : 'Alamat update disimpan.'; }
			} elseif (!$isAdmin) {
				$err = 'Hanya Site Administrator atau Journal Manager/Editor jurnal ini yang boleh memasang update.';
			}
		}

		$u = $this->updater((string) $this->getSetting($cid, 'githubToken'));
		$url = (string) $this->getSetting($cid, 'manifestUrl');
		if ($url === '' || strpos($url, '/AKUN/') !== false) $url = self::DEFAULT_MANIFEST;   // abaikan contoh alamat lama
		$manifest = null;
		if ($url) {
			try { $manifest = $u->fetchManifest($url); }
			catch (\Throwable $e) { $err = $err ?: $e->getMessage(); }
		}

		$name = (string) $request->getUserVar('package');
		if (!$err && $isAdmin && $verb === 'update') {
			if (!$manifest || !isset($manifest['packages'][$name])) $err = 'Paket tidak ditemukan di manifest.';
			else {
				try {
					$pkg = $manifest['packages'][$name];
					$r = $u->install($name, $pkg);
					$this->afterInstall($request, $pkg['category'], $name, !$r['viaOjs']);
					// cek kesehatan: buka beranda jurnal; bila error server (5xx), kembalikan versi lama otomatis
					$code = $this->healthCheck($request);
					if ($code >= 500 && $r['backup']) {
						$back = $u->rollback($pkg['category'], $name);
						$this->afterInstall($request, $pkg['category'], $name, true);
						throw new Exception('setelah dipasang, beranda situs error (HTTP ' . $code . '), jadi versi ' . $back . ' dikembalikan otomatis. Situs aman.');
					}
					$label = isset(self::KNOWN[$name]) ? self::KNOWN[$name][1] : (!empty($pkg['label']) ? $pkg['label'] : $name);
					$msg = $r['new']
						? $label . ' ' . $r['to'] . ' terpasang. Aktifkan di tab Installed Plugins (kategori ' . $pkg['category'] . ').'
						: $label . ' diperbarui ' . $r['from'] . ' → ' . $r['to'] . '. Tekan Ctrl+F5 di situs untuk melihat perubahan.';
				} catch (\Throwable $e) { $err = 'Update gagal: ' . $e->getMessage(); }
			}
		} elseif (!$err && $isAdmin && $verb === 'rollback') {
			$cat = $manifest && isset($manifest['packages'][$name]) ? $manifest['packages'][$name]['category'] : (isset(self::KNOWN[$name]) ? self::KNOWN[$name][0] : '');
			if (!$cat) $err = 'Paket tidak dikenal.';
			else {
				try { $v = $u->rollback($cat, $name); $this->afterInstall($request, $cat, $name, true); $msg = 'Dikembalikan ke versi ' . $v . '.'; }
				catch (\Throwable $e) { $err = 'Rollback gagal: ' . $e->getMessage(); }
			}
		} elseif (!$err && $verb === 'check' && $manifest) {
			$msg = 'Daftar versi diperbarui.';
		}

		$problems = $isAdmin ? $u->preflight('generic') : [];
		$tm = TemplateManager::getManager($request);
		$tm->assign([
			'cpRows' => $this->rows($u, $manifest),
			'cpUrl' => $url,
			'cpHasToken' => (bool) $this->getSetting($cid, 'githubToken'),
			'cpMsg' => $msg,
			'cpErr' => $err,
			'cpAdmin' => $isAdmin,
			'cpProblems' => $problems,
			'cpRecovery' => $siteAdmin ? $this->recoveryUrl($request) : '',
			'cpGenerated' => $manifest && isset($manifest['generated']) ? $manifest['generated'] : '',
			'cpAction' => $request->getRouter()->url($request, null, null, 'manage', null, ['plugin' => $this->getName(), 'category' => 'generic']),
		]);
		return new JSONMessage(true, $tm->fetch($this->getTemplateResource('panel.tpl')));
	}
}
