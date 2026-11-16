<?php

/**
 * @file plugins/generic/cheesaControlPanel/CheesaControlPanelPlugin.inc.php
 *
 * CHEESA Control Panel (OJS 3.3)
 * Memperbarui CHEESA Theme, Journal Widgets, Crossmark Button, dan panel ini sendiri langsung dari OJS,
 * tanpa mengunggah ulang plugin. Paket diambil dari manifest bertanda tangan digital (mis. di GitHub).
 *
 * Melihat daftar, memasang update / plugin baru, menghapus plugin, dan rollback: Site Admin serta Journal Manager / Journal Editor
 * jurnal ini. Hanya paket bertanda tangan kunci CHEESA yang bisa dipasang. Link pemulihan darurat: hanya Site Admin.
 * (karena mengganti kode di server).
 */

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(__DIR__ . '/classes/CheesaUpdater.inc.php');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class CheesaControlPanelPlugin extends GenericPlugin {

	/** Alamat update bawaan (repo GitHub cheesa-ctrl/cheesa-updates). Bisa diganti di panel. */
	/** Hash bcrypt kata sandi bagian "Sumber update" (kata sandi tidak disimpan dalam bentuk asli). */
	const SOURCE_PASSWORD_HASH = '$2y$11$M8iWNpT0ZdgnRs8HNls4U.r9KHaqX3l8IlVkVY4BZsAIJ5zAb6Zz2';

	const DEFAULT_MANIFEST = 'https://raw.githubusercontent.com/cheesa-ctrl/cheesa-updates/main/manifest.json';

	/** Paket yang dikenal panel (nama folder => kategori & label). */
	const KNOWN = [
		'cheesaNovelty' => ['themes', 'CHEESA Theme'],
		'journalWidgets' => ['generic', 'Journal Portal Widgets'],
		'crossmarkButton' => ['generic', 'Crossmark Button'],
		'cheesaControlPanel' => ['generic', 'CHEESA Control Panel'],
	];

	/** Nama halaman penuh panel: /index.php/<jurnal>/cheesapanel */
	const PAGE = 'cheesapanel';

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('LoadHandler', [$this, 'loadPageHandler']);
			HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addBackendMenu']);
		}
		return $success;
	}

	/** Halaman penuh panel (bukan modal). Hak akses diperiksa di CheesaPanelHandler. */
	public function loadPageHandler($hookName, $params) {
		if ($params[0] !== self::PAGE) return false;
		require_once(__DIR__ . '/CheesaPanelHandler.inc.php');
		CheesaPanelHandler::$plugin = $this;
		define('HANDLER_CLASS', 'CheesaPanelHandler');
		return true;
	}

	/** Alamat halaman penuh panel di jurnal yang sedang dibuka. */
	public function pageUrl($request) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE);
	}

	/** Alamat aksi panel (dipakai modal dan halaman penuh). */
	public function manageUrl($request) {
		return $request->getDispatcher()->url($request, ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'manage', null,
			['plugin' => $this->getName(), 'category' => 'generic']);
	}

	/** Boleh membuka panel: Site Admin, atau Journal Manager / Journal Editor jurnal ini. */
	public function canUse($request) {
		$user = $request->getUser(); $c = $request->getContext();
		return $user && (Validation::isSiteAdmin() || ($c && $user->hasRole([ROLE_ID_MANAGER], (int) $c->getId())));
	}

	/** Menu "CHEESA Panel" di sidebar dasbor (bila versi OJS menyediakan hook-nya). */
	public function addBackendMenu($hookName, $args) {
		try {
			$request = Application::get()->getRequest();
			if (!$request->getContext() || !$this->canUse($request)) return false;
			$tm = TemplateManager::getManager($request);
			$menu = $tm->getState('menu');
			if (!is_array($menu) || isset($menu['cheesaPanel'])) return false;
			$item = ['name' => 'CHEESA Panel', 'url' => $this->pageUrl($request), 'isCurrent' => $request->getRequestedPage() === self::PAGE];
			$new = [];
			foreach ($menu as $k => $v) { $new[$k] = $v; if ($k === 'settings') $new['cheesaPanel'] = $item; }
			if (!isset($new['cheesaPanel'])) $new['cheesaPanel'] = $item;
			$tm->setState(['menu' => $new]);
		} catch (\Throwable $e) {
			error_log('cheesaControlPanel menu: ' . $e->getMessage());
		}
		return false;
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
		$names = array_unique(array_merge(array_keys(self::KNOWN), $manifest ? array_keys($manifest['packages']) : [], $u->backupNames()));
		foreach ($names as $name) {
			$pkg = $manifest && isset($manifest['packages'][$name]) ? $manifest['packages'][$name] : null;
			$cat = $this->categoryOf($u, $manifest, $name);
			if (!$cat) continue;
			$installed = $u->installedVersion($cat, $name);
			$available = $pkg ? $pkg['version'] : null;
			$backups = count($u->backups($name));
			// paket yang hanya punya cadangan (sudah dihapus) tetap tampil, supaya bisa dikembalikan
			if (!$installed && !$available && !$backups) continue;
			if (!$pkg && !isset(self::KNOWN[$name]) && !$installed && !$backups) continue;
			$rows[] = [
				'name' => $name,
				'label' => isset(self::KNOWN[$name]) ? self::KNOWN[$name][1] : ($pkg && !empty($pkg['label']) ? $pkg['label'] : $name),
				'category' => $cat,
				'installed' => $installed,
				'available' => $available,
				'released' => $pkg && isset($pkg['released']) ? $pkg['released'] : '',
				'canUpdate' => $available && CheesaUpdater::isNewer($available, $installed),
				'backups' => $backups,
				'canDelete' => $installed && $name !== 'cheesaControlPanel',
				'canHide' => $name !== 'cheesaControlPanel',
				'inManifest' => (bool) $pkg,
			];
		}
		return $rows;
	}

	/** @return array nama paket => tanggal disembunyikan */
	private function hiddenList($cid) {
		$h = $this->getSetting($cid, 'hiddenPackages');
		return is_array($h) ? $h : [];
	}

	/** Kategori paket: dari manifest, daftar bawaan, atau cadangan (bila plugin sudah dihapus). */
	private function categoryOf(CheesaUpdater $u, $manifest, $name) {
		if ($manifest && isset($manifest['packages'][$name])) return $manifest['packages'][$name]['category'];
		if (isset(self::KNOWN[$name])) return self::KNOWN[$name][0];
		return $u->backupCategory($name);
	}

	/** @return string[] nama jurnal (selain jurnal $exceptId) yang memakai tema $name; 'situs' bila dipakai halaman situs */
	private function themeUsers($name, $exceptId) {
		$who = [];
		$site = Application::get()->getRequest()->getSite();
		if ($site && $site->getData('themePluginPath') === $name) $who[] = 'halaman situs (Administration > Site Settings)';
		$res = Application::getContextDAO()->getAll();
		while ($c = $res->next()) {
			if ($c->getData('themePluginPath') === $name) $who[] = $c->getLocalizedName() . ((int) $c->getId() === (int) $exceptId ? ' (jurnal ini)' : '');
		}
		return $who;
	}

	/**
	 * Plugin dimuat untuk membaca/mengubah status "enabled".
	 * @return array [plugin|null, string[] nama jurnal lain yang masih mengaktifkannya]
	 */
	private function pluginUsage($category, $name, $cid) {
		$plugin = PluginRegistry::loadPlugin($category, $name);
		$others = [];
		if ($plugin) {
			$res = Application::getContextDAO()->getAll();
			while ($c = $res->next()) {
				if ((int) $c->getId() !== (int) $cid && $plugin->getSetting((int) $c->getId(), 'enabled')) $others[] = $c->getLocalizedName();
			}
		}
		return [$plugin, $others];
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
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		$router = $request->getRouter();
		array_unshift($actions, new LinkAction('settings',
			new AjaxModal($router->url($request, null, null, 'manage', null, ['verb' => 'panel', 'plugin' => $this->getName(), 'category' => 'generic']), $this->getDisplayName()),
			'Buka Panel', null));
		if ($request->getContext()) {
			array_splice($actions, 1, 0, [new LinkAction('cheesaPanelPage', new RedirectAction($this->pageUrl($request)), 'Halaman Penuh', null)]);
		}
		return $actions;
	}

	public function manage($args, $request) {
		$verb = (string) $request->getUserVar('verb');
		if (!in_array($verb, ['panel', 'saveUrl', 'check', 'update', 'rollback', 'uninstall', 'hide', 'unhide', 'unlockSource', 'lockSource'], true)) return parent::manage($args, $request);
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
			$session = $request->getSession();
			if ($verb === 'unlockSource') {
				// maks. 5 percobaan salah per 10 menit per sesi
				$tries = json_decode((string) $session->getSessionVar('cheesaCPSourceTries'), true) ?: ['n' => 0, 't' => time()];
				if (time() - $tries['t'] > 600) $tries = ['n' => 0, 't' => time()];
				if ($tries['n'] >= 5) $err = 'Terlalu banyak percobaan salah. Coba lagi 10 menit lagi.';
				// spasi di awal/akhir dan huruf kapital (mis. kapital otomatis di HP) diabaikan
				elseif (password_verify(strtolower(trim((string) $request->getUserVar('password'))), self::SOURCE_PASSWORD_HASH)) {
					$session->setSessionVar('cheesaCPSource', '1');
					$session->unsetSessionVar('cheesaCPSourceTries');
					$msg = 'Bagian Sumber update dibuka.';
				} else {
					usleep(700000);
					$tries['n']++;
					$session->setSessionVar('cheesaCPSourceTries', json_encode($tries));
					$typed = trim((string) $request->getUserVar('password'));
					$err = ($typed === '' ? 'Kata sandi belum diisi.' : 'Kata sandi salah (' . strlen($typed) . ' karakter terkirim).')
						. ($tries['n'] >= 5 ? ' Terkunci 10 menit.' : ' Sisa ' . (5 - $tries['n']) . ' percobaan.');
				}
			} elseif ($verb === 'lockSource') {
				$session->unsetSessionVar('cheesaCPSource');
				$msg = 'Bagian Sumber update dikunci lagi.';
			} elseif ($verb === 'saveUrl' && $session->getSessionVar('cheesaCPSource') !== '1') {
				$err = 'Bagian Sumber update terkunci. Masukkan kata sandi dulu.';
			} elseif ($verb === 'saveUrl') {
				$url = trim((string) $request->getUserVar('manifestUrl'));
				if ($url !== '' && !preg_match('#^https://\S+/manifest\.json$#i', $url)) $err = 'Alamat harus https:// dan berakhiran /manifest.json';
				else {
					$tok = trim((string) $request->getUserVar('githubToken'));
					if ($tok === '-') $this->updateSetting($cid, 'githubToken', '', 'string');   // hapus token
					elseif ($tok !== '') $this->updateSetting($cid, 'githubToken', $tok, 'string');
					$this->updateSetting($cid, 'manifestUrl', $url === self::DEFAULT_MANIFEST ? '' : $url, 'string'); $msg = $url === '' ? 'Alamat update dikembalikan ke bawaan.' : 'Alamat update disimpan.'; }
			} elseif (!$isAdmin) {
				$err = 'Hanya Site Administrator atau Journal Manager/Editor jurnal ini yang boleh memasang update.';
			} elseif ($verb === 'hide' || $verb === 'unhide') {
				// "Hapus" = hanya menghapus baris dari daftar panel ini; plugin di server tidak diubah
				$pkgName = (string) $request->getUserVar('package');
				if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $pkgName) || $pkgName === 'cheesaControlPanel') $err = 'Paket tidak valid.';
				else {
					$hidden = $this->hiddenList($cid);
					if ($verb === 'hide') { $hidden[$pkgName] = date('Y-m-d'); $msg = $pkgName . ' dihapus dari daftar. Plugin di server tidak diubah; tampilkan lagi lewat "Disembunyikan" di bawah.'; }
					else { unset($hidden[$pkgName]); $msg = $pkgName . ' ditampilkan lagi di daftar.'; }
					$this->updateSetting($cid, 'hiddenPackages', $hidden, 'object');
				}
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
			$cat = preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name) ? $this->categoryOf($u, $manifest, $name) : null;
			if (!$cat) $err = 'Paket tidak dikenal.';
			else {
				try {
					$wasInstalled = (bool) $u->installedVersion($cat, $name);
					$v = $u->rollback($cat, $name); $this->afterInstall($request, $cat, $name, true);
					$msg = 'Dikembalikan ke versi ' . $v . '.' . ($wasInstalled ? '' : ' Plugin terpasang lagi; aktifkan di tab Installed Plugins.');
				}
				catch (\Throwable $e) { $err = 'Rollback gagal: ' . $e->getMessage(); }
			}
		} elseif (!$err && $isAdmin && $verb === 'uninstall') {
			$cat = preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name) ? $this->categoryOf($u, $manifest, $name) : null;
			$label = isset(self::KNOWN[$name]) ? self::KNOWN[$name][1] : ($manifest && !empty($manifest['packages'][$name]['label']) ? $manifest['packages'][$name]['label'] : $name);
			if (!$cat || !$u->installedVersion($cat, $name)) $err = 'Paket tidak dikenal atau tidak terpasang.';
			elseif ($name === 'cheesaControlPanel') $err = 'CHEESA Control Panel tidak bisa menghapus dirinya sendiri. Hapus lewat Settings > Website > Plugins (login sebagai Site Administrator).';
			else {
				try {
					if ($cat === 'themes' && ($who = $this->themeUsers($name, $cid))) {
						throw new Exception('tema ini masih dipakai oleh: ' . implode(', ', $who) . '. Ganti tema di Appearance > Theme lebih dulu.');
					}
					list($plugin, $others) = $this->pluginUsage($cat, $name, $cid);
					if ($others) {
						throw new Exception('plugin ini masih aktif di jurnal lain: ' . implode(', ', $others) . '. Plugin dipakai bersama satu server, jadi menghapusnya akan ikut menghapus dari jurnal tersebut. Nonaktifkan dulu di jurnal itu.');
					}
					// matikan di jurnal ini & tingkat situs, supaya tidak ada status "aktif" yang tertinggal
					if ($plugin) foreach (array_unique([(int) $cid, CONTEXT_SITE]) as $id) {
						if ($plugin->getSetting($id, 'enabled')) $plugin->updateSetting($id, 'enabled', false, 'bool');
					}
					$r = $u->uninstall($cat, $name);
					$versionDao = DAORegistry::getDAO('VersionDAO');
					if (method_exists($versionDao, 'disableVersion')) $versionDao->disableVersion('plugins.' . $cat, $name);
					$this->afterInstall($request, $cat, $name, false);
					$code = $this->healthCheck($request);
					if ($code >= 500) {
						$u->rollback($cat, $name);
						$this->afterInstall($request, $cat, $name, true);
						throw new Exception('setelah dihapus, beranda situs error (HTTP ' . $code . '), jadi plugin dikembalikan otomatis. Situs aman.');
					}
					$msg = $label . ' ' . $r['from'] . ' di-uninstall dari server. Cadangannya disimpan: klik "Kembalikan" bila ingin memasangnya lagi, atau "Hapus" untuk menghilangkannya dari daftar. Pengaturan dan data plugin di database tidak ikut dihapus.';
				} catch (\Throwable $e) { $err = 'Uninstall gagal: ' . $e->getMessage(); }
			}
		} elseif (!$err && $verb === 'check' && $manifest) {
			$msg = 'Daftar versi diperbarui.';
		}

		$problems = $isAdmin ? $u->preflight('generic') : [];
		$allRows = $this->rows($u, $manifest);
		$hidden = $this->hiddenList($cid);
		$rows = []; $hiddenRows = [];
		foreach ($allRows as $r) { if (isset($hidden[$r['name']])) $hiddenRows[] = $r; else $rows[] = $r; }
		$tm = TemplateManager::getManager($request);
		$tm->assign([
			'cpRows' => $rows,
			'cpHiddenRows' => $hiddenRows,
			'cpCountInstalled' => count(array_filter($rows, function ($r) { return (bool) $r['installed']; })),
			'cpCountUpdates' => count(array_filter($rows, function ($r) { return (bool) $r['canUpdate']; })),
			'cpFullPage' => $request->getUserVar('fullpage') === '1',
			'cpPageUrl' => $request->getContext() ? $this->pageUrl($request) : '',
			'cpUrl' => $url,
			'cpHasToken' => (bool) $this->getSetting($cid, 'githubToken'),
			'cpSourceUnlocked' => $request->getSession()->getSessionVar('cheesaCPSource') === '1',
			'cpSourceOpen' => in_array($verb, ['unlockSource', 'lockSource', 'saveUrl'], true),
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
