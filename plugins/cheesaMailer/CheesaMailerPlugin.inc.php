<?php

/**
 * @file plugins/generic/cheesaMailer/CheesaMailerPlugin.inc.php
 *
 * CHEESA Mailer — kirim email massal (Call for Papers, Call for Reviewer/Editor)
 * ke daftar email yang ditempel. Terkunci kata sandi, akses Manager/Editor,
 * kirim bertahap dengan pembatas, dan setiap pengiriman dicatat di log.
 *
 * Halaman: /index.php/<jurnal>/cheesamail
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class CheesaMailerPlugin extends GenericPlugin {

	const PAGE = 'cheesamail';

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('LoadHandler', [$this, 'loadPageHandler']);
			HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addBackendMenu']);
		}
		return $success;
	}

	public function getDisplayName() { return 'CHEESA Mailer'; }

	public function getDescription() {
		return 'Kirim email massal (Call for Papers, Call for Reviewer/Editor) ke daftar email yang ditempel. Terkunci kata sandi, akses Site Administrator dan Journal Manager/Editor, kirim bertahap dengan pembatas harian, uji-kirim ke email sendiri, dan setiap pengiriman dicatat.';
	}

	public function loadPageHandler($hookName, $params) {
		if ($params[0] !== self::PAGE) return false;
		require_once(__DIR__ . '/CheesaMailerHandler.inc.php');
		CheesaMailerHandler::$plugin = $this;
		define('HANDLER_CLASS', 'CheesaMailerHandler');
		return true;
	}

	public function pageUrl($request, $params = null) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE, null, null, $params);
	}

	public function actionUrl($request, $op) {
		return $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE, $op);
	}

	/** Menu "Email Massal" di sidebar dasbor (bila versi OJS menyediakan hook-nya). */
	public function addBackendMenu($hookName, $args) {
		try {
			$request = Application::get()->getRequest();
			$c = $request->getContext(); $u = $request->getUser();
			if (!$u || !(Validation::isSiteAdmin() || ($c && $u->hasRole([ROLE_ID_MANAGER], (int) $c->getId())))) return false;
			$tm = TemplateManager::getManager($request);
			$menu = $tm->getState('menu');
			if (!is_array($menu) || isset($menu['cheesaMail'])) return false;
			$item = ['name' => 'Email Massal', 'url' => $this->pageUrl($request), 'isCurrent' => $request->getRequestedPage() === self::PAGE];
			$new = [];
			foreach ($menu as $k => $v) { $new[$k] = $v; if ($k === 'announcements') $new['cheesaMail'] = $item; }
			if (!isset($new['cheesaMail'])) $new['cheesaMail'] = $item;
			$tm->setState(['menu' => $new]);
		} catch (\Throwable $e) {
			error_log('cheesaMailer menu: ' . $e->getMessage());
		}
		return false;
	}

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled() || !$request->getContext()) return $actions;
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift($actions, new LinkAction('cheesaMailPage', new RedirectAction($this->pageUrl($request)), 'Buka Email Massal', null));
		return $actions;
	}

	/* ---------------------------------------------------------------- pekerjaan kirim (job) */

	public function getJob($id) {
		if (!preg_match('/^[a-f0-9]{16}$/', (string) $id)) return null;
		$j = $this->getSetting(CONTEXT_SITE, 'job_' . $id);
		return is_array($j) ? $j : null;
	}

	public function saveJob($id, array $job) {
		$this->updateSetting(CONTEXT_SITE, 'job_' . $id, $job, 'object');
	}

	public function deleteJob($id) {
		if (preg_match('/^[a-f0-9]{16}$/', (string) $id)) $this->updateSetting(CONTEXT_SITE, 'job_' . $id, [], 'object');
	}

	/* ---------------------------------------------------------------- pembatas harian */

	/** Jumlah email terkirim hari ini (per situs). Bertambah lewat addSentToday(). */
	public function sentToday() {
		$d = $this->getSetting(CONTEXT_SITE, 'sentDay');
		$n = (int) $this->getSetting(CONTEXT_SITE, 'sentCount');
		return ($d === date('Y-m-d')) ? $n : 0;
	}

	public function addSentToday($n) {
		$today = date('Y-m-d');
		$cur = ($this->getSetting(CONTEXT_SITE, 'sentDay') === $today) ? (int) $this->getSetting(CONTEXT_SITE, 'sentCount') : 0;
		$this->updateSetting(CONTEXT_SITE, 'sentDay', $today, 'string');
		$this->updateSetting(CONTEXT_SITE, 'sentCount', $cur + (int) $n, 'int');
	}

	/* ---------------------------------------------------------------- audit */

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
		error_log('cheesaMailer: ' . ($me ? $me->getUsername() : '-') . ' ' . $action . ' — ' . $detail);
	}

	public function auditLog() {
		$log = $this->getSetting(CONTEXT_SITE, 'auditLog');
		return is_array($log) ? $log : [];
	}
}
