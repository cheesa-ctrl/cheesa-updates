<?php

/**
 * @file plugins/blocks/cheesaEditorialPick/CheesaEditorialPickHandler.inc.php
 *
 * Halaman kelola CHEESA Editorial Pick: /index.php/<jurnal>/editorialpick
 * Hanya Site Admin / Journal Manager / Editor jurnal ini. Semua perubahan POST + CSRF.
 */

import('classes.handler.Handler');

class CheesaEditorialPickHandler extends Handler {

	/** @var CheesaEditorialPickPlugin */
	public static $plugin;

	const AVAILABLE_LIMIT = 200;

	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], ['index', 'save']);
	}

	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/* ------------------------------------------------------------------ halaman */

	public function index($args, $request) {
		$plugin = self::$plugin;
		$context = $request->getContext();
		$cid = (int) $context->getId();
		$tm = TemplateManager::getManager($request);

		if (!$plugin->canManage($request)) {
			$tm->assign($this->common($request) + ['epDenied' => true]);
			return $tm->display($plugin->getTemplateResource('manage.tpl'));
		}

		$q = trim((string) $request->getUserVar('q'));
		$picked = []; $available = []; $dataErr = '';
		try {
			$pickSet = array_flip($plugin->getPickIds($cid));
			foreach ($plugin->getPickedSubmissions($cid) as $s) $picked[] = $this->row($s, $cid, true);
			foreach ($this->publishedSubmissions($cid, $q) as $s) {
				if (isset($pickSet[(int) $s->getId()])) continue;
				$available[] = $this->row($s, $cid, false);
			}
		} catch (\Throwable $e) {
			error_log('cheesaEditorialPick index: ' . $e->getMessage());
			$dataErr = 'Sebagian data artikel gagal dimuat: ' . $e->getMessage();
		}

		$session = $request->getSession();
		$flash = $session->getSessionVar('cheesaEp');
		$session->unsetSessionVar('cheesaEp');
		$flash = $flash ? json_decode($flash, true) : null;

		$tm->assign($this->common($request) + [
			'epDenied' => false,
			'epPicked' => $picked,
			'epAvailable' => $available,
			'epAvailableLimit' => self::AVAILABLE_LIMIT,
			'epQ' => $q,
			'epShowCards' => $plugin->showCards($cid),
			'epSaveUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, CheesaEditorialPickPlugin::PAGE, 'save'),
			'epSearchUrl' => $plugin->pageUrl($request),
			'epFlash' => $flash,
			'epDataErr' => $dataErr,
		]);
		$tm->display($plugin->getTemplateResource('manage.tpl'));
	}

	/* ------------------------------------------------------------------ aksi */

	public function save($args, $request) {
		$plugin = self::$plugin;
		$cid = (int) $request->getContext()->getId();
		$ok = ''; $err = '';
		try {
			if (!$plugin->canManage($request)) throw new Exception('Hanya Site Admin atau Journal Manager/Editor yang boleh mengelola.');
			if (!$request->isPost() || !$request->checkCSRF()) throw new Exception('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
			$verb = (string) $request->getUserVar('verb');
			$ids = $plugin->getPickIds($cid);

			if ($verb === 'add') {
				$id = (int) $request->getUserVar('id');
				if (!$this->isEligible($id, $cid)) throw new Exception('Artikel tidak valid atau belum terbit di jurnal ini.');
				if (!in_array($id, $ids, true)) { $ids[] = $id; $plugin->savePickIds($ids, $cid); }
				$ok = 'Artikel ditambahkan ke pilihan.';

			} elseif ($verb === 'remove') {
				$id = (int) $request->getUserVar('id');
				$ids = array_values(array_filter($ids, function ($x) use ($id) { return (int) $x !== $id; }));
				$plugin->savePickIds($ids, $cid);
				$ok = 'Artikel dihapus dari pilihan.';

			} elseif ($verb === 'up' || $verb === 'down') {
				$id = (int) $request->getUserVar('id');
				$pos = array_search($id, $ids, true);
				if ($pos !== false) {
					$swap = $verb === 'up' ? $pos - 1 : $pos + 1;
					if ($swap >= 0 && $swap < count($ids)) {
						$tmp = $ids[$swap]; $ids[$swap] = $ids[$pos]; $ids[$pos] = $tmp;
						$plugin->savePickIds($ids, $cid);
					}
				}
				$ok = 'Urutan diperbarui.';

			} elseif ($verb === 'cards') {
				$plugin->setShowCards((bool) $request->getUserVar('on'), $cid);
				$ok = 'Pengaturan tampilan disimpan.';

			} elseif ($verb === 'clear') {
				$plugin->savePickIds([], $cid);
				$ok = 'Semua pilihan dikosongkan.';

			} else {
				throw new Exception('Aksi tidak dikenal.');
			}
		} catch (\Throwable $e) {
			$err = $e->getMessage();
		}
		$request->getSession()->setSessionVar('cheesaEp', json_encode(['ok' => $ok, 'err' => $err]));
		$back = array_filter(['q' => (string) $request->getUserVar('q')], 'strlen');
		$request->redirectUrl($plugin->pageUrl($request, $back ?: null));
	}

	/* ------------------------------------------------------------------ bantuan */

	private function common($request) {
		$d = $request->getDispatcher();
		return [
			'epJournal' => $request->getContext() ? $request->getContext()->getLocalizedName() : '',
			'epDashUrl' => $d->url($request, ROUTE_PAGE, null, 'submissions'),
			'epHomeUrl' => $d->url($request, ROUTE_PAGE, null, 'index'),
			'epUserName' => $request->getUser() ? $request->getUser()->getFullName() : '',
		];
	}

	/** Artikel layak dipilih: ada, milik jurnal ini, dan sudah terbit. */
	private function isEligible($id, $cid) {
		if ($id <= 0) return false;
		$s = DAORegistry::getDAO('SubmissionDAO')->getById($id);
		if (!$s || (int) $s->getData('contextId') !== $cid) return false;
		if (!$s->getCurrentPublication()) return false;
		return (int) $s->getData('status') === (defined('STATUS_PUBLISHED') ? STATUS_PUBLISHED : 3);
	}

	/** @return Submission[] artikel terbit di jurnal ini (opsional difilter judul) */
	private function publishedSubmissions($cid, $q = '') {
		$base = [
			'contextId' => $cid,
			'status' => defined('STATUS_PUBLISHED') ? STATUS_PUBLISHED : 3,
			'count' => self::AVAILABLE_LIMIT,
		];
		if ($q !== '') $base['searchPhrase'] = $q;
		// beberapa versi OJS 3.x berbeda dukungan orderBy; coba dengan urutan tanggal, lalu tanpa
		foreach ([['orderBy' => 'datePublished', 'orderDirection' => 'DESC'], []] as $extra) {
			try {
				$result = Services::get('submission')->getMany($base + $extra);
				$out = [];
				foreach ($result as $s) $out[] = $s;
				return $out;
			} catch (\Throwable $e) {
				error_log('cheesaEditorialPick list: ' . $e->getMessage());
			}
		}
		return [];
	}

	/** Satu baris tampilan (aman di-escape di template). */
	private function row($submission, $cid, $picked) {
		$pub = $submission->getCurrentPublication();
		$authors = [];
		foreach ((array) $pub->getData('authors') as $a) $authors[] = $a->getFullName();
		$issueId = (int) $pub->getData('issueId');
		$issue = $issueId ? DAORegistry::getDAO('IssueDAO')->getById($issueId) : null;
		$date = $pub->getData('datePublished');
		return [
			'id' => (int) $submission->getId(),
			'title' => (string) $pub->getLocalizedData('title'),
			'authors' => $authors ? ($authors[0] . (count($authors) > 1 ? ' dkk.' : '')) : '',
			'date' => $date ? substr((string) $date, 0, 10) : '',
			'issue' => $issue ? $issue->getIssueIdentification() : '',
			'cover' => (string) $pub->getLocalizedCoverImageUrl($cid),
			'picked' => (bool) $picked,
		];
	}
}
