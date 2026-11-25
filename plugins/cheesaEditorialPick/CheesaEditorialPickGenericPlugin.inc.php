<?php

/**
 * @file plugins/generic/cheesaEditorialPick/CheesaEditorialPickGenericPlugin.inc.php
 *
 * CHEESA Editorial Pick — menampilkan artikel pilihan editor sebagai baris kartu
 * ber-cover di beranda. Dibuat khusus untuk CHEESA, tanpa lisensi.
 *
 * Plugin GENERIC: OJS memuat kategori generic sebelum routing, sehingga hook
 * LoadHandler (halaman kelola) terdaftar tepat waktu — berbeda dengan block plugin
 * yang dimuat belakangan dan menyebabkan halaman kelola 404.
 *
 * Pilihan artikel disimpan di pengaturan plugin ('articles' = daftar id submission, urut).
 * Halaman kelola: /index.php/<jurnal>/cheesapick (Manager / Editor jurnal).
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class CheesaEditorialPickGenericPlugin extends GenericPlugin {

	const PAGE = 'cheesapick';

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			// halaman kelola
			HookRegistry::register('LoadHandler', [$this, 'loadPageHandler']);
			// baris kartu di beranda
			HookRegistry::register('Templates::Index::journal', [$this, 'addHomepageCards']);
			// tautan "Kelola" di menu plugin dasbor, bila versi OJS menyediakan hook-nya
			HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addBackendMenu']);
		}
		return $success;
	}

	public function getDisplayName() { return 'CHEESA Editorial Pick'; }
	public function getDescription() {
		return 'Menampilkan artikel pilihan editor sebagai blok sidebar dan baris kartu di beranda. Khusus jurnal CHEESA, tanpa lisensi.';
	}

	/* ------------------------------------------------------------------ data pilihan */

	/** @return int id jurnal yang sedang aktif */
	private function contextId() {
		$context = Application::get()->getRequest()->getContext();
		return $context ? (int) $context->getId() : (int) CONTEXT_SITE;
	}

	/** @return int[] daftar id submission pilihan (urut) */
	public function getPickIds($contextId = null) {
		$contextId = $contextId ?? $this->contextId();
		$ids = $this->getSetting($contextId, 'articles');
		if (!is_array($ids)) return [];
		return array_values(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; }));
	}

	public function savePickIds(array $ids, $contextId = null) {
		$contextId = $contextId ?? $this->contextId();
		$clean = [];
		foreach ($ids as $id) { $id = (int) $id; if ($id > 0 && !in_array($id, $clean, true)) $clean[] = $id; }
		$this->updateSetting($contextId, 'articles', array_values($clean), 'object');
	}

	public function showCards($contextId = null) {
		$contextId = $contextId ?? $this->contextId();
		return (bool) $this->getSetting($contextId, 'displayCards');
	}

	public function setShowCards($on, $contextId = null) {
		$contextId = $contextId ?? $this->contextId();
		$this->updateSetting($contextId, 'displayCards', $on ? 1 : 0, 'bool');
	}

	/**
	 * Submission objek untuk id pilihan, hanya yang MASIH ADA, milik jurnal ini, dan sudah terbit.
	 * Urutannya mengikuti urutan pilihan.
	 * @return Submission[]
	 */
	public function getPickedSubmissions($contextId = null) {
		$contextId = $contextId ?? $this->contextId();
		$ids = $this->getPickIds($contextId);
		if (!$ids) return [];
		$dao = DAORegistry::getDAO('SubmissionDAO');
		$out = [];
		foreach ($ids as $id) {
			$submission = $dao->getById($id);
			if (!$submission) continue;
			if ((int) $submission->getData('contextId') !== $contextId) continue;
			$pub = $submission->getCurrentPublication();
			if (!$pub) continue;
			$status = (int) $submission->getData('status');
			if ($status !== (defined('STATUS_PUBLISHED') ? STATUS_PUBLISHED : 3)) continue;
			$out[] = $submission;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ baris kartu beranda */

	public function addHomepageCards($hookName, $args) {
		$output =& $args[2];
		try {
			if (!$this->showCards()) return false;
			$submissions = $this->getPickedSubmissions();
			if (!$submissions) return false;
			$request = Application::get()->getRequest();
			$tm = TemplateManager::getManager($request);
			$tm->assign('editorialPick', $submissions);
			$tm->assign('issueDao', DAORegistry::getDAO('IssueDAO'));
			$output .= $tm->fetch($this->getTemplateResource('cards.tpl'));
		} catch (\Throwable $e) {
			error_log('cheesaEditorialPick cards: ' . $e->getMessage());
		}
		return false;
	}

	/* ------------------------------------------------------------------ halaman kelola */

	public function loadPageHandler($hookName, $params) {
		if ($params[0] !== self::PAGE) return false;
		$this->import('CheesaEditorialPickGenericHandler');
		CheesaEditorialPickGenericHandler::$plugin = $this;
		define('HANDLER_CLASS', 'CheesaEditorialPickGenericHandler');
		return true;
	}

	public function pageUrl($request, $params = null) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE, null, null, $params);
	}

	/** Boleh mengelola: Site Admin, atau Journal Manager / Editor (sub editor) jurnal ini. */
	public function canManage($request) {
		$user = $request->getUser(); $c = $request->getContext();
		if (!$user || !$c) return false;
		if (Validation::isSiteAdmin()) return true;
		// cek peran langsung ke user-group (andal di OJS 3.3), bukan $user->hasRole() yang bisa meleset
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$groups = $userGroupDao->getByUserId($user->getId(), (int) $c->getId());
		while ($groups && ($g = $groups->next())) {
			if (in_array((int) $g->getRoleId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR])) return true;
		}
		return false;
	}

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled() || !$request->getContext() || !$this->canManage($request)) return $actions;
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift($actions, new LinkAction('cheesaEpManage', new RedirectAction($this->pageUrl($request)), 'Kelola Pilihan', null));
		return $actions;
	}

	/** Menu "Editorial Pick" di sidebar dasbor (bila versi OJS menyediakan hook-nya). */
	public function addBackendMenu($hookName, $args) {
		try {
			$request = Application::get()->getRequest();
			if (!$request->getContext() || !$this->canManage($request)) return false;
			$tm = TemplateManager::getManager($request);
			$menu = $tm->getState('menu');
			if (!is_array($menu) || isset($menu['cheesaEditorialPick'])) return false;
			$item = ['name' => 'Editorial Pick', 'url' => $this->pageUrl($request), 'isCurrent' => $request->getRequestedPage() === self::PAGE];
			$new = [];
			foreach ($menu as $k => $v) { $new[$k] = $v; if ($k === 'settings') $new['cheesaEditorialPick'] = $item; }
			if (!isset($new['cheesaEditorialPick'])) $new['cheesaEditorialPick'] = $item;
			$tm->setState(['menu' => $new]);
		} catch (\Throwable $e) {
			error_log('cheesaEditorialPick menu: ' . $e->getMessage());
		}
		return false;
	}
}
