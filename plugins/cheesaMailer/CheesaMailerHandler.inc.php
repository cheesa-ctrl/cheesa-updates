<?php

/**
 * @file plugins/generic/cheesaMailer/CheesaMailerHandler.inc.php
 *
 * Halaman Email Massal CHEESA. Terkunci kata sandi; kirim bertahap (job) via AJAX
 * agar tidak timeout; pembatas per-kirim dan per-hari; uji-kirim ke email sendiri.
 */

import('classes.handler.Handler');

class CheesaMailerHandler extends Handler {

	/** @var CheesaMailerPlugin */
	static $plugin;

	// kata sandi sama dengan panel CHEESA lain ("cheesactrl-janganlupa-111")
	const PAGE_PASSWORD_HASH = '$2y$11$ukFP6Lwiu9jLc7jU/eWKTeqB/q6m8y.TLYaIXOpovNocgJ2hUBNxW';
	const UNLOCK_IDLE   = 1800;     // 30 menit tanpa aktivitas -> terkunci lagi
	const BATCH         = 20;       // email per permintaan (hindari timeout)
	const MAX_RECIPIENTS = 2000;    // batas penerima per sekali kirim
	const DAILY_CAP     = 3000;     // batas total email per hari (per situs)
	const THROTTLE_US   = 150000;   // jeda antar email (0,15 detik)

	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
			['index', 'unlock', 'lock', 'start', 'batch', 'test']
		);
	}

	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/* ------------------------------------------------------------------ util */

	private function json($data) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function isUnlocked($request, $touch = true) {
		$session = $request->getSession();
		$t = (int) $session->getSessionVar('cheesaMailUnlocked');
		if (!$t || time() - $t > self::UNLOCK_IDLE) { $session->unsetSessionVar('cheesaMailUnlocked'); return false; }
		if ($touch) $session->setSessionVar('cheesaMailUnlocked', (string) time());
		return true;
	}

	private function common($request) {
		$plugin = self::$plugin;
		$c = $request->getContext();
		return [
			'pageTitle' => 'Email Massal — CHEESA Mailer',
			'cmName' => $c ? $c->getLocalizedName() : 'CHEESA',
			'cmHome' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'index'),
			'cmDash' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'dashboard'),
			'cmPluginsUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'management', 'settings', 'website', null, 'plugins'),
		];
	}

	/** Alamat pengirim = kontak jurnal. */
	private function fromAddress($request) {
		$c = $request->getContext();
		$email = $c ? $c->getData('contactEmail') : '';
		$name  = $c ? $c->getData('contactName')  : '';
		if (!$email) {
			$site = $request->getSite();
			if ($site && method_exists($site, 'getLocalizedContactEmail')) $email = (string) $site->getLocalizedContactEmail();
			if (!$name && $site && method_exists($site, 'getLocalizedContactName')) $name = (string) $site->getLocalizedContactName();
		}
		return ['email' => $email, 'name' => $name ?: 'CHEESA'];
	}

	/* ------------------------------------------------------------------ kunci */

	function unlock($args, $request) {
		$session = $request->getSession();
		$err = '';
		if ($request->isPost() && $request->checkCSRF()) {
			$tries = json_decode((string) $session->getSessionVar('cheesaMailTries'), true) ?: ['n' => 0, 't' => time()];
			if (time() - (int) $tries['t'] > 900) $tries = ['n' => 0, 't' => time()];
			$enc = (string) $request->getUserVar('mlk');
			$typed = $enc !== '' ? (string) base64_decode(strtr($enc, '-_', '+/'), true) : '';
			if ((int) $tries['n'] >= 8) $err = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
			elseif (password_verify(strtolower(trim($typed)), self::PAGE_PASSWORD_HASH)) {
				$session->setSessionVar('cheesaMailUnlocked', (string) time());
				$session->unsetSessionVar('cheesaMailTries');
			} else {
				$tries['n'] = (int) $tries['n'] + 1;
				$session->setSessionVar('cheesaMailTries', json_encode($tries));
				$err = 'Kata sandi salah.';
			}
		} else { $err = 'Permintaan tidak sah.'; }
		if ($err) $session->setSessionVar('cheesaMailLockErr', $err);
		$request->redirectUrl(self::$plugin->pageUrl($request));
	}

	function lock($args, $request) {
		$request->getSession()->unsetSessionVar('cheesaMailUnlocked');
		$request->redirectUrl(self::$plugin->pageUrl($request));
	}

	/* ------------------------------------------------------------------ halaman */

	function index($args, $request) {
		$this->setupTemplate($request);
		$plugin = self::$plugin;
		$tm = TemplateManager::getManager($request);
		$session = $request->getSession();

		if (!$this->isUnlocked($request)) {
			$lockErr = (string) $session->getSessionVar('cheesaMailLockErr');
			$session->unsetSessionVar('cheesaMailLockErr');
			$tm->assign($this->common($request) + [
				'cmLocked' => true,
				'cmLockErr' => $lockErr,
				'cmUnlockUrl' => $plugin->actionUrl($request, 'unlock'),
			]);
			return $tm->display($plugin->getTemplateResource('page.tpl'));
		}

		$from = $this->fromAddress($request);
		$tm->assign($this->common($request) + [
			'cmLocked' => false,
			'cmLockUrl' => $plugin->actionUrl($request, 'lock'),
			'cmStartUrl' => $plugin->actionUrl($request, 'start'),
			'cmBatchUrl' => $plugin->actionUrl($request, 'batch'),
			'cmTestUrl' => $plugin->actionUrl($request, 'test'),
			'cmFromEmail' => $from['email'],
			'cmFromName' => $from['name'],
			'cmMax' => self::MAX_RECIPIENTS,
			'cmDailyCap' => self::DAILY_CAP,
			'cmSentToday' => $plugin->sentToday(),
			'cmMyEmail' => $request->getUser() ? $request->getUser()->getEmail() : '',
			'cmLog' => array_slice($plugin->auditLog(), 0, 20),
			'cmTemplates' => $this->templates($from),
		]);
		return $tm->display($plugin->getTemplateResource('page.tpl'));
	}

	/* ------------------------------------------------------------------ mulai kirim */

	function start($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		if (!$this->isUnlocked($request)) return $this->json(['error' => 'Halaman terkunci. Muat ulang dan masukkan kata sandi.']);
		$plugin = self::$plugin;

		$subject = trim(preg_replace('/[\r\n]+/', ' ', (string) $request->getUserVar('subject')));
		$body = (string) $request->getUserVar('body');
		$format = $request->getUserVar('format') === 'html' ? 'html' : 'plain';
		$fromName = trim(preg_replace('/[\r\n]+/', ' ', (string) $request->getUserVar('fromName')));
		$raw = (string) $request->getUserVar('recipients');
		$mode = $request->getUserVar('mode') === 'personal' ? 'personal' : 'blast';

		if ($subject === '') return $this->json(['error' => 'Subjek wajib diisi.']);
		if (trim($body) === '') return $this->json(['error' => 'Isi email wajib diisi.']);

		$parsed = $this->parseRecipients($raw, $mode);
		$rows = $parsed['rows']; $invalid = $parsed['invalid']; $dup = $parsed['dup'];
		$total = count($rows);
		if ($total === 0) return $this->json(['error' => 'Tidak ada alamat email yang valid di daftar.']);
		if ($total > self::MAX_RECIPIENTS) return $this->json(['error' => 'Terlalu banyak penerima (' . $total . '). Maksimal ' . self::MAX_RECIPIENTS . ' per kirim. Bagi menjadi beberapa pengiriman.']);
		if ($plugin->sentToday() + $total > self::DAILY_CAP) return $this->json(['error' => 'Melebihi batas harian (' . self::DAILY_CAP . ' email/hari). Hari ini sudah ' . $plugin->sentToday() . '. Coba lagi besok atau kurangi penerima.']);

		$from = $this->fromAddress($request);
		if (!$from['email']) return $this->json(['error' => 'Alamat kontak jurnal belum diset, jadi email tidak bisa dikirim. Isi dulu di Settings → Journal → Contact.']);

		$id = bin2hex(random_bytes(8));
		$me = $request->getUser();
		$plugin->saveJob($id, [
			'subject' => $subject,
			'body' => $body,
			'format' => $format,
			'mode' => $mode,
			'fromName' => $fromName !== '' ? $fromName : $from['name'],
			'rows' => $rows,
			'idx' => 0,
			'sent' => 0,
			'failed' => 0,
			'total' => $total,
			'by' => $me ? $me->getUsername() : '-',
			'ctx' => $request->getContext() ? (int) $request->getContext()->getId() : 0,
			'created' => time(),
		]);
		return $this->json(['jobId' => $id, 'total' => $total, 'invalid' => $invalid, 'dup' => $dup]);
	}

	/* ------------------------------------------------------------------ proses batch */

	function batch($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		if (!$this->isUnlocked($request)) return $this->json(['error' => 'Halaman terkunci. Muat ulang dan masukkan kata sandi.']);
		$plugin = self::$plugin;
		$id = (string) $request->getUserVar('jobId');
		$job = $plugin->getJob($id);
		if (!$job) return $this->json(['error' => 'Pekerjaan kirim tidak ditemukan atau sudah selesai.']);

		$from = $this->fromAddress($request);
		$c = $request->getContext();
		$end = min($job['idx'] + self::BATCH, $job['total']);
		$batchSent = 0;
		for ($i = $job['idx']; $i < $end; $i++) {
			$r = $job['rows'][$i];
			$repl = ['{name}' => (string) $r['name'], '{paper}' => (string) $r['paper'], '{email}' => $r['email']];
			$subj = strtr($job['subject'], $repl);
			$bod = strtr($job['body'], $repl);
			$ok = $this->sendOne($from['email'], $job['fromName'], $r['email'], $subj, $bod, $job['format'], $c);
			if ($ok) { $job['sent']++; $batchSent++; } else { $job['failed']++; }
			usleep(self::THROTTLE_US);
		}
		$job['idx'] = $end;
		if ($batchSent) $plugin->addSentToday($batchSent);

		$done = $job['idx'] >= $job['total'];
		if ($done) {
			$plugin->audit('kirim', 'Call/email massal: "' . $job['subject'] . '" — ' . $job['sent'] . ' terkirim, ' . $job['failed'] . ' gagal, dari ' . $job['total'] . ' penerima.', $request);
			$plugin->deleteJob($id);
		} else {
			$plugin->saveJob($id, $job);
		}
		return $this->json([
			'done' => $done, 'sent' => $job['sent'], 'failed' => $job['failed'],
			'processed' => $job['idx'], 'total' => $job['total'],
		]);
	}

	/* ------------------------------------------------------------------ uji kirim */

	function test($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		if (!$this->isUnlocked($request)) return $this->json(['error' => 'Halaman terkunci. Muat ulang dan masukkan kata sandi.']);
		$me = $request->getUser();
		if (!$me || !$me->getEmail()) return $this->json(['error' => 'Email akun Anda tidak ditemukan.']);
		$subject = trim(preg_replace('/[\r\n]+/', ' ', (string) $request->getUserVar('subject')));
		$body = (string) $request->getUserVar('body');
		$format = $request->getUserVar('format') === 'html' ? 'html' : 'plain';
		$fromName = trim(preg_replace('/[\r\n]+/', ' ', (string) $request->getUserVar('fromName')));
		if ($subject === '' || trim($body) === '') return $this->json(['error' => 'Isi subjek dan isi email dulu sebelum uji-kirim.']);
		$from = $this->fromAddress($request);
		if (!$from['email']) return $this->json(['error' => 'Alamat kontak jurnal belum diset (Settings → Journal → Contact).']);
		$repl = ['{name}' => 'Dr. [Nama]', '{paper}' => '[judul makalah]', '{email}' => $me->getEmail()];
		$ok = $this->sendOne($from['email'], $fromName !== '' ? $fromName : $from['name'], $me->getEmail(), '[UJI] ' . strtr($subject, $repl), strtr($body, $repl), $format, $request->getContext());
		return $ok ? $this->json(['ok' => true, 'email' => $me->getEmail()]) : $this->json(['error' => 'Uji-kirim gagal. Cek konfigurasi email server.']);
	}

	/* ------------------------------------------------------------------ kirim satu email */

	private function sendOne($fromEmail, $fromName, $to, $subject, $bodyRaw, $format, $context) {
		try {
			import('lib.pkp.classes.mail.Mail');
			$mail = new Mail();
			$mail->setFrom($fromEmail, $fromName);
			$mail->addRecipient($to);
			$mail->addHeader('Reply-To', $fromName !== '' ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail);
			$mail->setSubject($subject);
			$journal = $context ? $context->getLocalizedName() : 'CHEESA';
			if ($format === 'html') {
				$mail->setBody($this->htmlShell($bodyRaw, $journal, $fromEmail));
				$mail->addHeader('MIME-Version', '1.0');
				$mail->addHeader('Content-Type', 'text/html; charset="utf-8"');
			} else {
				$foot = "\n\n—\nAnda menerima email ini karena alamat Anda ada dalam daftar kontak kami untuk " . $journal . ".\nUntuk berhenti menerima, balas email ini dengan subjek: BERHENTI.";
				$mail->setBody($bodyRaw . $foot);
			}
			return (bool) $mail->send();
		} catch (\Throwable $e) {
			error_log('cheesaMailer send ' . $to . ': ' . $e->getMessage());
			return false;
		}
	}

	/** Bungkus teks jadi HTML berheader CHEESA; tautan otomatis & baris baru dijaga. */
	private function htmlShell($text, $journal, $contact) {
		$esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
		$esc = preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" style="color:#ed991f;">$1</a>', $esc);
		$esc = nl2br($esc);
		$j = htmlspecialchars($journal, ENT_QUOTES, 'UTF-8');
		$cmail = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="margin:0;padding:0;background:#eef1f6;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;padding:24px 12px;"><tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">'
			. '<tr><td style="background:#030271;padding:22px 28px;"><div style="color:#fff;font-size:20px;font-weight:bold;letter-spacing:.5px;">' . $j . '</div>'
			. '<div style="color:#9fb0e6;font-size:12px;margin-top:3px;">Chemical Engineering Research Articles</div></td></tr>'
			. '<tr><td style="height:4px;background:#ed991f;"></td></tr>'
			. '<tr><td style="padding:26px 28px;color:#20272e;font-size:15px;line-height:1.6;">' . $esc . '</td></tr>'
			. '<tr><td style="padding:16px 28px 24px;border-top:1px solid #eef0f6;color:#7a8595;font-size:12px;line-height:1.5;">'
			. 'Anda menerima email ini karena alamat Anda ada dalam daftar kontak kami untuk ' . $j . '.<br>'
			. 'Untuk berhenti menerima, balas email ini dengan subjek: <b>BERHENTI</b>. &nbsp;·&nbsp; Kontak: ' . $cmail
			. '</td></tr></table></td></tr></table></body></html>';
	}

	/* ------------------------------------------------------------------ template siap pakai */

	/** Urai daftar penerima. blast: token email saja. personal: baris "email, Nama, Judul" (atau dipisah TAB dari Excel). */
	private function parseRecipients($raw, $mode) {
		$rows = []; $invalid = 0; $dup = 0; $seen = [];
		if ($mode === 'personal') {
			foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
				if (trim($line) === '') continue;
				$c = (strpos($line, "\t") !== false) ? explode("\t", $line) : explode(',', $line, 3);
				$email = trim(isset($c[0]) ? $c[0] : '', " \t<>\"'");
				$name = isset($c[1]) ? trim($c[1]) : '';
				$paper = isset($c[2]) ? trim($c[2]) : '';
				if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $invalid++; continue; }
				$key = strtolower($email);
				if (isset($seen[$key])) { $dup++; continue; }
				$seen[$key] = true;
				$rows[] = ['email' => $email, 'name' => $name, 'paper' => $paper];
			}
		} else {
			foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
				$e = trim($p, " \t\n\r\0\x0B<>\"'");
				if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { if ($e !== '') $invalid++; continue; }
				$key = strtolower($e);
				if (isset($seen[$key])) { $dup++; continue; }
				$seen[$key] = true;
				$rows[] = ['email' => $e, 'name' => '', 'paper' => ''];
			}
		}
		return ['rows' => $rows, 'invalid' => $invalid, 'dup' => $dup];
	}

	private function templates($from) {
		$cfp_body =
"Dear Colleagues,\n\n" .
"We are pleased to invite you to submit your original research to CHEESA: Chemical Engineering Research Articles — a peer-reviewed journal publishing work in chemical engineering, process technology, and related fields.\n\n" .
"Scope includes (but is not limited to): reaction engineering, separation processes, biofuels and renewable energy, environmental and wastewater treatment, materials and catalysis, and process modeling.\n\n" .
"Why submit to CHEESA:\n" .
"- Rigorous and constructive peer review\n" .
"- Open access and freely available to readers\n" .
"- Indexed and growing international readership\n\n" .
"Submission deadline for the upcoming issue: {DEADLINE}\n" .
"Submit online: https://journal-ft.unipma.ac.id/cheesa\n" .
"Author guidelines: https://journal-ft.unipma.ac.id/cheesa\n\n" .
"We look forward to your contribution.\n\n" .
"Warm regards,\n" .
"Editorial Team\n" .
"CHEESA: Chemical Engineering Research Articles";

		$crev_body =
"Dear Colleague,\n\n" .
"CHEESA: Chemical Engineering Research Articles is expanding its reviewer and editorial board, and we would be honored to have you join us.\n\n" .
"Based on your expertise, we believe your contribution as a reviewer/editor would be highly valuable to our journal and the chemical engineering community.\n\n" .
"As a reviewer you would:\n" .
"- Receive manuscripts matching your area of expertise\n" .
"- Help maintain the scientific quality of published work\n" .
"- Be formally acknowledged for your service\n\n" .
"If you are interested, please register as a reviewer here:\n" .
"https://journal-ft.unipma.ac.id/cheesa\n\n" .
"Kindly reply to this email with your short CV or ORCID iD and your areas of expertise, and our editorial team will follow up.\n\n" .
"Thank you for considering this invitation.\n\n" .
"Best regards,\n" .
"Editorial Team\n" .
"CHEESA: Chemical Engineering Research Articles";

		$invite_body =
"Dear Dr. {name},\n\n" .
"I am writing on behalf of the editorial team of CHEESA: Chemical Engineering Research Articles. I read your work, \"{paper}\", with great interest.\n\n" .
"Given your expertise in this area, I would like to warmly invite you to consider CHEESA for your future research, and — if you are open to it — to join our pool of reviewers.\n\n" .
"About CHEESA:\n" .
"- Peer-reviewed, open access, freely available to readers\n" .
"- Focused on chemical engineering, process technology, and related fields\n" .
"- Growing international readership\n\n" .
"Journal homepage: https://journal-ft.unipma.ac.id/cheesa\n\n" .
"If you would be willing to review for us, simply reply to this email with your ORCID iD and your areas of expertise, and our team will follow up.\n\n" .
"Thank you very much for your time and for your contribution to the field.\n\n" .
"Best regards,\n" .
"Editorial Team\n" .
"CHEESA: Chemical Engineering Research Articles";

		return [
			'cfp' => ['label' => 'Call for Papers', 'subject' => 'Call for Papers — CHEESA: Chemical Engineering Research Articles', 'body' => $cfp_body],
			'reviewer' => ['label' => 'Call for Reviewer / Editor', 'subject' => 'Invitation to Join as Reviewer / Editor — CHEESA', 'body' => $crev_body],
			'invite' => ['label' => 'Undangan personal (pakai {name}, {paper})', 'subject' => 'Invitation from CHEESA: Chemical Engineering Research Articles', 'body' => $invite_body],
		];
	}
}
