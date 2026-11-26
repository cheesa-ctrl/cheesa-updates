<?php

/**
 * @file plugins/generic/cheesaGuard/CheesaGuardPlugin.inc.php
 *
 * CHEESA Security Scan — mendeteksi injeksi judi online (judol) / peretasan pada konten
 * situs jurnal (pengaturan, pengumuman, menu/halaman statis, metadata artikel, berkas HTML).
 * Hanya untuk Site Admin / Manager / Editor. Hanya membaca — tidak mengubah apa pun.
 *
 * Halaman: /index.php/<jurnal>/cheesaguard
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.config.Config');

class CheesaGuardPlugin extends GenericPlugin {

	const PAGE = 'cheesaguard';

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('LoadHandler', [$this, 'loadPageHandler']);
			HookRegistry::register('LoadHandler', [$this, 'loadLoginHandler']);
			HookRegistry::register('TemplateManager::display', [$this, 'loginCaptcha']);
			HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addBackendMenu']);
		}
		return $success;
	}

	public function getDisplayName() { return 'CHEESA Security Scan'; }

	public function getDescription() {
		return 'Scans live journal content (settings, announcements, navigation/static pages, article metadata and HTML galley files) for online-gambling (judol) spam and injection/hack signatures, lets an editor clean detected injections with one-click backup/restore, and protects the login form with a local math captcha plus brute-force throttling. Site Admin / Manager / Editor only.';
	}

	public function loadPageHandler($hookName, $params) {
		if ($params[0] !== self::PAGE) return false;
		require_once(__DIR__ . '/CheesaGuardHandler.inc.php');
		CheesaGuardHandler::$plugin = $this;
		define('HANDLER_CLASS', 'CheesaGuardHandler');
		return true;
	}

	/** Ambil alih POST login untuk cek captcha + throttle sebelum kredensial diproses. */
	public function loadLoginHandler($hookName, $params) {
		if (($params[0] ?? '') !== 'login' || ($params[1] ?? '') !== 'signIn') return false;
		// Gagal-aman: bila kelas kita tak bisa dimuat, jatuhkan ke login inti (jangan 500).
		try {
			import('lib.pkp.pages.login.LoginHandler');
			require_once(__DIR__ . '/CheesaLoginGuardHandler.inc.php');
			if (!class_exists('CheesaLoginGuardHandler')) return false;
			if (!defined('HANDLER_CLASS')) define('HANDLER_CLASS', 'CheesaLoginGuardHandler');
			return true;
		} catch (\Throwable $e) {
			error_log('cheesaGuard loadLoginHandler: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Sisipkan captcha matematika lokal ke halaman login (frontend/pages/userLogin.tpl).
	 * Jawaban disimpan di sesi; hanya diset bila penyisipan berhasil (gagal-aman).
	 */
	public function loginCaptcha($hookName, $args) {
		// Gagal-aman total: error apa pun di sini TIDAK boleh menjatuhkan halaman.
		try {
			$template = $args[1] ?? null;
			if ($template !== 'frontend/pages/userLogin.tpl') return false;

			$templateMgr = $args[0];
			$request = Application::get()->getRequest();
			$session = ($request && method_exists($request, 'getSession')) ? $request->getSession() : null;

			try { $a = random_int(1, 9); $b = random_int(1, 9); $op = random_int(0, 1); }
			catch (\Throwable $e) { $a = mt_rand(1, 9); $b = mt_rand(1, 9); $op = mt_rand(0, 1); }
			if ($op === 1) { // pengurangan: pastikan hasil tidak negatif
				if ($b > $a) { $tmp = $a; $a = $b; $b = $tmp; }
				$sym = '&minus;'; $ans = $a - $b;
			} else {
				$sym = '+'; $ans = $a + $b;
			}

			$msg = $session ? $session->getSessionVar('cheesaLoginMsg') : null;
			if ($msg && $session) $session->unsetSessionVar('cheesaLoginMsg');

			$html = $templateMgr->fetch($template);
			if (!is_string($html) || $html === '') return false; // biarkan render normal

			$err = $msg ? '<div style="color:#c62828;font-weight:600;margin-bottom:8px;">' . htmlspecialchars((string) $msg, ENT_QUOTES) . '</div>' : '';
			$block = '<div class="cheesaCaptcha" style="margin:14px 0;padding:12px 14px;border:1px solid #d7dcea;border-radius:8px;background:#f7f9fc;">'
				. $err
				. '<label style="display:block;font-weight:600;margin-bottom:6px;">Security check: what is <b>' . $a . ' ' . $sym . ' ' . $b . '</b>? <span class="required" aria-hidden="true">*</span></label>'
				. '<input type="text" name="cheesaCaptcha" inputmode="numeric" autocomplete="off" required aria-required="true" style="max-width:140px;padding:6px 8px;" placeholder="Answer" />'
				. '</div>'
				// Honeypot: kolom umpan tersembunyi; manusia tak pernah mengisinya, bot biasanya mengisi.
				. '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">'
				. '<label>Leave this field blank<input type="text" name="cheesa_website" tabindex="-1" autocomplete="off" value="" /></label>'
				. '</div>';

			$injected = false;
			if (strpos($html, '<div class="buttons">') !== false) {
				$html = str_replace('<div class="buttons">', $block . '<div class="buttons">', $html);
				$injected = true;
			} elseif (preg_match('#</form>#i', $html)) {
				$html = preg_replace('#</form>#i', $block . '</form>', $html, 1);
				$injected = true;
			}

			// Set jawaban HANYA bila captcha benar-benar tampil; jika tidak, captcha dilewati (gagal-aman).
			if ($session) {
				if ($injected) {
					$session->setSessionVar('cheesaCaptchaAns', (string) $ans);
					$session->setSessionVar('cheesaFormTime', (string) time()); // untuk time-trap
				} else {
					$session->unsetSessionVar('cheesaCaptchaAns');
					$session->unsetSessionVar('cheesaFormTime');
				}
			}

			if (!headers_sent()) header('Content-Type: text/html; charset=' . Config::getVar('i18n', 'client_charset', 'utf-8'));
			$output =& $args[2];
			$output = $html;
			return true;
		} catch (\Throwable $e) {
			error_log('cheesaGuard loginCaptcha: ' . $e->getMessage());
			return false; // render login normal tanpa captcha, situs tetap hidup
		}
	}

	public function pageUrl($request) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE);
	}

	public function actionUrl($request, $op) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE, $op);
	}

	/** Menu "Security Scan" di sidebar, di bawah grup "CHEESA Plugin". */
	public function addBackendMenu($hookName, $args) {
		try {
			$request = Application::get()->getRequest();
			$c = $request->getContext(); $u = $request->getUser();
			if (!$c || !$u) return false;
			$allow = Validation::isSiteAdmin();
			if (!$allow) {
				$ug = DAORegistry::getDAO('UserGroupDAO')->getByUserId($u->getId(), (int) $c->getId());
				while ($ug && ($g = $ug->next())) { if (in_array((int) $g->getRoleId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR])) { $allow = true; break; } }
			}
			if (!$allow) return false;
			$tm = TemplateManager::getManager($request);
			$menu = $tm->getState('menu');
			if (!is_array($menu)) return false;
			if (!isset($menu['cheesaPlugin'])) {
				$parent = ['name' => 'CHEESA Plugin', 'submenu' => []];
				$new = []; $placed = false;
				foreach ($menu as $k => $v) { $new[$k] = $v; if ($k === 'announcements') { $new['cheesaPlugin'] = $parent; $placed = true; } }
				if (!$placed) $new['cheesaPlugin'] = $parent;
				$menu = $new;
			}
			if (!isset($menu['cheesaPlugin']['submenu']) || !is_array($menu['cheesaPlugin']['submenu'])) $menu['cheesaPlugin']['submenu'] = [];
			$menu['cheesaPlugin']['submenu']['cheesaguard'] = ['name' => 'Security Scan', 'url' => $this->pageUrl($request), 'isCurrent' => $request->getRequestedPage() === self::PAGE];
			$tm->setState(['menu' => $menu]);
		} catch (\Throwable $e) {
			error_log('cheesaGuard menu: ' . $e->getMessage());
		}
		return false;
	}

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled() || !$request->getContext()) return $actions;
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift($actions, new LinkAction('cheesaGuardOpen', new RedirectAction($this->pageUrl($request)), 'Open Security Scan', null));
		return $actions;
	}

	/* ---------------------------------------------------------------- baseline & audit */

	public function baseline($cid) {
		$b = $this->getSetting(CONTEXT_SITE, 'baseline_' . (int) $cid);
		return is_array($b) ? $b : [];
	}

	public function saveBaseline($cid, array $fingerprints) {
		$this->updateSetting(CONTEXT_SITE, 'baseline_' . (int) $cid, array_values(array_unique($fingerprints)), 'object');
	}

	private function dir() {
		$d = Config::getVar('files', 'files_dir') . '/cheesaGuard';
		if (!is_dir($d)) @mkdir($d, 0750, true);
		return $d;
	}
	private function readJson($f) { return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : []; }
	public function scanMap($cid) { return $this->readJson($this->dir() . '/scanmap_' . (int) $cid . '.json'); }
	public function saveScanMap($cid, array $m) { file_put_contents($this->dir() . '/scanmap_' . (int) $cid . '.json', json_encode($m), LOCK_EX); }
	public function backups($cid) { return $this->readJson($this->dir() . '/backup_' . (int) $cid . '.json'); }
	public function saveBackups($cid, array $m) { file_put_contents($this->dir() . '/backup_' . (int) $cid . '.json', json_encode($m), LOCK_EX); }

	public function audit($detail, $request) {
		$log = $this->getSetting(CONTEXT_SITE, 'auditLog');
		if (!is_array($log)) $log = [];
		$u = $request->getUser();
		array_unshift($log, ['time' => date('Y-m-d H:i:s'), 'by' => $u ? $u->getUsername() : '-', 'detail' => $detail]);
		$this->updateSetting(CONTEXT_SITE, 'auditLog', array_slice($log, 0, 50), 'object');
	}

	public function auditLog() {
		$log = $this->getSetting(CONTEXT_SITE, 'auditLog');
		return is_array($log) ? $log : [];
	}
}
