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
			['index', 'unlock', 'lock', 'start', 'batch', 'test', 'upload', 'download']
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
		$lastId = $plugin->lastRunId();
		$last = $lastId ? $plugin->getJob($lastId) : null;
		$tm->assign($this->common($request) + [
			'cmLocked' => false,
			'cmLockUrl' => $plugin->actionUrl($request, 'lock'),
			'cmStartUrl' => $plugin->actionUrl($request, 'start'),
			'cmBatchUrl' => $plugin->actionUrl($request, 'batch'),
			'cmTestUrl' => $plugin->actionUrl($request, 'test'),
			'cmUploadUrl' => $plugin->actionUrl($request, 'upload'),
			'cmFromEmail' => $from['email'],
			'cmFromName' => $from['name'],
			'cmMax' => self::MAX_RECIPIENTS,
			'cmDailyCap' => self::DAILY_CAP,
			'cmSentToday' => $plugin->sentToday(),
			'cmMyEmail' => $request->getUser() ? $request->getUser()->getEmail() : '',
			'cmLog' => array_slice($plugin->auditLog(), 0, 20),
			'cmLast' => $last ? ['subject' => $last['subject'], 'total' => (int) $last['total'], 'sent' => (int) $last['sent'], 'failed' => (int) $last['failed'], 'processed' => (int) $last['idx'], 'done' => ($last['idx'] >= $last['total'])] : null,
			'cmResumeId' => ($last && $last['idx'] < $last['total']) ? $lastId : '',
			'cmDownloadUrl' => $plugin->actionUrl($request, 'download'),
			'cmTemplates' => $this->templates($from),
			'cmTplJson' => json_encode($this->templates($from), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE),
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

		$prev = $plugin->lastRunId();
		if ($prev) $plugin->deleteJob($prev);
		$id = bin2hex(random_bytes(8));
		$me = $request->getUser();
		$plugin->saveJob($id, [
			'subject' => $subject,
			'body' => $body,
			'format' => $format,
			'mode' => $mode,
			'fromName' => $fromName !== '' ? $fromName : $from['name'],
			'rows' => $rows,
			'results' => [],
			'idx' => 0,
			'sent' => 0,
			'failed' => 0,
			'total' => $total,
			'by' => $me ? $me->getUsername() : '-',
			'ctx' => $request->getContext() ? (int) $request->getContext()->getId() : 0,
			'created' => time(),
		]);
		$plugin->setLastRunId($id);
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
			$job['results'][] = [$r['email'], $ok ? 1 : 0];
			if ($ok) { $job['sent']++; $batchSent++; } else { $job['failed']++; }
			usleep(self::THROTTLE_US);
		}
		$job['idx'] = $end;
		if ($batchSent) $plugin->addSentToday($batchSent);

		$done = $job['idx'] >= $job['total'];
		if ($done && empty($job['logged'])) {
			$job['logged'] = true;
			$plugin->audit('kirim', 'Call/email massal: "' . $job['subject'] . '" — ' . $job['sent'] . ' terkirim, ' . $job['failed'] . ' gagal, dari ' . $job['total'] . ' penerima.', $request);
		}
		$plugin->saveJob($id, $job);
		$plugin->setLastRunId($id);
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

	/* ------------------------------------------------------------------ unggah Excel/CSV */

	function upload($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->json(['error' => 'Permintaan tidak sah.']);
		if (!$this->isUnlocked($request)) return $this->json(['error' => 'Halaman terkunci. Muat ulang dan masukkan kata sandi.']);
		if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) return $this->json(['error' => 'Tidak ada file atau gagal mengunggah.']);
		$f = $_FILES['file'];
		if ($f['size'] > 5 * 1024 * 1024) return $this->json(['error' => 'File terlalu besar (maks 5 MB).']);
		if (!is_uploaded_file($f['tmp_name'])) return $this->json(['error' => 'File tidak valid.']);
		$name = strtolower((string) $f['name']);
		try {
			if (substr($name, -4) === '.csv') $rows = $this->readCsvRows($f['tmp_name']);
			elseif (substr($name, -5) === '.xlsx') $rows = $this->readXlsxRows($f['tmp_name']);
			else return $this->json(['error' => 'Format tidak didukung. Gunakan .xlsx atau .csv.']);
		} catch (\Throwable $e) {
			return $this->json(['error' => 'Gagal membaca file: ' . $e->getMessage()]);
		}
		if (!$rows) return $this->json(['error' => 'File kosong.']);

		// deteksi kolom dari baris header
		$hdr = array_map(function ($x) { return strtolower(trim((string) $x)); }, $rows[0]);
		$ciE = $ciN = $ciT = -1;
		foreach ($hdr as $i => $h) {
			if ($ciE < 0 && strpos($h, 'email') !== false) $ciE = $i;
			if ($ciN < 0 && (strpos($h, 'nama') !== false || strpos($h, 'name') !== false)) $ciN = $i;
			if ($ciT < 0 && (strpos($h, 'judul') !== false || strpos($h, 'title') !== false || strpos($h, 'paper') !== false || strpos($h, 'makalah') !== false)) $ciT = $i;
		}
		$hasHeader = $ciE >= 0;
		if (!$hasHeader) { $ciE = 0; $ciN = 1; $ciT = 2; }
		$start = $hasHeader ? 1 : 0;

		$lines = []; $seen = []; $invalid = 0;
		for ($i = $start; $i < count($rows); $i++) {
			$r = $rows[$i];
			$email = isset($r[$ciE]) ? trim((string) $r[$ciE], " \t<>\"'") : '';
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { if (trim(implode('', $r)) !== '') $invalid++; continue; }
			$key = strtolower($email);
			if (isset($seen[$key])) continue;
			$seen[$key] = true;
			$nm = ($ciN >= 0 && isset($r[$ciN])) ? trim((string) $r[$ciN]) : '';
			$tt = ($ciT >= 0 && isset($r[$ciT])) ? trim((string) $r[$ciT]) : '';
			$nm = str_replace(["\t", "\r", "\n"], ' ', $nm);
			$tt = str_replace(["\t", "\r", "\n"], ' ', $tt);
			$lines[] = $email . "\t" . $nm . "\t" . $tt;
			if (count($lines) >= self::MAX_RECIPIENTS) break;
		}
		if (!$lines) return $this->json(['error' => 'Tidak ada email valid di file. Pastikan ada kolom berisi alamat email.']);
		return $this->json(['text' => implode("\n", $lines), 'count' => count($lines), 'invalid' => $invalid, 'header' => $hasHeader]);
	}

	private function readCsvRows($path) {
		$rows = [];
		$h = fopen($path, 'r');
		if (!$h) throw new Exception('tidak bisa membuka file.');
		$n = 0;
		while (($c = fgetcsv($h, 0, ',')) !== false) {
			if ($n === 0 && isset($c[0])) $c[0] = preg_replace('/^\xEF\xBB\xBF/', '', $c[0]); // buang BOM
			$rows[] = $c;
			if (++$n > self::MAX_RECIPIENTS + 50) break;
		}
		fclose($h);
		return $rows;
	}

	private function colIdx($letters) {
		$n = 0; $letters = strtoupper($letters);
		for ($i = 0; $i < strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
		return $n - 1;
	}

	private function readXlsxRows($path) {
		if (!class_exists('ZipArchive')) throw new Exception('server tidak bisa membaca .xlsx (ekstensi zip nonaktif). Unggah CSV saja.');
		$zip = new ZipArchive();
		if ($zip->open($path) !== true) throw new Exception('file xlsx rusak.');
		$shared = [];
		$ss = $zip->getFromName('xl/sharedStrings.xml');
		if ($ss !== false) {
			$x = simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NONET);
			if ($x) foreach ($x->si as $si) {
				$t = '';
				if (isset($si->t)) $t = (string) $si->t;
				else foreach ($si->r as $r) $t .= (string) $r->t;
				$shared[] = $t;
			}
		}
		$sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
		if ($sheet === false) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$nm = $zip->getNameIndex($i);
				if (preg_match('#xl/worksheets/sheet\d+\.xml$#', $nm)) { $sheet = $zip->getFromName($nm); break; }
			}
		}
		$zip->close();
		if ($sheet === false) throw new Exception('lembar kerja tidak ditemukan.');
		$x = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
		if (!$x) throw new Exception('isi xlsx tidak terbaca.');
		$rows = []; $rc = 0;
		foreach ($x->sheetData->row as $row) {
			$cells = [];
			foreach ($row->c as $c) {
				$ref = (string) $c['r']; $col = preg_replace('/[0-9]+/', '', $ref);
				if ($col === '') continue;
				$t = (string) $c['t']; $v = '';
				if ($t === 's') { $v = isset($shared[(int) $c->v]) ? $shared[(int) $c->v] : ''; }
				elseif ($t === 'inlineStr') { $v = isset($c->is->t) ? (string) $c->is->t : ''; }
				else { $v = (string) $c->v; }
				$cells[$this->colIdx($col)] = $v;
			}
			$max = $cells ? max(array_keys($cells)) : -1;
			$arr = [];
			for ($i = 0; $i <= $max; $i++) $arr[$i] = isset($cells[$i]) ? $cells[$i] : '';
			$rows[] = $arr;
			if (++$rc > self::MAX_RECIPIENTS + 50) break;
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ unduh hasil kirim */

	function download($args, $request) {
		$this->setupTemplate($request);
		$plugin = self::$plugin;
		if (!$this->isUnlocked($request)) { $request->redirectUrl($plugin->pageUrl($request)); return; }
		$id = $plugin->lastRunId();
		$job = $id ? $plugin->getJob($id) : null;
		if (!$job || empty($job['results'])) { $request->redirectUrl($plugin->pageUrl($request)); return; }
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="cheesa_mailer_hasil.csv"');
		echo "\xEF\xBB\xBF" . "email,status\n";
		foreach ($job['results'] as $r) {
			$e = str_replace('"', '""', (string) $r[0]);
			echo '"' . $e . '",' . (!empty($r[1]) ? 'terkirim' : 'gagal') . "\n";
		}
		exit;
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

		$invite_cfp_body =
"Dear Dr. {name},\n\n" .
"I am writing on behalf of the editorial team of CHEESA: Chemical Engineering Research Articles. I read your work, \"{paper}\", with great interest.\n\n" .
"Given the relevance of your research, I would like to warmly invite you to consider submitting your future work to CHEESA. We are currently welcoming submissions for our upcoming issues.\n\n" .
"About CHEESA:\n" .
"- Peer-reviewed and open access (freely available to readers)\n" .
"- Focused on chemical engineering, process technology, and related fields\n" .
"- Growing international readership\n\n" .
"Submission & author guidelines: https://journal-ft.unipma.ac.id/cheesa\n\n" .
"If you have a manuscript that fits our scope, we would be glad to receive it. Should you have any questions, simply reply to this email.\n\n" .
"Thank you for your time and for your contribution to the field.\n\n" .
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
			'invitecfp' => ['label' => 'Personal — Call for Papers', 'subject' => 'Invitation to Submit to CHEESA: Chemical Engineering Research Articles', 'body' => $invite_cfp_body],
			'inviterev' => ['label' => 'Personal — Reviewer/Editor', 'subject' => 'Invitation to Join CHEESA as Reviewer / Editor', 'body' => $invite_body],
		];
	}
}
