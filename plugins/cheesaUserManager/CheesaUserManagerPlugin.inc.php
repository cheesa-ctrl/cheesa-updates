<?php

/**
 * @file plugins/generic/cheesaUserManager/CheesaUserManagerPlugin.inc.php
 *
 * CHEESA User Manager — ubah username, hapus akun, dan gabungkan akun dari satu halaman.
 * Halaman: /index.php/<jurnal>/cheesausers — Site Administrator (semua akun) dan Journal Manager/Editor
 * (hanya akun yang terdaftar khusus di jurnalnya, karena akun berlaku untuk semua jurnal di server).
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class CheesaUserManagerPlugin extends GenericPlugin {

	const PAGE = 'cheesausers';

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('LoadHandler', [$this, 'loadPageHandler']);
			HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addBackendMenu']);
		}
		return $success;
	}

	public function getDisplayName() { return 'CHEESA User Manager'; }

	public function getDescription() {
		return 'Tambah akun, ubah username, masuk sebagai pengguna (login as — dicatat di log), hapus akun tanpa aktivitas, atau gabungkan akun ganda ke akun lain, serta impor pendaftaran dari Google Sheet. Untuk Site Administrator (semua akun) dan Journal Manager/Editor (akun jurnalnya sendiri); setiap tindakan dicatat.';
	}

	public function loadPageHandler($hookName, $params) {
		if ($params[0] !== self::PAGE) return false;
		require_once(__DIR__ . '/CheesaUserManagerHandler.inc.php');
		CheesaUserManagerHandler::$plugin = $this;
		define('HANDLER_CLASS', 'CheesaUserManagerHandler');
		return true;
	}

	public function pageUrl($request, $params = null) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE, null, null, $params);
	}

	/** Menu "Kelola Akun" di sidebar dasbor (bila versi OJS menyediakan hook-nya). */
	public function addBackendMenu($hookName, $args) {
		try {
			$request = Application::get()->getRequest();
			$c = $request->getContext(); $u = $request->getUser();
			if (!$c || !$u) return false;
			$allow = Validation::isSiteAdmin();
			if (!$allow) {
				$ug = DAORegistry::getDAO('UserGroupDAO')->getByUserId($u->getId(), (int) $c->getId());
				while ($ug && ($g = $ug->next())) { if (in_array((int) $g->getRoleId(), [ROLE_ID_MANAGER])) { $allow = true; break; } }
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
			$menu['cheesaPlugin']['submenu']['users'] = ['name' => 'User Manager', 'url' => $this->pageUrl($request), 'isCurrent' => $request->getRequestedPage() === self::PAGE];
			$tm->setState(['menu' => $menu]);
		} catch (\Throwable $e) {
			error_log('cheesaUserManager menu: ' . $e->getMessage());
		}
		return false;
	}

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled() || !$request->getContext()) return $actions;
		// tautan selalu tampil bila plugin aktif; halamannya sendiri yang memeriksa Site Administrator
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift($actions, new LinkAction('cheesaUsersPage', new RedirectAction($this->pageUrl($request)), 'Buka Kelola Akun', null));
		return $actions;
	}

	/** Catatan tindakan (100 terakhir), disimpan di pengaturan plugin tingkat situs. */
	public function audit($action, $detail, $request) {
		$log = $this->getSetting(CONTEXT_SITE, 'auditLog');
		if (!is_array($log)) $log = [];
		$me = $request->getUser();
		array_unshift($log, [
			'time' => date('Y-m-d H:i:s'),
			'by' => $me ? $me->getUsername() : '-',
			'ip' => $request->getRemoteAddr(),
			'ctx' => $request->getContext() ? (int) $request->getContext()->getId() : 0,
			'action' => $action,
			'detail' => $detail,
		]);
		$this->updateSetting(CONTEXT_SITE, 'auditLog', array_slice($log, 0, 100), 'object');
		error_log('cheesaUserManager: ' . ($me ? $me->getUsername() : '-') . ' ' . $action . ' — ' . $detail);
	}

	public function auditLog() {
		$log = $this->getSetting(CONTEXT_SITE, 'auditLog');
		return is_array($log) ? $log : [];
	}
}
