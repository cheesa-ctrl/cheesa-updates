<?php

/**
 * @file plugins/generic/apcLoa/ApcLoaPlugin.inc.php
 *
 * @class ApcLoaPlugin
 * @brief Letter of Acceptance (LoA) & Article Processing Charge (APC) invoices.
 *
 * - When an editor records an Accept decision, a numbered, bilingual LoA is issued
 *   and emailed to the authors, together with the APC invoice if a fee applies.
 * - Authors open "APC & LoA" (page /apcloa) to print documents, pay online
 *   (Midtrans / Xendit) or upload a bank-transfer proof.
 * - Editors manage everything on /apcloa/dashboard.
 * - Every document carries a QR code that leads to a public verification page.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class ApcLoaPlugin extends GenericPlugin {

	const TABLE = 'apc_loa_records';

	/** Journal-level settings and their defaults. */
	const DEFAULTS = [
		// Fee
		'apcFee' => 0,
		'currency' => 'IDR',
		'dueDays' => 14,
		'sendTo' => 'all',                  // all | primary
		'triggerProduction' => true,       // also issue when "Send to Production" is used without Accept
		// Payment methods
		'methodBank' => true,
		'methodMidtrans' => false,
		'methodXendit' => false,
		'bankInstructions' => '',
		'qrisUrl' => '',
		'midtransServerKey' => '',
		'midtransProduction' => false,
		'xenditSecretKey' => '',
		'xenditCallbackToken' => '',
		// Letterhead
		'logoLeftUrl' => '',
		'logoRightUrl' => '',
		'headerLines' => '',
		'headerLineStyle' => 'double',      // double | single | thick | none
		'paperSize' => 'A4',                // A4 | F4 | Letter
		'accentColor' => '#0F2A4A',
		'city' => '',
		// Numbering
		'numberPattern' => '{SEQ}/{PREFIX}/{ACRONYM}/{ROMAN}/{YEAR}',
		'prefixLoa' => 'LoA',
		'prefixInvoice' => 'INV',
		'prefixReceipt' => 'KWT',
		// Signatories
		'signer1Name' => '',
		'signer1Title' => 'Editor in Chief',
		'signer1Sign' => '',
		'signer2Name' => '',
		'signer2Title' => '',
		'signer2Sign' => '',
		'stampUrl' => '',
		'showQr' => true,
		'footerText' => 'Dokumen ini sah tanpa tanda tangan basah karena dapat diverifikasi melalui kode QR. / This document is valid without a wet signature as it can be verified through the QR code.',
		// LoA wording (placeholders: {title}, {journal}, {authors}, {date})
		'loaBodyId' => 'Dengan hormat, kami sampaikan bahwa naskah berjudul "{title}" yang ditulis oleh {authors} telah melalui proses penelaahan sejawat (peer review) dan dinyatakan DITERIMA untuk diterbitkan di {journal}. Terima kasih atas kontribusi Anda.',
		'loaBodyEn' => 'We are pleased to inform you that the manuscript entitled "{title}" by {authors} has been peer-reviewed and ACCEPTED for publication in {journal}. Thank you for your valuable contribution.',
		// Email
		'emailSubject' => 'Letter of Acceptance — {journal}',
		'emailBody' => "Yth. {authorName},\n\nNaskah Anda \"{title}\" telah DITERIMA untuk diterbitkan di {journal}.\n\nLetter of Acceptance: {loaUrl}\n{invoiceBlock}\nSemua dokumen dan pembayaran dapat diakses di: {apcPageUrl}\n\n---\n\nDear {authorName},\n\nYour manuscript \"{title}\" has been ACCEPTED for publication in {journal}.\n\nLetter of Acceptance: {loaUrl}\n{invoiceBlockEn}\nAll documents and payment options: {apcPageUrl}\n\n{journal}",
	];

	const CHECKBOXES = ['triggerProduction', 'methodBank', 'methodMidtrans', 'methodXendit', 'midtransProduction', 'showQr'];
	const NUMBERS = ['apcFee', 'dueDays'];

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			$this->ensureTable();
			HookRegistry::register('EditorAction::recordDecision', [$this, 'handleDecision']);
			HookRegistry::register('LoadHandler', [$this, 'handleLoadHandler']);
		}
		return $success;
	}

	public function getDisplayName() {
		return __('plugins.generic.apcLoa.displayName');
	}

	public function getDescription() {
		return __('plugins.generic.apcLoa.description');
	}

	public function getInstallMigration() {
		$this->import('classes.ApcLoaMigration');
		return new ApcLoaMigration();
	}

	/** Create the table if the install migration did not run (e.g. manual copy). */
	public function ensureTable() {
		try {
			if (!Capsule::schema()->hasTable(self::TABLE)) {
				$this->getInstallMigration()->up();
			}
		} catch (Exception $e) {
			error_log('apcLoa: could not create table: ' . $e->getMessage());
		}
	}

	// ------------------------------------------------------------------
	// Settings helpers
	// ------------------------------------------------------------------

	public function setting($contextId, $name) {
		$value = $this->getSetting($contextId, $name);
		return $value === null ? self::DEFAULTS[$name] : $value;
	}

	public function settings($contextId) {
		$all = [];
		foreach (array_keys(self::DEFAULTS) as $name) $all[$name] = $this->setting($contextId, $name);
		$all['currency'] = strtoupper(trim((string) $all['currency'])) ?: 'IDR';
		// Midtrans only supports IDR
		if ($all['currency'] !== 'IDR') $all['methodMidtrans'] = false;
		if (trim((string) $all['midtransServerKey']) === '') $all['methodMidtrans'] = false;
		if (trim((string) $all['xenditSecretKey']) === '') $all['methodXendit'] = false;
		return $all;
	}

	// ------------------------------------------------------------------
	// Record storage
	// ------------------------------------------------------------------

	public function table() {
		return Capsule::table(self::TABLE);
	}

	public function getRecord($contextId, $submissionId) {
		$row = $this->table()->where('context_id', (int) $contextId)->where('submission_id', (int) $submissionId)->first();
		return $row ? (array) $row : null;
	}

	public function getRecordById($recordId) {
		$row = $this->table()->where('record_id', (int) $recordId)->first();
		return $row ? (array) $row : null;
	}

	public function getRecordByToken($token) {
		if (!preg_match('/^[a-f0-9]{32}$/', (string) $token)) return null;
		$row = $this->table()->where('token', $token)->first();
		return $row ? (array) $row : null;
	}

	public function getRecordByOrder($order) {
		if (!preg_match('/^[A-Za-z0-9\-_.]{1,64}$/', (string) $order)) return null;
		$row = $this->table()->where('gateway_order', $order)->first();
		return $row ? (array) $row : null;
	}

	public function updateRecord($recordId, array $data) {
		$data['updated_at'] = Core::getCurrentDate();
		$this->table()->where('record_id', (int) $recordId)->update($data);
		return $this->getRecordById($recordId);
	}

	public function listRecords($contextId, $status = null) {
		$q = $this->table()->where('context_id', (int) $contextId);
		if ($status) $q->where('status', $status);
		return array_map(function ($r) { return (array) $r; }, $q->orderBy('record_id', 'desc')->get()->toArray());
	}

	/**
	 * Create the record for an accepted submission (idempotent) and issue numbers.
	 */
	public function issueRecord($context, $submission) {
		$contextId = $context->getId();
		$existing = $this->getRecord($contextId, $submission->getId());
		if ($existing) {
			// Re-accepting a revoked manuscript restores it with the same numbers
			if ($existing['status'] === 'revoked') {
				$status = ((float) $existing['amount'] > 0) ? ($existing['paid_at'] ? 'paid' : 'unpaid') : 'nofee';
				return $this->updateRecord($existing['record_id'], ['status' => $status]);
			}
			return $existing;
		}

		$s = $this->settings($contextId);
		$now = Core::getCurrentDate();
		$year = (int) date('Y', strtotime($now));
		$seq = (int) $this->table()->where('context_id', $contextId)->where('seq_year', $year)->max('seq') + 1;
		$fee = max(0, (float) $s['apcFee']);

		$id = $this->table()->insertGetId([
			'context_id' => $contextId,
			'submission_id' => $submission->getId(),
			'status' => $fee > 0 ? 'unpaid' : 'nofee',
			'amount' => $fee,
			'currency' => $s['currency'],
			'seq' => $seq,
			'seq_year' => $year,
			'token' => bin2hex(random_bytes(16)),
			'accepted_at' => $now,
			'due_at' => $fee > 0 ? date('Y-m-d H:i:s', strtotime($now . ' +' . max(1, (int) $s['dueDays']) . ' days')) : null,
			'updated_at' => $now,
		], 'record_id');

		$record = $this->getRecordById($id);
		$data = ['loa_number' => $this->formatNumber($context, $record, 'loa')];
		if ($fee > 0) $data['invoice_number'] = $this->formatNumber($context, $record, 'invoice');
		return $this->updateRecord($id, $data);
	}

	/**
	 * Build a document number from the journal's pattern.
	 * Placeholders: {SEQ} {PREFIX} {ACRONYM} {YEAR} {MONTH} {ROMAN} {ID}
	 */
	public function formatNumber($context, array $record, $type) {
		$s = $this->settings($context->getId());
		$prefix = ['loa' => $s['prefixLoa'], 'invoice' => $s['prefixInvoice'], 'receipt' => $s['prefixReceipt']][$type];
		$time = strtotime($type === 'receipt' && $record['paid_at'] ? $record['paid_at'] : $record['accepted_at']);
		$month = (int) date('n', $time);
		$roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
		$acronym = preg_replace('/\s+/', '', (string) ($context->getLocalizedAcronym() ?: $context->getPath()));
		return strtr((string) $s['numberPattern'], [
			'{SEQ}' => str_pad((string) $record['seq'], 5, '0', STR_PAD_LEFT),
			'{PREFIX}' => $prefix,
			'{ACRONYM}' => strtoupper($acronym),
			'{YEAR}' => date('Y', $time),
			'{MONTH}' => date('m', $time),
			'{ROMAN}' => $roman,
			'{ID}' => (string) $record['submission_id'],
		]);
	}

	/**
	 * Mark a record as paid and issue its receipt number.
	 */
	public function markPaid($context, array $record, $method, $ref = null) {
		if (in_array($record['status'], ['paid', 'revoked'])) return $record;
		$data = [
			'status' => 'paid',
			'paid_at' => Core::getCurrentDate(),
			'method' => $method,
		];
		if ($ref !== null) $data['gateway_ref'] = substr((string) $ref, 0, 128);
		$record = $this->updateRecord($record['record_id'], $data);
		if (!$record['receipt_number']) {
			$record = $this->updateRecord($record['record_id'], ['receipt_number' => $this->formatNumber($context, $record, 'receipt')]);
		}
		return $record;
	}

	// ------------------------------------------------------------------
	// Formatting & URLs
	// ------------------------------------------------------------------

	public function formatMoney($amount, $currency) {
		$amount = (float) $amount;
		if ($currency === 'IDR') return 'Rp ' . number_format($amount, 0, ',', '.');
		return $currency . ' ' . number_format($amount, 2, '.', ',');
	}

	public function pageUrl($request, $op = null, $path = null, $params = null, $context = null) {
		if (!$context) $context = $request->getContext();
		$contextPath = $context ? $context->getPath() : null;
		return $request->getDispatcher()->url($request, ROUTE_PAGE, $contextPath, 'apcloa', $op, $path, $params);
	}

	// ------------------------------------------------------------------
	// Hooks
	// ------------------------------------------------------------------

	/**
	 * Accept (or Send to Production) → issue LoA + invoice and email the authors.
	 * Decline after acceptance → documents become "revoked".
	 */
	public function handleDecision($hookName, $args) {
		$submission = $args[0];
		$editorDecision = $args[1];
		$recommendation = isset($args[3]) ? $args[3] : false;
		if ($recommendation) return false;

		$decision = (int) (is_array($editorDecision) ? ($editorDecision['decision'] ?? 0) : 0);
		$accept = defined('SUBMISSION_EDITOR_DECISION_ACCEPT') ? SUBMISSION_EDITOR_DECISION_ACCEPT : 1;
		$production = defined('SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION') ? SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION : 7;
		$declines = [
			defined('SUBMISSION_EDITOR_DECISION_DECLINE') ? SUBMISSION_EDITOR_DECISION_DECLINE : 4,
			defined('SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE') ? SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE : 9,
		];

		try {
			$request = Application::get()->getRequest();
			$context = Services::get('context')->get($submission->getData('contextId'));
			if (!$context) return false;

			if ($decision === $accept || ($decision === $production && $this->setting($context->getId(), 'triggerProduction'))) {
				$existing = $this->getRecord($context->getId(), $submission->getId());
				$record = $this->issueRecord($context, $submission);
				if (!$existing || !$existing['loa_sent_at'] || $existing['status'] === 'revoked') {
					$this->sendLoaEmail($request, $context, $submission, $record);
				}
			} elseif (in_array($decision, $declines, true)) {
				$record = $this->getRecord($context->getId(), $submission->getId());
				if ($record && $record['status'] !== 'revoked') {
					$this->updateRecord($record['record_id'], ['status' => 'revoked']);
				}
			}
		} catch (Exception $e) {
			// Never block the editorial decision
			error_log('apcLoa: ' . $e->getMessage());
		}
		return false;
	}

	public function handleLoadHandler($hookName, $args) {
		$page = $args[0];
		if ($page === 'apcloa') {
			define('HANDLER_CLASS', 'ApcLoaHandler');
			define('APC_LOA_PLUGIN_NAME', $this->getName());
			$sourceFile =& $args[2];
			$sourceFile = $this->getPluginPath() . '/pages/ApcLoaHandler.inc.php';
			return true;
		}
		return false;
	}

	// ------------------------------------------------------------------
	// Email
	// ------------------------------------------------------------------

	/**
	 * Email the LoA (and invoice) links to the authors.
	 * @return int number of recipients
	 */
	public function sendLoaEmail($request, $context, $submission, array $record) {
		$s = $this->settings($context->getId());
		$publication = $submission->getCurrentPublication();
		$authors = $publication ? (array) $publication->getData('authors') : [];
		$primaryId = $publication ? $publication->getData('primaryContactId') : null;

		$recipients = [];
		foreach ($authors as $author) {
			if (!$author->getEmail()) continue;
			if ($s['sendTo'] === 'primary' && $primaryId && $author->getId() != $primaryId) continue;
			$recipients[strtolower($author->getEmail())] = [$author->getEmail(), $author->getFullName()];
		}
		if (!$recipients) return 0;

		$title = $publication ? strip_tags($publication->getLocalizedTitle()) : '';
		$journal = $context->getLocalizedName();
		$loaUrl = $this->pageUrl($request, 'document', ['loa', $record['token']], null, $context);
		$invoiceBlock = $invoiceBlockEn = '';
		if ((float) $record['amount'] > 0 && $record['invoice_number']) {
			$invoiceUrl = $this->pageUrl($request, 'document', ['invoice', $record['token']], null, $context);
			$amount = $this->formatMoney($record['amount'], $record['currency']);
			$due = $record['due_at'] ? date('Y-m-d', strtotime($record['due_at'])) : '-';
			$invoiceBlock = "Invoice APC ({$amount}, jatuh tempo {$due}): {$invoiceUrl}\n";
			$invoiceBlockEn = "APC invoice ({$amount}, due {$due}): {$invoiceUrl}\n";
		}
		$apcPageUrl = $this->pageUrl($request, null, null, null, $context);

		import('lib.pkp.classes.mail.Mail');
		$sent = 0;
		foreach ($recipients as $recipient) {
			list($email, $name) = $recipient;
			$vars = [
				'{authorName}' => $name, '{title}' => $title, '{journal}' => $journal,
				'{loaUrl}' => $loaUrl, '{invoiceBlock}' => $invoiceBlock, '{invoiceBlockEn}' => $invoiceBlockEn,
				'{apcPageUrl}' => $apcPageUrl,
			];
			$subject = strtr((string) $s['emailSubject'], $vars);
			$body = strtr((string) $s['emailBody'], $vars);
			$html = nl2br(preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', htmlspecialchars($body, ENT_QUOTES, 'UTF-8')));

			$mail = new Mail();
			$mail->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
			$mail->addRecipient($email, $name);
			$mail->setSubject($subject);
			if (method_exists($mail, 'setContentType')) $mail->setContentType('text/html');
			$mail->setBody($html);
			if ($mail->send()) $sent++;
		}
		if ($sent) $this->updateRecord($record['record_id'], ['loa_sent_at' => Core::getCurrentDate()]);
		return $sent;
	}

	/**
	 * Short notice to the journal contact (new transfer proof, online payment).
	 */
	public function notifyJournal($request, $context, $subject, $text) {
		$to = $context->getData('contactEmail');
		if (!$to) return;
		import('lib.pkp.classes.mail.Mail');
		$mail = new Mail();
		$mail->setFrom($to, $context->getData('contactName'));
		$mail->addRecipient($to, $context->getData('contactName'));
		$mail->setSubject($subject);
		$text .= "\n\n" . $this->pageUrl($request, 'dashboard', null, null, $context);
		if (method_exists($mail, 'setContentType')) $mail->setContentType('text/html');
		$mail->setBody(nl2br(preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', htmlspecialchars($text, ENT_QUOTES, 'UTF-8'))));
		$mail->send();
	}

	// ------------------------------------------------------------------
	// Plugin list actions: Settings + Dashboard
	// ------------------------------------------------------------------

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift(
			$actions,
			new LinkAction(
				'settings',
				new AjaxModal(
					$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
					$this->getDisplayName()
				),
				__('manager.plugins.settings'),
				null
			),
			new LinkAction(
				'apcLoaDashboard',
				new RedirectAction($this->pageUrl($request, 'dashboard')),
				__('plugins.generic.apcLoa.dashboard'),
				null
			)
		);
		return $actions;
	}

	public function manage($args, $request) {
		if ($request->getUserVar('verb') === 'settings') {
			$context = $request->getContext();
			$this->import('classes.ApcLoaSettingsForm');
			$form = new ApcLoaSettingsForm($this, $context->getId());
			import('lib.pkp.classes.core.JSONMessage');
			if ($request->getUserVar('save')) {
				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					return new JSONMessage(true);
				}
			} else {
				$form->initData();
			}
			return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}
}
