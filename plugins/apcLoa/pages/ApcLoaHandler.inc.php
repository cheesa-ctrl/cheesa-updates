<?php

/**
 * @file plugins/generic/apcLoa/pages/ApcLoaHandler.inc.php
 *
 * @class ApcLoaHandler
 * @brief Pages under /apcloa:
 *   index                 author page "APC & LoA"
 *   document/{type}/{tk}  printable LoA / invoice / receipt
 *   preview/{type}        sample document (managers)
 *   verify/{tk}           public QR verification
 *   pay        (POST)     start Midtrans / Xendit payment
 *   finish/{tk}           return from the payment page
 *   upload     (POST)     bank-transfer proof
 *   proof/{id}            download a proof (editors / owner)
 *   notify/{gateway}      payment notifications (webhooks)
 *   dashboard             payment status dashboard (editors)
 *   action     (POST)     dashboard actions
 */

import('classes.handler.Handler');

class ApcLoaHandler extends Handler {

	/** @var ApcLoaPlugin */
	private $plugin;

	public function __construct() {
		parent::__construct();
		$this->plugin = PluginRegistry::getPlugin('generic', APC_LOA_PLUGIN_NAME);
	}

	// ------------------------------------------------------------------
	// Access helpers
	// ------------------------------------------------------------------

	private function context($request) {
		$context = $request->getContext();
		if (!$context) $request->getDispatcher()->handle404();
		return $context;
	}

	private function isEditor($request, $context) {
		$user = $request->getUser();
		if (!$user) return false;
		if ($user->hasRole([ROLE_ID_SITE_ADMIN], CONTEXT_SITE)) return true;
		return $user->hasRole([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], $context->getId());
	}

	private function isAuthorOf($user, $submission) {
		if (!$user || !$submission) return false;
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, null, $user->getId());
		if ($assignments && $assignments->next()) return true;
		// Fallback: the user's email is one of the authors' emails
		$publication = $submission->getCurrentPublication();
		foreach ($publication ? (array) $publication->getData('authors') : [] as $author) {
			if ($author->getEmail() && strcasecmp($author->getEmail(), $user->getEmail()) === 0) return true;
		}
		return false;
	}

	private function requireEditor($request, $context) {
		if (!$request->getUser()) {
			Validation::redirectLogin();
		}
		if (!$this->isEditor($request, $context)) $request->getDispatcher()->handle404();
	}

	private function submission($record) {
		return $record ? Services::get('submission')->get((int) $record['submission_id']) : null;
	}

	private function templateMgr($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->addStyleSheet('apcLoa', $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/css/apcloa.css?v=' . $this->assetVersion(), ['contexts' => 'frontend']);
		return $templateMgr;
	}

	private function assetVersion() {
		try {
			$version = $this->plugin->getCurrentVersion();
			return $version ? rawurlencode($version->getVersionString(false)) : '1';
		} catch (Exception $e) {
			return '1';
		}
	}

	private function redirectIndex($request, $context, $flash = null) {
		$request->redirectUrl($this->plugin->pageUrl($request, null, null, $flash ? ['msg' => $flash] : null, $context));
	}

	private function redirectDashboard($request, $context, $flash = null) {
		$request->redirectUrl($this->plugin->pageUrl($request, 'dashboard', null, $flash ? ['msg' => $flash] : null, $context));
	}

	// ------------------------------------------------------------------
	// Shared data for documents and lists
	// ------------------------------------------------------------------

	private function monthName($month, $lang) {
		$id = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
		$en = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		return $lang === 'id' ? $id[$month] : $en[$month];
	}

	public function longDate($date, $lang) {
		if (!$date) return '-';
		$t = strtotime($date);
		return date('j', $t) . ' ' . $this->monthName((int) date('n', $t), $lang) . ' ' . date('Y', $t);
	}

	private function recordView($request, $context, array $record, $submission = null) {
		$submission = $submission ?: $this->submission($record);
		$publication = $submission ? $submission->getCurrentPublication() : null;
		$authors = [];
		$affiliations = [];
		foreach ($publication ? (array) $publication->getData('authors') : [] as $author) {
			$authors[] = $author->getFullName();
			$aff = $author->getLocalizedData('affiliation');
			if ($aff && !in_array($aff, $affiliations)) $affiliations[] = $aff;
		}
		$s = $this->plugin->settings($context->getId());
		$isFee = (float) $record['amount'] > 0;
		return [
			'record' => $record,
			'title' => $publication ? strip_tags($publication->getLocalizedFullTitle()) : '',
			'authors' => $authors,
			'authorsText' => implode(', ', $authors),
			'affiliations' => $affiliations,
			'affiliationsText' => implode(', ', $affiliations),
			'statusText' => __('plugins.generic.apcLoa.status.' . $record['status']),
			'amountText' => $this->plugin->formatMoney($record['amount'], $record['currency']),
			'isFee' => $isFee,
			'acceptedId' => $this->longDate($record['accepted_at'], 'id'),
			'acceptedEn' => $this->longDate($record['accepted_at'], 'en'),
			'dueId' => $this->longDate($record['due_at'], 'id'),
			'dueEn' => $this->longDate($record['due_at'], 'en'),
			'paidId' => $this->longDate($record['paid_at'], 'id'),
			'paidEn' => $this->longDate($record['paid_at'], 'en'),
			'loaUrl' => $this->plugin->pageUrl($request, 'document', ['loa', $record['token']], null, $context),
			'invoiceUrl' => $this->plugin->pageUrl($request, 'document', ['invoice', $record['token']], null, $context),
			'receiptUrl' => $this->plugin->pageUrl($request, 'document', ['receipt', $record['token']], null, $context),
			'verifyUrl' => $this->plugin->pageUrl($request, 'verify', $record['token'], null, $context),
			'proofUrl' => $record['proof_path'] ? $this->plugin->pageUrl($request, 'proof', $record['record_id'], null, $context) : '',
			'workflowUrl' => $submission ? $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $submission->getId()) : '',
			'canPay' => $isFee && in_array($record['status'], ['unpaid', 'pending']),
			'methodMidtrans' => $s['methodMidtrans'],
			'methodXendit' => $s['methodXendit'],
			'methodBank' => $s['methodBank'],
		];
	}

	private function flashText($request) {
		$msg = preg_replace('/[^a-z]/', '', (string) $request->getUserVar('msg'));
		$known = ['error', 'gatewayerror', 'returned', 'uploaded', 'uploaderror', 'toolarge', 'badtype', 'saved', 'sent', 'notsent'];
		return in_array($msg, $known, true) ? __('plugins.generic.apcLoa.msg.' . $msg) : '';
	}

	// ------------------------------------------------------------------
	// Author page
	// ------------------------------------------------------------------

	public function index($args, $request) {
		$context = $this->context($request);
		$user = $request->getUser();
		if (!$user) Validation::redirectLogin();

		$rows = [];
		foreach ($this->plugin->listRecords($context->getId()) as $record) {
			$submission = $this->submission($record);
			if (!$submission || !$this->isAuthorOf($user, $submission)) continue;
			$rows[] = $this->recordView($request, $context, $record, $submission);
		}

		$s = $this->plugin->settings($context->getId());
		$templateMgr = $this->templateMgr($request);
		$templateMgr->assign([
			'apc' => [
				'rows' => $rows,
				'settings' => $s,
				'bankHtml' => nl2br(htmlspecialchars((string) $s['bankInstructions'], ENT_QUOTES, 'UTF-8')),
				'msgText' => $this->flashText($request),
				'payUrl' => $this->plugin->pageUrl($request, 'pay', null, null, $context),
				'uploadUrl' => $this->plugin->pageUrl($request, 'upload', null, null, $context),
				'isEditor' => $this->isEditor($request, $context),
				'dashboardUrl' => $this->plugin->pageUrl($request, 'dashboard', null, null, $context),
			],
		]);
		$templateMgr->display($this->plugin->getTemplateResource('author.tpl'));
	}

	// ------------------------------------------------------------------
	// Documents
	// ------------------------------------------------------------------

	public function document($args, $request) {
		$context = $this->context($request);
		$type = isset($args[0]) ? $args[0] : '';
		$record = $this->plugin->getRecordByToken(isset($args[1]) ? $args[1] : '');
		if (!$record || $record['context_id'] != $context->getId() || !in_array($type, ['loa', 'invoice', 'receipt'])) {
			$request->getDispatcher()->handle404();
		}
		if ($type === 'invoice' && !$record['invoice_number']) $request->getDispatcher()->handle404();
		if ($type === 'receipt' && ($record['status'] !== 'paid' || !$record['receipt_number'])) $request->getDispatcher()->handle404();
		$this->renderDocument($request, $context, $type, $this->recordView($request, $context, $record));
	}

	public function preview($args, $request) {
		$context = $this->context($request);
		$this->requireEditor($request, $context);
		$type = in_array($args[0] ?? '', ['loa', 'invoice', 'receipt']) ? $args[0] : 'loa';
		$s = $this->plugin->settings($context->getId());
		$now = Core::getCurrentDate();
		$record = [
			'record_id' => 0, 'context_id' => $context->getId(), 'submission_id' => 0, 'status' => $type === 'receipt' ? 'paid' : 'unpaid',
			'amount' => (float) $s['apcFee'] > 0 ? (float) $s['apcFee'] : 1000000, 'currency' => $s['currency'],
			'seq' => 1, 'seq_year' => (int) date('Y'), 'token' => str_repeat('0', 32),
			'accepted_at' => $now, 'due_at' => date('Y-m-d H:i:s', strtotime('+' . (int) $s['dueDays'] . ' days')), 'paid_at' => $now,
			'method' => 'bank', 'proof_path' => null,
		];
		$record['loa_number'] = $this->plugin->formatNumber($context, $record, 'loa');
		$record['invoice_number'] = $this->plugin->formatNumber($context, $record, 'invoice');
		$record['receipt_number'] = $this->plugin->formatNumber($context, $record, 'receipt');
		$view = $this->recordView($request, $context, $record, null);
		$view['title'] = __('plugins.generic.apcLoa.preview.sampleTitle');
		$view['authors'] = ['Author One', 'Author Two'];
		$view['authorsText'] = 'Author One, Author Two';
		$view['affiliations'] = ['Universitas Contoh'];
		$view['affiliationsText'] = 'Universitas Contoh';
		$view['isPreview'] = true;
		$this->renderDocument($request, $context, $type, $view);
	}

	private function renderDocument($request, $context, $type, array $view) {
		$s = $this->plugin->settings($context->getId());
		$vars = [
			'{title}' => $view['title'],
			'{journal}' => $context->getLocalizedName(),
			'{authors}' => $view['authorsText'],
			'{date}' => $view['acceptedId'],
		];
		$headerLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s['headerLines'])), 'strlen'));
		if (!$headerLines) {
			$headerLines = [$context->getLocalizedName()];
			if ($context->getData('publisherInstitution')) $headerLines[] = $context->getData('publisherInstitution');
			$issn = array_filter([$context->getData('printIssn') ? 'p-ISSN ' . $context->getData('printIssn') : '', $context->getData('onlineIssn') ? 'e-ISSN ' . $context->getData('onlineIssn') : '']);
			if ($issn) $headerLines[] = implode(' · ', $issn);
			if ($context->getData('mailingAddress')) $headerLines[] = preg_replace('/\s*\R\s*/', ', ', trim($context->getData('mailingAddress')));
			if ($context->getData('contactEmail')) $headerLines[] = $context->getData('contactEmail');
		}
		$accent = preg_match('/^#[0-9a-f]{3,6}$/i', (string) $s['accentColor']) ? $s['accentColor'] : '#0F2A4A';

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'doc' => array_merge($view, [
				'type' => $type,
				'journal' => $context->getLocalizedName(),
				'headerLines' => array_slice($headerLines, 0, 5),
				'loaBodyId' => strtr((string) $s['loaBodyId'], $vars),
				'loaBodyEn' => strtr((string) $s['loaBodyEn'], $vars),
				'bankHtml' => nl2br(htmlspecialchars((string) $s['bankInstructions'], ENT_QUOTES, 'UTF-8')),
				'accent' => $accent,
				'cssUrl' => $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/css/document.css?v=' . $this->assetVersion(),
				'qrJsUrl' => $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/qr.js?v=' . $this->assetVersion(),
				'methodText' => $view['record']['method'] ? __('plugins.generic.apcLoa.method.' . $view['record']['method']) : '-',
			]),
			'docSettings' => $s,
		]);
		$templateMgr->display($this->plugin->getTemplateResource('document.tpl'));
	}

	// ------------------------------------------------------------------
	// Public verification
	// ------------------------------------------------------------------

	public function verify($args, $request) {
		$context = $this->context($request);
		$record = $this->plugin->getRecordByToken($args[0] ?? '');
		$view = null;
		if ($record && $record['context_id'] == $context->getId()) {
			$view = $this->recordView($request, $context, $record);
		}
		$templateMgr = $this->templateMgr($request);
		$templateMgr->assign(['apcVerify' => $view]);
		$templateMgr->display($this->plugin->getTemplateResource('verify.tpl'));
	}

	// ------------------------------------------------------------------
	// Online payment
	// ------------------------------------------------------------------

	public function pay($args, $request) {
		$context = $this->context($request);
		$user = $request->getUser();
		if (!$user) Validation::redirectLogin();
		if (!$request->isPost() || !$request->checkCSRF()) $this->redirectIndex($request, $context, 'error');

		$record = $this->plugin->getRecordById((int) $request->getUserVar('recordId'));
		$submission = $this->submission($record);
		if (!$record || $record['context_id'] != $context->getId() || !$this->isAuthorOf($user, $submission)) $request->getDispatcher()->handle404();
		if ((float) $record['amount'] <= 0 || !in_array($record['status'], ['unpaid', 'pending'])) $this->redirectIndex($request, $context, 'error');

		$s = $this->plugin->settings($context->getId());
		$gateway = (string) $request->getUserVar('gateway');
		$orderId = 'APC' . $record['record_id'] . '-' . time();
		$finishUrl = $this->plugin->pageUrl($request, 'finish', $record['token'], null, $context);
		$item = ($record['invoice_number'] ?: 'APC') . ' ' . $context->getLocalizedAcronym();
		$this->plugin->import('classes.ApcLoaGateway');

		if ($gateway === 'midtrans' && $s['methodMidtrans']) {
			$result = ApcLoaGateway::midtransCreate(
				trim($s['midtransServerKey']), (bool) $s['midtransProduction'], $orderId, (float) $record['amount'],
				['name' => $user->getFullName(), 'email' => $user->getEmail()], $item, $finishUrl,
				$this->plugin->pageUrl($request, 'notify', 'midtrans', null, $context)
			);
		} elseif ($gateway === 'xendit' && $s['methodXendit']) {
			$result = ApcLoaGateway::xenditCreate(
				trim($s['xenditSecretKey']), $orderId, (float) $record['amount'], $record['currency'], $user->getEmail(),
				'APC ' . $item . ' — ' . $this->recordView($request, $context, $record, $submission)['title'],
				$finishUrl, $finishUrl
			);
		} else {
			$this->redirectIndex($request, $context, 'error');
		}

		if (empty($result['ok'])) {
			error_log('apcLoa payment create failed (' . $gateway . '): ' . ($result['error'] ?? ''));
			$this->redirectIndex($request, $context, 'gatewayerror');
		}
		$this->plugin->updateRecord($record['record_id'], ['gateway_order' => $orderId, 'method' => $gateway]);
		$request->redirectUrl($result['url']);
	}

	public function finish($args, $request) {
		$context = $this->context($request);
		$this->redirectIndex($request, $context, 'returned');
	}

	/**
	 * Gateway notifications. Always answers 200 "OK" once handled so gateways stop retrying;
	 * answers 400 on anything that fails verification.
	 */
	public function notify($args, $request) {
		$context = $this->context($request);
		$gateway = $args[0] ?? '';
		$s = $this->plugin->settings($context->getId());
		$payload = json_decode((string) file_get_contents('php://input'), true);
		if (!is_array($payload)) $payload = $_POST;
		$this->plugin->import('classes.ApcLoaGateway');

		$result = null;
		if ($gateway === 'midtrans' && trim($s['midtransServerKey']) !== '') {
			$result = ApcLoaGateway::midtransVerify($payload, trim($s['midtransServerKey']), (bool) $s['midtransProduction']);
		} elseif ($gateway === 'xendit' && trim($s['xenditSecretKey']) !== '') {
			$header = isset($_SERVER['HTTP_X_CALLBACK_TOKEN']) ? $_SERVER['HTTP_X_CALLBACK_TOKEN'] : '';
			$result = ApcLoaGateway::xenditVerify($payload, $header, trim($s['xenditCallbackToken']), trim($s['xenditSecretKey']));
		}

		header('Content-Type: text/plain');
		if (!$result) {
			http_response_code(400);
			echo 'INVALID';
			exit;
		}

		$record = $this->plugin->getRecordByOrder($result['order_id']);
		// An older payment attempt for the same record (order ids are "APC{recordId}-{time}",
		// and the payment itself has been confirmed with the gateway above)
		if (!$record && preg_match('/^APC(\d+)-\d+$/', (string) $result['order_id'], $m)) {
			$record = $this->plugin->getRecordById((int) $m[1]);
		}
		if ($record && $record['context_id'] == $context->getId() && $result['paid']
			&& $result['currency'] === strtoupper($record['currency'])
			&& $result['amount'] + 0.001 >= (float) $record['amount']
			&& in_array($record['status'], ['unpaid', 'pending'])) {
			$record = $this->plugin->markPaid($context, $record, $gateway, $result['ref']);
			$submission = $this->submission($record);
			$this->plugin->notifyJournal($request, $context,
				'[APC] ' . __('plugins.generic.apcLoa.mail.paidOnline') . ' — ' . $record['invoice_number'],
				__('plugins.generic.apcLoa.mail.paidOnline') . ': ' . $record['invoice_number'] . ' (' . $this->plugin->formatMoney($record['amount'], $record['currency']) . ', ' . $gateway . ")\n"
				. ($submission ? strip_tags($submission->getCurrentPublication()->getLocalizedTitle()) : '')
			);
		}
		echo 'OK';
		exit;
	}

	// ------------------------------------------------------------------
	// Bank transfer proof
	// ------------------------------------------------------------------

	private function proofDir($contextId) {
		return rtrim(Config::getVar('files', 'files_dir'), '/') . '/apcLoa/' . (int) $contextId;
	}

	public function upload($args, $request) {
		$context = $this->context($request);
		$user = $request->getUser();
		if (!$user) Validation::redirectLogin();
		if (!$request->isPost() || !$request->checkCSRF()) $this->redirectIndex($request, $context, 'error');

		$record = $this->plugin->getRecordById((int) $request->getUserVar('recordId'));
		$submission = $this->submission($record);
		if (!$record || $record['context_id'] != $context->getId() || !$this->isAuthorOf($user, $submission)) $request->getDispatcher()->handle404();
		if (!$this->plugin->setting($context->getId(), 'methodBank') || !in_array($record['status'], ['unpaid', 'pending'])) $this->redirectIndex($request, $context, 'error');

		$file = isset($_FILES['proof']) ? $_FILES['proof'] : null;
		if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) $this->redirectIndex($request, $context, 'uploaderror');
		if ($file['size'] > 5 * 1024 * 1024) $this->redirectIndex($request, $context, 'toolarge');

		$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
		$mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '';
		if (!isset($allowed[$mime])) $this->redirectIndex($request, $context, 'badtype');

		$dir = $this->proofDir($context->getId());
		if (!is_dir($dir) && !mkdir($dir, 0775, true)) $this->redirectIndex($request, $context, 'uploaderror');
		$name = $record['token'] . '-' . time() . '.' . $allowed[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) $this->redirectIndex($request, $context, 'uploaderror');

		if ($record['proof_path'] && is_file($dir . '/' . basename($record['proof_path']))) @unlink($dir . '/' . basename($record['proof_path']));
		$this->plugin->updateRecord($record['record_id'], [
			'status' => 'pending',
			'method' => 'bank',
			'proof_path' => $name,
			'proof_name' => mb_substr(basename((string) $file['name']), 0, 255),
			'proof_note' => mb_substr(trim((string) $request->getUserVar('note')), 0, 2000),
			'proof_at' => Core::getCurrentDate(),
		]);
		$this->plugin->notifyJournal($request, $context,
			'[APC] ' . __('plugins.generic.apcLoa.mail.newProof') . ' — ' . $record['invoice_number'],
			__('plugins.generic.apcLoa.mail.newProof') . ': ' . $record['invoice_number'] . ' (' . $this->plugin->formatMoney($record['amount'], $record['currency']) . ")\n"
			. strip_tags($submission->getCurrentPublication()->getLocalizedTitle()) . "\n" . $user->getFullName() . ' <' . $user->getEmail() . '>'
		);
		$this->redirectIndex($request, $context, 'uploaded');
	}

	public function proof($args, $request) {
		$context = $this->context($request);
		$user = $request->getUser();
		if (!$user) Validation::redirectLogin();
		$record = $this->plugin->getRecordById((int) ($args[0] ?? 0));
		if (!$record || $record['context_id'] != $context->getId() || !$record['proof_path']) $request->getDispatcher()->handle404();
		if (!$this->isEditor($request, $context) && !$this->isAuthorOf($user, $this->submission($record))) $request->getDispatcher()->handle404();

		$path = $this->proofDir($context->getId()) . '/' . basename($record['proof_path']);
		if (!is_file($path)) $request->getDispatcher()->handle404();
		$mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . filesize($path));
		header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $record['proof_name'] ?: basename($path)) . '"');
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}

	// ------------------------------------------------------------------
	// Editor dashboard
	// ------------------------------------------------------------------

	public function dashboard($args, $request) {
		$context = $this->context($request);
		$this->requireEditor($request, $context);

		$filter = preg_replace('/[^a-z]/', '', (string) $request->getUserVar('status'));
		$all = $this->plugin->listRecords($context->getId());
		$counts = ['unpaid' => 0, 'pending' => 0, 'paid' => 0, 'waived' => 0, 'nofee' => 0, 'revoked' => 0];
		$received = [];
		$outstanding = [];
		$rows = [];
		foreach ($all as $record) {
			$counts[$record['status']] = ($counts[$record['status']] ?? 0) + 1;
			if ($record['status'] === 'paid') $received[$record['currency']] = ($received[$record['currency']] ?? 0) + (float) $record['amount'];
			if (in_array($record['status'], ['unpaid', 'pending'])) $outstanding[$record['currency']] = ($outstanding[$record['currency']] ?? 0) + (float) $record['amount'];
			if ($filter && $record['status'] !== $filter) continue;
			$rows[] = $this->recordView($request, $context, $record);
		}
		$defaultCurrency = $this->plugin->settings($context->getId())['currency'];
		$plugin = $this->plugin;
		$fmt = function ($sums) use ($plugin, $defaultCurrency) {
			$out = [];
			foreach ($sums as $cur => $sum) $out[] = $plugin->formatMoney($sum, $cur);
			return $out ? implode(' · ', $out) : $plugin->formatMoney(0, $defaultCurrency);
		};

		$templateMgr = $this->templateMgr($request);
		$templateMgr->assign([
			'apcDash' => [
				'rows' => $rows,
				'counts' => $counts,
				'total' => count($all),
				'received' => $fmt($received),
				'outstanding' => $fmt($outstanding),
				'filter' => $filter,
				'msgText' => $this->flashText($request),
				'actionUrl' => $this->plugin->pageUrl($request, 'action', null, null, $context),
				'baseUrl' => $this->plugin->pageUrl($request, 'dashboard', null, null, $context),
				'filterUrls' => [
					'unpaid' => $this->plugin->pageUrl($request, 'dashboard', null, ['status' => 'unpaid'], $context),
					'pending' => $this->plugin->pageUrl($request, 'dashboard', null, ['status' => 'pending'], $context),
					'paid' => $this->plugin->pageUrl($request, 'dashboard', null, ['status' => 'paid'], $context),
					'waived' => $this->plugin->pageUrl($request, 'dashboard', null, ['status' => 'waived'], $context),
				],
				'previewUrls' => [
					'loa' => $this->plugin->pageUrl($request, 'preview', 'loa', null, $context),
					'invoice' => $this->plugin->pageUrl($request, 'preview', 'invoice', null, $context),
					'receipt' => $this->plugin->pageUrl($request, 'preview', 'receipt', null, $context),
				],
			],
		]);
		$templateMgr->display($this->plugin->getTemplateResource('dashboard.tpl'));
	}

	public function action($args, $request) {
		$context = $this->context($request);
		$this->requireEditor($request, $context);
		if (!$request->isPost() || !$request->checkCSRF()) $this->redirectDashboard($request, $context, 'error');

		$record = $this->plugin->getRecordById((int) $request->getUserVar('recordId'));
		if (!$record || $record['context_id'] != $context->getId()) $this->redirectDashboard($request, $context, 'error');
		$do = (string) $request->getUserVar('do');
		$note = trim((string) $request->getUserVar('note'));
		$msg = 'saved';

		switch ($do) {
			case 'confirm': // mark as paid (bank proof or manual)
				if (in_array($record['status'], ['unpaid', 'pending']) && (float) $record['amount'] > 0) {
					$this->plugin->markPaid($context, $record, $record['proof_path'] ? 'bank' : 'manual', 'editor:' . $request->getUser()->getUsername());
				}
				break;
			case 'reject': // proof not valid → back to unpaid
				if ($record['status'] === 'pending') $this->plugin->updateRecord($record['record_id'], ['status' => 'unpaid', 'editor_note' => $note ?: $record['editor_note']]);
				break;
			case 'waive':
				if (in_array($record['status'], ['unpaid', 'pending'])) $this->plugin->updateRecord($record['record_id'], ['status' => 'waived', 'method' => 'waiver']);
				break;
			case 'unwaive':
				if ($record['status'] === 'waived') $this->plugin->updateRecord($record['record_id'], ['status' => 'unpaid', 'method' => null]);
				break;
			case 'amount':
				$amount = (float) str_replace([',', ' '], ['.', ''], (string) $request->getUserVar('amount'));
				if ($amount >= 0 && in_array($record['status'], ['unpaid', 'pending', 'nofee', 'waived'])) {
					$data = ['amount' => $amount];
					if ($amount <= 0) {
						$data['status'] = 'nofee';
					} else {
						if ($record['status'] === 'nofee') $data['status'] = 'unpaid';
						if (!$record['due_at']) $data['due_at'] = date('Y-m-d H:i:s', strtotime('+' . (int) $this->plugin->setting($context->getId(), 'dueDays') . ' days'));
					}
					$record = $this->plugin->updateRecord($record['record_id'], $data);
					if ($amount > 0 && !$record['invoice_number']) {
						$this->plugin->updateRecord($record['record_id'], ['invoice_number' => $this->plugin->formatNumber($context, $record, 'invoice')]);
					}
				}
				break;
			case 'resend':
				$submission = $this->submission($record);
				$sent = $submission ? $this->plugin->sendLoaEmail($request, $context, $submission, $record) : 0;
				$msg = $sent ? 'sent' : 'notsent';
				break;
			case 'revoke':
				if ($record['status'] !== 'revoked') $this->plugin->updateRecord($record['record_id'], ['status' => 'revoked']);
				break;
			case 'restore':
				if ($record['status'] === 'revoked') {
					$status = (float) $record['amount'] > 0 ? ($record['paid_at'] ? 'paid' : 'unpaid') : 'nofee';
					$this->plugin->updateRecord($record['record_id'], ['status' => $status]);
				}
				break;
			case 'note':
				$this->plugin->updateRecord($record['record_id'], ['editor_note' => mb_substr($note, 0, 2000)]);
				break;
			default:
				$msg = 'error';
		}
		$this->redirectDashboard($request, $context, $msg);
	}
}
