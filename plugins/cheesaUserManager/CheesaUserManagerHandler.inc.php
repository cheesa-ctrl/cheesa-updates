<?php

/**
 * @file plugins/generic/cheesaUserManager/CheesaUserManagerHandler.inc.php
 *
 * Halaman /index.php/<jurnal>/cheesausers
 *  - Site Administrator: semua akun di server.
 *  - Journal Manager/Editor: hanya akun yang punya peran di jurnal ini, dan hanya bisa mengubah akun yang
 *    TIDAK terdaftar di jurnal lain (akun dipakai bersama semua jurnal di server) serta bukan Manager/Admin.
 *  - index : cari & lihat akun (username, email, nama, peran, jejak aktivitas)
 *  - save  : POST verb=username | delete | merge  (CSRF + konfirmasi ketik username)
 *
 * Hapus hanya untuk akun TANPA jejak aktivitas (naskah, review, keputusan, file, diskusi, pembayaran).
 * Akun yang punya jejak harus digabungkan ke akun lain (fungsi bawaan OJS UserAction::mergeUsers),
 * supaya riwayat naskah tetap utuh.
 */

import('classes.handler.Handler');

use Illuminate\Database\Capsule\Manager as Capsule;

class CheesaUserManagerHandler extends Handler {

	/** @var CheesaUserManagerPlugin */
	public static $plugin;

	const PER_PAGE = 25;

	/** Hash bcrypt kata sandi halaman (kata sandi tidak disimpan dalam bentuk asli). */
	const PAGE_PASSWORD_HASH = '$2y$11$ukFP6Lwiu9jLc7jU/eWKTeqB/q6m8y.TLYaIXOpovNocgJ2hUBNxW';

	/** Halaman terkunci lagi setelah sekian detik tanpa aktivitas. */
	const UNLOCK_IDLE = 1800;

	/** Tabel yang menandakan akun pernah dipakai bekerja: [kolom, label]. */
	const ACTIVITY = [
		'stage_assignments'   => ['user_id', 'penugasan naskah'],
		'review_assignments'  => ['reviewer_id', 'review'],
		'edit_decisions'      => ['editor_id', 'keputusan editor'],
		'submission_files'    => ['uploader_user_id', 'file naskah'],
		'notes'               => ['user_id', 'catatan'],
		'query_participants'  => ['user_id', 'diskusi'],
		'submission_comments' => ['author_id', 'komentar'],
		'event_log'           => ['user_id', 'riwayat aktivitas'],
		'subscriptions'       => ['user_id', 'langganan'],
		'completed_payments'  => ['user_id', 'pembayaran'],
	];

	/** Data milik akun yang ikut dihapus bersama akun (urutan penting: users terakhir). */
	const CLEANUP = [
		'user_settings', 'user_user_groups', 'user_interests', 'notifications',
		'notification_subscription_settings', 'sessions', 'access_keys', 'temporary_files', 'email_log_users',
	];

	const ROLES = [
		1 => 'Site Admin', 16 => 'Manager', 17 => 'Editor Bagian', 4097 => 'Asisten',
		65536 => 'Penulis', 4096 => 'Reviewer', 1048576 => 'Pembaca', 2097152 => 'Pelanggan',
	];

	public function __construct() {
		parent::__construct();
		// Manager boleh membuka halaman (untuk melihat pesan "khusus Site Administrator");
		// semua data & aksi tetap hanya untuk Site Administrator (diperiksa di index() dan save()).
		$this->addRoleAssignment([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], ['index', 'save', 'unlock', 'lock']);
	}

	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	private function common($request) {
		$d = $request->getDispatcher();
		return [
			'umJournal' => $request->getContext() ? $request->getContext()->getLocalizedName() : '',
			'umDashUrl' => $d->url($request, ROUTE_PAGE, null, 'submissions'),
			'umUsersUrl' => $d->url($request, ROUTE_PAGE, null, 'management', 'settings', 'access'),
			'umAdminName' => $request->getUser()->getUsername(),
			'umIsAdmin' => Validation::isSiteAdmin(),
		];
	}

	/* ------------------------------------------------------------------ kunci kata sandi */

	/** Terbuka bila kata sandi sudah dimasukkan dan belum lewat 30 menit tanpa aktivitas. */
	private function isUnlocked($request, $touch = true) {
		$session = $request->getSession();
		$t = (int) $session->getSessionVar('cheesaUMUnlocked');
		if (!$t || time() - $t > self::UNLOCK_IDLE) { $session->unsetSessionVar('cheesaUMUnlocked'); return false; }
		if ($touch) $session->setSessionVar('cheesaUMUnlocked', (string) time());
		return true;
	}

	public function unlock($args, $request) {
		$plugin = self::$plugin;
		$session = $request->getSession();
		$err = '';
		if (!$this->canUse($request)) $err = 'Hanya Site Administrator atau Journal Manager/Editor jurnal ini.';
		elseif (!$request->isPost() || !$request->checkCSRF()) $err = 'Sesi formulir kedaluwarsa. Coba lagi.';
		else {
			$tries = json_decode((string) $session->getSessionVar('cheesaUMTries'), true) ?: ['n' => 0, 't' => time()];
			if (time() - $tries['t'] > 600) $tries = ['n' => 0, 't' => time()];
			// dikirim base64 di "umk": server ini membuang parameter yang bernama password
			$enc = (string) $request->getUserVar('umk');
			$typed = $enc !== '' ? (string) base64_decode(strtr($enc, '-_', '+/'), true) : '';
			if ($tries['n'] >= 5) $err = 'Terlalu banyak percobaan salah. Coba lagi 10 menit lagi.';
			elseif (password_verify(strtolower(trim($typed)), self::PAGE_PASSWORD_HASH)) {
				$session->setSessionVar('cheesaUMUnlocked', (string) time());
				$session->unsetSessionVar('cheesaUMTries');
				$plugin->audit('buka halaman', 'kata sandi benar', $request);
			} else {
				usleep(700000);
				$tries['n']++;
				$session->setSessionVar('cheesaUMTries', json_encode($tries));
				$err = (trim($typed) === '' ? 'Kata sandi belum diisi.' : 'Kata sandi salah.')
					. ($tries['n'] >= 5 ? ' Terkunci 10 menit.' : ' Sisa ' . (5 - $tries['n']) . ' percobaan.');
			}
		}
		if ($err) $session->setSessionVar('cheesaUMLockErr', $err);
		$request->redirectUrl($plugin->pageUrl($request));
	}

	public function lock($args, $request) {
		$request->getSession()->unsetSessionVar('cheesaUMUnlocked');
		$request->redirectUrl(self::$plugin->pageUrl($request));
	}

	/** Site Admin, atau Journal Manager/Editor jurnal yang sedang dibuka. */
	private function canUse($request) {
		if (Validation::isSiteAdmin()) return true;
		$c = $request->getContext(); $u = $request->getUser();
		return $c && $u && $u->hasRole([ROLE_ID_MANAGER], (int) $c->getId());
	}

	/** Subquery: user_id yang punya peran di jurnal $cid. */
	private function inJournal($cid) {
		return function ($s) use ($cid) {
			$s->select('uug.user_id')->from('user_user_groups as uug')
				->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
				->where('ug.context_id', $cid);
		};
	}

	/**
	 * Alasan akun TIDAK boleh diubah oleh pengguna ini ('' = boleh).
	 * Editor (bukan Site Admin) tidak boleh menyentuh akun Site Admin, Manager jurnal ini,
	 * atau akun yang juga terdaftar di jurnal lain.
	 */
	private function lockReason($id, $me, array $info, $siteAdmin, $cid) {
		if ($siteAdmin) return '';
		if (!empty($info['admin'])) return 'Akun Site Administrator — hanya Site Admin yang bisa mengubahnya.';
		if ($id === $me) return '';
		$others = array_diff(isset($info['contexts']) ? $info['contexts'] : [], [$cid]);
		if ($others) {
			$paths = Capsule::table('journals')->whereIn('journal_id', $others)->pluck('path')->all();
			return 'Akun ini juga terdaftar di jurnal lain (' . implode(', ', $paths) . ') — hanya Site Admin yang bisa mengubahnya.';
		}
		if (in_array($cid, isset($info['managerIn']) ? $info['managerIn'] : [], true)) return 'Akun Manager/Editor jurnal ini — hanya Site Admin yang bisa mengubahnya.';
		return '';
	}

	/* ------------------------------------------------------------------ halaman */

	public function index($args, $request) {
		$plugin = self::$plugin;
		$tm = TemplateManager::getManager($request);
		if (!$this->canUse($request)) {
			$tm->assign($this->common($request) + ['umDenied' => true, 'umLocked' => false]);
			return $tm->display($plugin->getTemplateResource('page.tpl'));
		}
		if (!$this->isUnlocked($request)) {
			$session = $request->getSession();
			$lockErr = (string) $session->getSessionVar('cheesaUMLockErr');
			$session->unsetSessionVar('cheesaUMLockErr');
			$tm->assign($this->common($request) + [
				'umDenied' => false,
				'umLocked' => true,
				'umLockErr' => $lockErr,
				'umUnlockUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, CheesaUserManagerPlugin::PAGE, 'unlock'),
			]);
			return $tm->display($plugin->getTemplateResource('page.tpl'));
		}
		import('lib.pkp.classes.security.Validation');
		$siteAdmin = Validation::isSiteAdmin();
		$cid = (int) $request->getContext()->getId();
		$q = trim((string) $request->getUserVar('q'));
		$page = max(1, (int) $request->getUserVar('p'));

		$base = Capsule::table('users as u');
		if (!$siteAdmin) $base->whereIn('u.user_id', $this->inJournal($cid));
		$allQuery = clone $base;
		if ($q !== '') {
			$like = '%' . addcslashes($q, '%_\\') . '%';
			$base->where(function ($w) use ($like, $q) {
				$w->where('u.username', 'like', $like)
					->orWhere('u.email', 'like', $like)
					->orWhereIn('u.user_id', function ($s) use ($like) {
						$s->select('user_id')->from('user_settings')
							->whereIn('setting_name', ['givenName', 'familyName'])
							->where('setting_value', 'like', $like);
					});
				if (ctype_digit($q)) $w->orWhere('u.user_id', (int) $q);
			});
		}
		$total = (clone $base)->count();
		$pages = max(1, (int) ceil($total / self::PER_PAGE));
		$page = min($page, $pages);
		$list = $base->orderBy('u.user_id', 'desc')
			->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE)
			->get(['u.user_id', 'u.username', 'u.email', 'u.date_registered', 'u.date_last_login', 'u.disabled']);

		$ids = [];
		foreach ($list as $r) $ids[] = (int) $r->user_id;
		$names = $this->names($ids);
		$roles = $this->roles($ids);
		$activity = $this->activity($ids);
		$me = (int) $request->getUser()->getId();

		$rows = [];
		foreach ($list as $r) {
			$id = (int) $r->user_id;
			$act = isset($activity[$id]) ? $activity[$id] : [];
			$info = isset($roles[$id]) ? $roles[$id] : [];
			$isAdmin = !empty($info['admin']);
			$lock = $this->lockReason($id, $me, $info, $siteAdmin, $cid);
			// "Masuk sebagai" (login as): hanya Site Admin, hanya ke akun yang boleh ia kelola, bukan Site Admin lain / diri sendiri / akun nonaktif
			// Login-as: untuk Site Admin dan Journal Manager/Editor (peran Manager) — bukan Section Editor dsb,
			// karena peran selain Manager tidak bisa membuka halaman ini. Scope target dijaga canAdminister + lock.
			$canLoginAs = $id !== $me && !(bool) $r->disabled && $lock === '' && Validation::canAdminister($id, $me);
			$rows[] = [
				'id' => $id,
				'username' => $r->username,
				'email' => $r->email,
				'name' => isset($names[$id]) ? $names[$id] : '',
				'registered' => $r->date_registered ? substr($r->date_registered, 0, 10) : '',
				'lastLogin' => $r->date_last_login ? substr($r->date_last_login, 0, 10) : '',
				'disabled' => (bool) $r->disabled,
				'roles' => isset($roles[$id]['list']) ? $roles[$id]['list'] : [],
				'isAdmin' => $isAdmin,
				'isMe' => $id === $me,
				'activity' => $act,
				'activityText' => implode(', ', array_map(function ($k, $n) { return $n . ' ' . $k; }, array_keys($act), $act)),
				'lock' => $lock,
				'canEditName' => $lock === '',
				'canDelete' => !$act && !$isAdmin && $id !== $me && $lock === '',
				'canMerge' => !$isAdmin && $id !== $me && $lock === '',
				'canLoginAs' => $canLoginAs,
			];
		}

		$session = $request->getSession();
		$flash = $session->getSessionVar('cheesaUM');
		$session->unsetSessionVar('cheesaUM');
		$flash = $flash ? json_decode($flash, true) : null;

		$keep = $q !== '' ? ['q' => $q] : [];
		$tm->assign($this->common($request) + [
			'umDenied' => false,
			'umLocked' => false,
			'umLockUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, CheesaUserManagerPlugin::PAGE, 'lock'),
			'umRows' => $rows,
			'umQ' => $q,
			'umTotal' => $total,
			'umAll' => $q === '' ? $total : $allQuery->count(),
			'umScoped' => !$siteAdmin,
			'umGroups' => array_map(function ($g) use ($flash) {
				$g['checked'] = $flash && !empty($flash['old']['groups']) && in_array($g['id'], $flash['old']['groups']);
				return $g;
			}, $this->journalGroups($cid)),
			'umOld' => $flash && isset($flash['old']) ? $flash['old'] : [],
			'umPage' => $page,
			'umPages' => $pages,
			'umPrev' => $page > 1 ? $plugin->pageUrl($request, $keep + ['p' => $page - 1]) : '',
			'umNext' => $page < $pages ? $plugin->pageUrl($request, $keep + ['p' => $page + 1]) : '',
			'umSearchUrl' => $plugin->pageUrl($request),
			'umSaveUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, CheesaUserManagerPlugin::PAGE, 'save'),
			'umFlash' => $flash,
			'umLog' => array_slice($siteAdmin ? $plugin->auditLog() : array_values(array_filter($plugin->auditLog(), function ($l) use ($cid) {
				return isset($l['ctx']) && (int) $l['ctx'] === $cid;   // editor hanya melihat catatan jurnalnya
			})), 0, 30),
		]);
		$tm->display($plugin->getTemplateResource('page.tpl'));
	}

	/* ------------------------------------------------------------------ aksi */

	public function save($args, $request) {
		$plugin = self::$plugin;
		$back = array_filter(['q' => (string) $request->getUserVar('q'), 'p' => (string) $request->getUserVar('p')], 'strlen');
		$ok = '';
		$err = '';
		try {
			if (!$this->canUse($request)) throw new Exception('Hanya Site Administrator atau Journal Manager/Editor jurnal ini yang boleh mengubah akun.');
			if (!$this->isUnlocked($request)) throw new Exception('Halaman terkunci (30 menit tanpa aktivitas). Masukkan kata sandi lagi, lalu ulangi tindakan Anda.');
			if (!$request->isPost() || !$request->checkCSRF()) throw new Exception('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
			$siteAdmin = Validation::isSiteAdmin();
			$cid = (int) $request->getContext()->getId();
			$verb = (string) $request->getUserVar('verb');
			$userDao = DAORegistry::getDAO('UserDAO');
			if ($verb === 'add') {
				$ok = $this->addUser($request, $cid);
				$request->getSession()->setSessionVar('cheesaUM', json_encode(['ok' => $ok, 'err' => '']));
				return $request->redirectUrl($plugin->pageUrl($request, ['q' => trim((string) $request->getUserVar('username'))]));
			}
			$user = $userDao->getById((int) $request->getUserVar('userId'), true);
			if (!$user) throw new Exception('Akun tidak ditemukan.');
			$id = (int) $user->getId();
			$old = $user->getUsername();
			$me = (int) $request->getUser()->getId();
			// periksa ulang hak atas akun ini di server (jangan percaya tombol di halaman)
			$info = $this->roles([$id]);
			$info = isset($info[$id]) ? $info[$id] : [];
			if (!$siteAdmin) {
				if ($id !== $me && !in_array($cid, isset($info['contexts']) ? $info['contexts'] : [], true)) throw new Exception('Akun ini tidak terdaftar di jurnal ini.');
				if ($why = $this->lockReason($id, $me, $info, false, $cid)) throw new Exception($why);
			}

			if ($verb === 'username') {
				$new = trim((string) $request->getUserVar('newUsername'));
				if (!preg_match('/^[a-z0-9]+([\-_][a-z0-9]+)*$/i', $new)) throw new Exception('Username hanya boleh huruf, angka, dan tanda - atau _ di antara huruf/angka (tanpa spasi).');
				if (strlen($new) > 32) throw new Exception('Username maksimal 32 karakter.');
				if ($new === $old) throw new Exception('Username baru sama dengan yang lama.');
				$taken = Capsule::table('users')->whereRaw('LOWER(username) = ?', [strtolower($new)])->where('user_id', '<>', $id)->exists();
				if ($taken) throw new Exception('Username "' . $new . '" sudah dipakai akun lain.');
				$user->setUsername($new);
				$userDao->updateObject($user);
				$plugin->audit('ubah username', "#$id $old → $new", $request);
				$ok = "Username #$id diubah dari \"$old\" menjadi \"$new\". Beri tahu pemilik akun bahwa ia sekarang login dengan username baru.";

			} elseif ($verb === 'delete') {
				$this->guardTarget($id, $me, (string) $request->getUserVar('confirm'), $old, 'dihapus');
				$act = $this->activity([$id]);
				if (!empty($act[$id])) {
					$list = [];
					foreach ($act[$id] as $k => $n) $list[] = "$n $k";
					throw new Exception('Akun "' . $old . '" punya jejak aktivitas (' . implode(', ', $list) . '), jadi tidak boleh dihapus langsung. Gunakan "Gabungkan" ke akun lain.');
				}
				$email = $user->getEmail();
				Capsule::connection()->transaction(function () use ($id) {
					$schema = Capsule::schema();
					foreach (self::CLEANUP as $t) {
						if ($schema->hasTable($t) && $schema->hasColumn($t, 'user_id')) Capsule::table($t)->where('user_id', $id)->delete();
					}
					Capsule::table('users')->where('user_id', $id)->delete();
				});
				$plugin->audit('hapus akun', "#$id $old <$email>", $request);
				$ok = "Akun \"$old\" ($email) sudah dihapus.";

			} elseif ($verb === 'merge') {
				$this->guardTarget($id, $me, (string) $request->getUserVar('confirm'), $old, 'digabungkan');
				$targetName = trim((string) $request->getUserVar('target'));
				$target = $targetName !== '' ? $userDao->getByUsername($targetName, true) : null;
				if (!$target) throw new Exception('Akun tujuan "' . $targetName . '" tidak ditemukan.');
				if ((int) $target->getId() === $id) throw new Exception('Akun tujuan tidak boleh sama dengan akun yang digabungkan.');
				if (!$siteAdmin) {
					$tInfo = $this->roles([(int) $target->getId()]);
					$tCtx = isset($tInfo[(int) $target->getId()]['contexts']) ? $tInfo[(int) $target->getId()]['contexts'] : [];
					if (!in_array($cid, $tCtx, true)) throw new Exception('Akun tujuan "' . $target->getUsername() . '" tidak terdaftar di jurnal ini. Hanya Site Admin yang bisa menggabungkan ke akun di luar jurnal ini.');
				}
				import('classes.user.UserAction');
				$ua = new UserAction();
				$ua->mergeUsers($id, (int) $target->getId());
				$plugin->audit('gabungkan akun', "#$id $old → #" . $target->getId() . ' ' . $target->getUsername(), $request);
				$ok = "Akun \"$old\" digabungkan ke \"" . $target->getUsername() . '". Semua naskah, review, dan peran dipindahkan; akun lama dihapus.';

			} elseif ($verb === 'loginas') {
				import('lib.pkp.classes.security.Validation');
				// hanya Site Admin atau Journal Manager/Editor (peran Manager) yang boleh — canUse() sudah menjamin ini
				if (!$siteAdmin && !$request->getUser()->hasRole([ROLE_ID_MANAGER], $cid)) throw new Exception('Hanya Site Administrator atau Journal Manager/Editor yang bisa masuk sebagai pengguna lain.');
				if ($id === $me) throw new Exception('Tidak bisa masuk sebagai akun Anda sendiri.');
				if (!Validation::canAdminister($id, $me)) throw new Exception('Tidak bisa masuk sebagai akun ini (akun Site Administrator atau akun di luar jurnal Anda tidak diizinkan).');
				if ($user->getDisabled()) throw new Exception('Akun "' . $old . '" nonaktif.');
				$session = $request->getSession();
				if ($session->getSessionVar('signedInAs')) throw new Exception('Anda sedang masuk sebagai pengguna lain. Keluar dulu lewat pemberitahuan OJS di atas halaman, baru coba lagi.');
				// CATAT DULU atas nama admin asli, sebelum sesi berpindah — inilah jejak login-as yang bisa ditelusuri
				$plugin->audit('masuk sebagai (login as)', '#' . $me . ' ' . $request->getUser()->getUsername() . ' → #' . $id . ' ' . $old . ' <' . $user->getEmail() . '>', $request);
				$session->setSessionVar('signedInAs', (int) $me);
				$session->setSessionVar('username', $user->getUsername());
				$session->setUserId((int) $id);
				DAORegistry::getDAO('SessionDAO')->updateObject($session);
				return $request->redirectUrl($request->getDispatcher()->url($request, ROUTE_PAGE, null, 'submissions'));

			} else {
				throw new Exception('Aksi tidak dikenal.');
			}
		} catch (\Throwable $e) {
			$err = $e->getMessage();
		}
		$old = [];
		if ($err && (string) $request->getUserVar('verb') === 'add') {
			// isian formulir dikembalikan (kecuali password) supaya tidak perlu mengetik ulang
			foreach (['username', 'email', 'givenName', 'familyName', 'affiliation', 'orcid', 'interests'] as $f) $old[$f] = (string) $request->getUserVar($f);
			$old['sendEmail'] = (bool) $request->getUserVar('sendEmail');
			$old['groups'] = array_map('intval', (array) $request->getUserVar('groups'));
			$old['open'] = true;
		}
		$request->getSession()->setSessionVar('cheesaUM', json_encode(['ok' => $ok, 'err' => $err, 'old' => $old]));
		$request->redirectUrl($plugin->pageUrl($request, $back ?: null));
	}

	/* ------------------------------------------------------------------ tambah akun */

	/** @return string pesan sukses; melempar Exception bila isian tidak valid */
	private function addUser($request, $cid) {
		$plugin = self::$plugin;
		$userDao = DAORegistry::getDAO('UserDAO');
		$username = trim((string) $request->getUserVar('username'));
		$email = trim((string) $request->getUserVar('email'));
		$given = trim((string) $request->getUserVar('givenName'));
		$family = trim((string) $request->getUserVar('familyName'));
		$affiliation = trim((string) $request->getUserVar('affiliation'));
		// password dikirim base64 di "npk" (server ini membuang parameter yang bernama password)
		$npk = (string) $request->getUserVar('npk');
		$password = $npk !== '' ? (string) base64_decode(strtr($npk, '-_', '+/'), true) : '';
		$mustChange = (bool) $request->getUserVar('mustChange');
		$sendEmail = (bool) $request->getUserVar('sendEmail');
		$orcid = $this->normalizeOrcid((string) $request->getUserVar('orcid'));
		$interests = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $request->getUserVar('interests'))), 'strlen')));
		$groups = array_unique(array_map('intval', (array) $request->getUserVar('groups')));

		if (!preg_match('/^[a-z0-9]+([\-_][a-z0-9]+)*$/i', $username)) throw new Exception('Username hanya boleh huruf, angka, dan tanda - atau _ di antara huruf/angka (tanpa spasi).');
		if (strlen($username) > 32) throw new Exception('Username maksimal 32 karakter.');
		if (Capsule::table('users')->whereRaw('LOWER(username) = ?', [strtolower($username)])->exists()) throw new Exception('Username "' . $username . '" sudah dipakai akun lain.');
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Alamat email tidak valid.');
		if (Capsule::table('users')->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) throw new Exception('Email "' . $email . '" sudah dipakai akun lain. Cari akun itu lalu tambahkan perannya lewat Users & Roles.');
		if ($given === '') throw new Exception('Nama depan wajib diisi.');
		$min = (int) Config::getVar('security', 'min_password_length') ?: 6;
		if (strlen($password) < $min) throw new Exception('Password minimal ' . $min . ' karakter.');
		if (!$groups) throw new Exception('Pilih minimal satu peran di jurnal ini.');
		if ($orcid === false) throw new Exception('ORCID iD tidak valid. Formatnya 0000-0000-0000-0000 (angka terakhir boleh X), dan angka kontrolnya harus cocok.');
		$allowed = [];
		foreach ($this->journalGroups($cid) as $g) $allowed[$g['id']] = true;
		foreach ($groups as $gid) if (!isset($allowed[$gid])) throw new Exception('Peran yang dipilih tidak valid untuk jurnal ini.');

		$locale = AppLocale::getPrimaryLocale();
		$user = $userDao->newDataObject();
		$user->setUsername($username);
		$user->setEmail($email);
		$user->setGivenName($given, $locale);
		$user->setFamilyName($family, $locale);
		if ($affiliation !== '') $user->setAffiliation($affiliation, $locale);
		if ($orcid) $user->setOrcid($orcid);
		$user->setPassword(Validation::encryptCredentials($username, $password));
		$user->setMustChangePassword($mustChange);
		$user->setDateRegistered(Core::getCurrentDate());
		$user->setDisabled(false);
		$user->setInlineHelp(1);
		$userDao->insertObject($user);
		$newId = (int) $user->getId();
		if (!$newId) throw new Exception('Akun gagal dibuat (database tidak mengembalikan ID).');

		if ($interests) {
			import('lib.pkp.classes.user.InterestManager');
			(new InterestManager())->setInterestsForUser($user, $interests);
		}

		$ugDao = DAORegistry::getDAO('UserGroupDAO');
		$names = [];
		foreach ($groups as $gid) {
			$ugDao->assignUserToGroup($newId, $gid);
			foreach ($this->journalGroups($cid) as $g) if ($g['id'] === $gid) $names[] = $g['name'];
		}
		$mailNote = '';
		if ($sendEmail) {
			$sent = false;
			try { $sent = $this->sendWelcome($request, $user, $username, $password); }
			catch (\Throwable $e) { error_log('cheesaUserManager mail: ' . $e->getMessage()); }
			$mailNote = $sent ? ' Email selamat datang (berisi username & password) terkirim ke ' . $email . '.'
				: ' Email GAGAL dikirim — periksa pengaturan email server, lalu sampaikan username & password secara manual.';
		} else {
			$mailNote = ' Email tidak dikirim; sampaikan username & password ke pengguna.';
		}
		$plugin->audit('tambah akun', "#$newId $username <$email> — " . implode(', ', $names) . ($sendEmail ? ' — email ' . (strpos($mailNote, 'GAGAL') ? 'gagal' : 'terkirim') : ''), $request);
		return "Akun \"$username\" dibuat dengan peran " . implode(', ', $names) . '.'
			. ($mustChange ? ' Pengguna wajib mengganti password saat login pertama.' : '')
			. $mailNote;
	}

	/**
	 * Email selamat datang memakai template bawaan OJS "USER_REGISTER"
	 * (bisa diubah di Settings > Workflow > Emails), sama seperti tambah pengguna di Users & Roles.
	 */
	private function sendWelcome($request, $user, $username, $password) {
		$context = $request->getContext();
		import('lib.pkp.classes.mail.MailTemplate');
		$mail = new MailTemplate('USER_REGISTER', null, $context, false);
		if ($context && $context->getData('contactEmail')) $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
		$mail->assignParams([
			'username' => $username,
			'password' => $password,
			'userFullName' => $user->getFullName(),
		]);
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		return (bool) $mail->send();
	}

	/**
	 * ORCID iD -> 'https://orcid.org/0000-0000-0000-0000'; '' bila kosong; false bila tidak valid.
	 * Angka kontrol diperiksa (ISO 7064 MOD 11-2).
	 */
	private function normalizeOrcid($in) {
		$in = trim($in);
		if ($in === '') return '';
		if (!preg_match('/(\d{4})-?(\d{4})-?(\d{4})-?(\d{3}[\dXx])\s*$/', $in, $m)) return false;
		$id = $m[1] . $m[2] . $m[3] . strtoupper($m[4]);
		$total = 0;
		for ($i = 0; $i < 15; $i++) $total = ($total + (int) $id[$i]) * 2;
		$check = (12 - $total % 11) % 11;
		if (($check === 10 ? 'X' : (string) $check) !== $id[15]) return false;
		return 'https://orcid.org/' . implode('-', str_split($id, 4));
	}

	/** @return array [['id'=>, 'name'=>, 'role'=>], ...] peran (user group) di jurnal ini */
	private function journalGroups($cid) {
		static $cache = [];
		if (isset($cache[$cid])) return $cache[$cid];
		$out = [];
		$res = DAORegistry::getDAO('UserGroupDAO')->getByContextId($cid);
		while ($g = $res->next()) {
			$rid = (int) $g->getRoleId();
			$out[] = ['id' => (int) $g->getId(), 'name' => $g->getLocalizedName(), 'role' => isset(self::ROLES[$rid]) ? self::ROLES[$rid] : ''];
		}
		return $cache[$cid] = $out;
	}

	/* ------------------------------------------------------------------ bantuan */

	/** Pengaman bersama untuk hapus & gabung. */
	private function guardTarget($id, $me, $confirm, $username, $what) {
		if ($id === $me) throw new Exception('Akun Anda sendiri tidak bisa ' . $what . ' dari sini.');
		if ($this->isSiteAdmin($id)) throw new Exception('Akun Site Administrator tidak bisa ' . $what . '. Cabut dulu peran Site Admin-nya.');
		if (strtolower(trim($confirm)) !== strtolower($username)) throw new Exception('Konfirmasi tidak cocok: ketik username "' . $username . '" persis untuk melanjutkan.');
	}

	private function isSiteAdmin($id) {
		return Capsule::table('user_user_groups as uug')
			->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
			->where('uug.user_id', $id)->where('ug.role_id', ROLE_ID_SITE_ADMIN)->exists();
	}

	/** @return array user_id => nama lengkap */
	private function names(array $ids) {
		if (!$ids) return [];
		$parts = [];
		$rows = Capsule::table('user_settings')->whereIn('user_id', $ids)
			->whereIn('setting_name', ['givenName', 'familyName'])->where('setting_value', '<>', '')
			->get(['user_id', 'setting_name', 'setting_value', 'locale']);
		$primary = AppLocale::getPrimaryLocale();
		foreach ($rows as $r) {
			$k = (int) $r->user_id;
			// utamakan locale utama; locale lain hanya mengisi yang kosong
			if (!isset($parts[$k][$r->setting_name]) || $r->locale === $primary) $parts[$k][$r->setting_name] = $r->setting_value;
		}
		$out = [];
		foreach ($parts as $k => $p) $out[$k] = trim((isset($p['givenName']) ? $p['givenName'] : '') . ' ' . (isset($p['familyName']) ? $p['familyName'] : ''));
		return $out;
	}

	/** @return array user_id => ['admin' => bool, 'list' => ['Penulis · cheesa', ...]] */
	private function roles(array $ids) {
		if (!$ids) return [];
		$paths = Capsule::table('journals')->pluck('path', 'journal_id')->all();
		$rows = Capsule::table('user_user_groups as uug')
			->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
			->whereIn('uug.user_id', $ids)->get(['uug.user_id', 'ug.role_id', 'ug.context_id']);
		$out = [];
		foreach ($rows as $r) {
			$k = (int) $r->user_id;
			$role = isset(self::ROLES[(int) $r->role_id]) ? self::ROLES[(int) $r->role_id] : 'Peran ' . $r->role_id;
			if ((int) $r->role_id === ROLE_ID_SITE_ADMIN) { $out[$k]['admin'] = true; continue; }
			$out[$k]['contexts'][(int) $r->context_id] = (int) $r->context_id;
			if ((int) $r->role_id === ROLE_ID_MANAGER) $out[$k]['managerIn'][(int) $r->context_id] = (int) $r->context_id;
			$label = $role . ((int) $r->context_id && isset($paths[$r->context_id]) ? ' · ' . $paths[$r->context_id] : '');
			$out[$k]['list'][$label] = $label;
		}
		foreach ($out as $k => $v) {
			$out[$k]['list'] = isset($v['list']) ? array_values($v['list']) : [];
			$out[$k]['contexts'] = isset($v['contexts']) ? array_values($v['contexts']) : [];
			$out[$k]['managerIn'] = isset($v['managerIn']) ? array_values($v['managerIn']) : [];
		}
		return $out;
	}

	/** @return array user_id => ['review' => 3, ...] (hanya yang > 0) */
	private function activity(array $ids) {
		if (!$ids) return [];
		$schema = Capsule::schema();
		$out = [];
		foreach (self::ACTIVITY as $table => $def) {
			list($col, $label) = $def;
			if (!$schema->hasTable($table) || !$schema->hasColumn($table, $col)) continue;
			$rows = Capsule::table($table)->whereIn($col, $ids)->groupBy($col)
				->select([$col . ' as uid', Capsule::raw('COUNT(*) as n')])->get();
			foreach ($rows as $r) if ((int) $r->n > 0) $out[(int) $r->uid][$label] = (int) $r->n;
		}
		return $out;
	}
}
