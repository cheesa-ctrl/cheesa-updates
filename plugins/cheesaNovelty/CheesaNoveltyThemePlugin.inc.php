<?php

/**
 * @file plugins/themes/cheesaNovelty/CheesaNoveltyThemePlugin.inc.php
 *
 * CHEESA Theme — child theme dari Default Theme OJS 3.3.
 * Tata letak 3 kolom bergaya portal jurnal modern (header + menu ikon, logo indeksasi,
 * tab Current Issue / Upcoming Issue, kartu artikel bergambar, panel tombol cepat,
 * Author Diversity dengan grafik batang) memakai warna CHEESA.
 */

import('lib.pkp.classes.plugins.ThemePlugin');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class CheesaNoveltyThemePlugin extends ThemePlugin {

	/** @var array konfigurasi untuk script tema */
	private $_cfg = [];

	/**
	 * Tetap aktif saat instalasi/upgrade agar tidak memutus tampilan.
	 */
	public function isActive() {
		if (defined('SESSION_DISABLE_INIT')) return true;
		return parent::isActive();
	}

	/** @var string alasan lisensi tidak sah ('' = sah) */
	private $_license = null;

	private function license() {
		if ($this->_license === null) $this->_license = CheesaLicense::checkRequest(__DIR__);
		return $this->_license;
	}

	public function init() {
		$this->setParent('defaultthemeplugin');
		// Lisensi: di domain/jurnal lain tema ini tampil seperti tema bawaan OJS (tanpa gaya & fitur CHEESA).
		if ($this->license()) return;

		/* ---------------- Opsi tema (Settings > Website > Appearance > Theme) ---------------- */
		$this->addOption('chNavy', 'FieldColor', [
			'label' => 'Warna utama (navy)',
			'description' => 'Warna header, menu, judul blok, dan tombol.',
			'default' => '#030271',
		]);
		$this->addOption('chAccent', 'FieldColor', [
			'label' => 'Warna aksen (area putih)',
			'description' => 'Ikon kartu artikel, garis aktif, penanda, dan sorotan pada area berlatar putih. Warna teks di atas aksen dipilih otomatis (putih/gelap).',
			'default' => '#ed991f',
		]);
		$this->addOption('chHeaderAccent', 'FieldColor', [
			'label' => 'Warna sorotan header & menu (di atas navy)',
			'description' => 'Angka ISSN, ikon menu, hover menu, tombol Login/pengguna, tombol Search, dan pita judul artikel. Bila warnanya terlalu gelap di atas warna utama, otomatis dipakai versi lebih terang dari warna yang sama agar tetap terlihat.',
			'default' => '#ed991f',
		]);
		$this->addOption('chLeftBlocks', 'FieldText', [
			'label' => 'Blok kolom KIRI (beranda)',
			'description' => 'Judul blok sidebar, dipisah koma, urut dari atas. Huruf besar/kecil tidak berpengaruh.',
			'default' => 'Additional Information, Journal Archive, Diversity of Authors',
		]);
		$this->addOption('chRightBlocks', 'FieldText', [
			'label' => 'Blok kolom KANAN (beranda)',
			'description' => 'Judul blok sidebar, dipisah koma. Blok yang tidak tercantum ditaruh di bawah kolom kanan.',
			'default' => 'Submission Menu, CHEESA Profiles, Additional Menu, Visitors',
		]);
		$this->addOption('chSidebarPages', 'FieldTextarea', [
			'label' => 'Blok sidebar kiri per halaman (selain beranda)',
			'description' => 'Satu baris per aturan: halaman | blok, blok, ... Halaman = bagian alamat setelah /cheesa/ (boleh beberapa, pisahkan koma). Tanda * = semua halaman lain. Nama blok = judul blok (Additional Information, Submission Menu, Published Volumes, Article Info, Actions, dll). Kosongkan untuk susunan bawaan.',
			'default' => "* | Additional Information, Actions\narticle/view | Article Info, Published Volumes\nissue | Published Volumes, Additional Information\nabout/submissions, authorguidelines | Submission Menu, Additional Information, Actions",
		]);
		$this->addOption('chCardStats', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Kartu artikel: negara penulis & statistik dari OJS',
			'description' => 'Negara diambil dari metadata Country penulis; Views & Downloads dari statistik OJS.',
			'options' => [['value' => 'on', 'label' => 'Tampilkan'], ['value' => 'off', 'label' => 'Sembunyikan']],
			'default' => 'on',
		]);
		$this->addOption('chCorrEmail', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Penulis korespondensi di halaman artikel',
			'description' => 'Penulis korespondensi = Primary Contact pada metadata artikel.',
			'options' => [
				['value' => 'on', 'label' => 'Tandai dan tautkan ke email penulis'],
				['value' => 'mark', 'label' => 'Tandai saja (email tidak ditampilkan)'],
			],
			'default' => 'on',
		]);
		$this->addOption('chArticleFacts', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Halaman artikel: tanggal proses, editor, hak cipta & lisensi',
			'description' => 'Received, Revised, Accepted, Available online, Published (dari riwayat naskah), Editor(s) yang menangani, serta Copyright dan lisensi.',
			'options' => [
				['value' => 'on', 'label' => 'Tampilkan semua'],
				['value' => 'noeditor', 'label' => 'Tampilkan tanpa nama editor'],
				['value' => 'off', 'label' => 'Sembunyikan'],
			],
			'default' => 'on',
		]);
		$this->addOption('chSubmitUrl', 'FieldText', [
			'label' => 'Tautan tombol "Submit Your Paper"',
			'description' => 'Kosongkan untuk memakai halaman Submissions jurnal.',
			'default' => '',
		]);
		$this->addOption('chReviewerUrl', 'FieldText', [
			'label' => 'Tautan tombol "Become a Reviewer"',
			'description' => 'Kosongkan untuk memakai halaman /rolereg.',
			'default' => '',
		]);
		$this->addOption('chSocial', 'FieldTextarea', [
			'label' => 'Media sosial (panel tombol cepat)',
			'description' => 'Satu baris per akun: Nama|URL. Nama yang dikenali: Instagram, Whatsapp, Facebook, Twitter, X, Youtube, Linkedin, Telegram, Email.',
			'default' => "Instagram|https://www.instagram.com/\nWhatsapp|https://wa.me/\nEmail|mailto:cheesa@unipma.ac.id",
		]);
		$this->addOption('chIndexing', 'FieldTextarea', [
			'label' => 'Logo indeksasi (carousel di beranda)',
			'description' => 'Satu baris per logo: Nama|URL gambar logo|URL tautan. Kosongkan untuk membuat otomatis dari blok "CHEESA Profiles" (tampil sebagai label teks).',
			'default' => '',
		]);
		$this->addOption('chUpcoming', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Tab "Upcoming Issue" di beranda',
			'options' => [
				['value' => 'on', 'label' => 'Tampilkan (terbitan berikutnya yang belum dipublikasikan + daftar artikel terjadwal)'],
				['value' => 'off', 'label' => 'Sembunyikan'],
			],
			'default' => 'on',
		]);
		$this->addOption('chHomeArticles', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Daftar artikel Current Issue di beranda',
			'options' => [
				['value' => 'on', 'label' => 'Tampilkan sebagai kartu bergambar (gaya portal)'],
				['value' => 'off', 'label' => 'Sembunyikan (hanya info terbitan + tombol See Current Issue)'],
			],
			'default' => 'on',
		]);
		$this->addOption('chLegacy', 'FieldOptions', [
			'type' => 'radio',
			'label' => 'Kode CHEESA lama (stylesheet + script)',
			'description' => 'Fitur lama (slider pengumuman, Journal Archive, bendera, tab artikel, Cite Article, statistik negara, dsb.) sudah dibawa di dalam tema. Bila kode lama masih ada di pengaturan Appearance, bagian yang sama di tema dilewati otomatis.',
			'options' => [
				['value' => 'on', 'label' => 'Muat dari tema (disarankan)'],
				['value' => 'off', 'label' => 'Jangan muat (pakai kode di pengaturan Appearance saja)'],
			],
			'default' => 'on',
		]);

		/* ---------------- Gaya ---------------- */
		$this->addStyle('cheesa-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700;800&family=Noto+Serif:wght@400;700&display=swap', ['baseUrl' => '']);
		if ($this->getOption('chLegacy') !== 'off') {
			$this->addStyle('cheesa-legacy', 'styles/cheesa-legacy.css');
		}
		$this->addStyle('cheesa-theme', 'styles/cheesa-theme.css');

		$navy = $this->_color($this->getOption('chNavy'), '#030271');
		$accent = $this->_color($this->getOption('chAccent'), '#ed991f');
		// warna pilihan pengelola tetap dipakai; bila terlalu gelap di atas warna utama,
		// dipakai versi lebih terang dari warna yang SAMA (mis. biru -> biru muda) supaya ISSN, ikon & tombol terlihat
		$hl = $this->_lighten($this->_color($this->getOption('chHeaderAccent'), '#ed991f'), $navy, 4.5);
		$this->addStyle('cheesa-vars', ':root{--nv-navy:' . $navy . ';--nv-accent:' . $accent . ';--nv-on-accent:' . $this->_onColor($accent)
			. ';--nv-accent-base:' . $accent . ';--nv-on-accent-base:' . $this->_onColor($accent) . ';--nv-hl:' . $hl . ';--nv-on-hl:' . $this->_onColor($hl) . ';}', ['inline' => true]);
		if ($this->getOption('chHomeArticles') === 'off') {
			$this->addStyle('cheesa-hide-home-articles', ':root body .page_index_journal .current_issue .sections{display:none!important}', ['inline' => true]);
		}

		/* ---------------- Script ---------------- */
		$cfg = [
			'left' => $this->_list($this->getOption('chLeftBlocks')),
			'right' => $this->_list($this->getOption('chRightBlocks')),
			'submitUrl' => trim((string) $this->getOption('chSubmitUrl')),
			'reviewerUrl' => trim((string) $this->getOption('chReviewerUrl')),
			'social' => $this->_lines($this->getOption('chSocial'), 2),
			'indexing' => $this->_lines($this->getOption('chIndexing'), 3),
			'upcoming' => $this->getOption('chUpcoming') !== 'off',
			'sidebarPages' => $this->_rules($this->getOption('chSidebarPages')),
		];
		$this->_cfg = $cfg;
		// Konfigurasi script disusun saat halaman pembaca ditampilkan (bukan saat API/backend berjalan)
		HookRegistry::register('TemplateManager::display', [$this, 'addConfig']);
		if ($this->getOption('chLegacy') !== 'off') {
			$this->addScript('cheesa-legacy', 'js/cheesa-legacy.js');
		}
		$this->addScript('cheesa-theme', 'js/cheesa-theme.js');

		if ($this->getOption('chCardStats') !== 'off') {
			HookRegistry::register('Templates::Issue::Issue::Article', [$this, 'addSummaryData']);
		}
		// halaman artikel: penulis (afiliasi + negara dari metadata Country) dan DOI
		HookRegistry::register('Templates::Article::Main', [$this, 'addArticleData']);

		/* ---------------- Upcoming Issue (dikirim tersembunyi, disusun jadi tab oleh script) ---------------- */
		if ($this->getOption('chUpcoming') !== 'off') {
			HookRegistry::register('Templates::Index::journal', [$this, 'addUpcomingIssue']);
		}
	}

	/** Hook TemplateManager::display — hanya untuk halaman pembaca (PageRouter). */
	public function addConfig($hookName, $args) {
		static $done = false;
		if ($done) return false;
		try {
			$request = Application::get()->getRequest();
			if (!$request || !is_a($request->getRouter(), 'PKPPageRouter') || !$request->getContext()) return false;
			$done = true;
			$cfg = $this->_cfg;
			$cfg['searchUrl'] = $request->url(null, 'search', 'search');
			$cfg['announcementUrl'] = $request->url(null, 'announcement');
			$cfg['base'] = $request->url(null, 'index');
			// Tautan Login / pengguna dibuat dari server agar selalu tampil di header
			$user = $request->getUser();
			if ($user) {
				$cfg['user'] = [
					'name' => $user->getUsername(),
					'links' => [
						['Dashboard', $request->url(null, 'submissions')],
						['View Profile', $request->url(null, 'user', 'profile')],
					],
				];
				try {
					import('lib.pkp.classes.security.Validation');
					if (class_exists('Validation') && Validation::isSiteAdmin()) $cfg['user']['links'][] = ['Administration', $request->url('index', 'admin')];
				} catch (\Throwable $e) {}
				$cfg['user']['links'][] = ['Logout', $request->url(null, 'login', 'signOut')];
			} else {
				$cfg['user'] = [
					'name' => '',
					'links' => [
						['Login', $request->url(null, 'login')],
						['Register', $request->url(null, 'user', 'register')],
					],
				];
			}
			$args[0]->addJavaScript('cheesa-config', 'window.CHEESA_CFG=' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) . ';',
				['inline' => true, 'contexts' => 'frontend', 'priority' => STYLE_SEQUENCE_CORE]);
		} catch (\Throwable $e) {
			error_log('CHEESA Theme config: ' . $e->getMessage());
		}
		return false;
	}

	public function getDisplayName() {
		return 'CHEESA Theme';
	}

	public function getDescription() {
		return 'Tema 3 kolom bergaya portal jurnal modern untuk CHEESA: menu ikon, logo indeksasi, tab Current/Upcoming Issue, kartu artikel bergambar, panel tombol cepat, dan Author Diversity.' . CheesaLicense::notice($this->license());
	}

	/**
	 * Hook Templates::Index::journal — menambahkan terbitan berikutnya (belum terbit) di beranda.
	 */
	public function addUpcomingIssue($hookName, $args) {
		$output =& $args[2];
		try {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			if (!$context) return false;

			$issueDao = DAORegistry::getDAO('IssueDAO');
			$result = $issueDao->getUnpublishedIssues($context->getId());
			$issue = null; $best = '';
			while ($candidate = $result->next()) {
				// Ambil terbitan belum-terbit yang paling dekat (nomor terkecil setelah terbitan terakhir)
				$k = sprintf('%05d-%05d-%05d', (int) $candidate->getYear(), (int) $candidate->getVolume(), (int) $candidate->getNumber());
				if (!$issue || $k < $best) { $issue = $candidate; $best = $k; }
			}

			$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
			$html = '<section class="ch-upcoming" hidden aria-label="Upcoming Issue">';

			if (!$issue) {
				$html .= '<p class="ch-up-empty">No upcoming issue has been scheduled yet.</p></section>';
				$output .= $html;
				return false;
			}

			$cover = method_exists($issue, 'getLocalizedCoverImageUrl') ? $issue->getLocalizedCoverImageUrl() : '';
			$html .= '<div class="ch-up-head">';
			if ($cover) $html .= '<img class="ch-up-cover" src="' . $h($cover) . '" alt="">';
			$html .= '<div><div class="ch-up-title">' . $h($issue->getIssueIdentification()) . '</div>';
			$html .= '<div class="ch-up-note">Preview — this issue has not been published yet.</div>';
			$desc = $issue->getLocalizedDescription();
			if ($desc) $html .= '<div class="ch-up-desc">' . strip_tags($desc, '<p><br><strong><em><b><i>') . '</div>';
			$html .= '</div></div>';

			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$submissions = Services::get('submission')->getMany([
				'contextId' => $context->getId(),
				'issueIds' => [$issue->getId()],
				'status' => [defined('STATUS_SCHEDULED') ? STATUS_SCHEDULED : 5, defined('STATUS_QUEUED') ? STATUS_QUEUED : 1],
				'count' => 50,
			]);
			$items = '';
			foreach ($submissions as $submission) {
				$pub = $submission->getCurrentPublication();
				if (!$pub || (int) $pub->getData('issueId') !== (int) $issue->getId()) continue;
				$section = $pub->getData('sectionId') ? $sectionDao->getById($pub->getData('sectionId')) : null;
				$names = [];
				foreach ((array) $pub->getData('authors') as $author) $names[] = $author->getFullName();
				$items .= '<li class="ch-up-item">'
					. ($section ? '<span class="ch-up-sec">' . $h($section->getLocalizedTitle()) . '</span>' : '')
					. '<div class="ch-up-art">' . $h($pub->getLocalizedFullTitle()) . '</div>'
					. ($names ? '<div class="ch-up-au"><i class="fa fa-users" aria-hidden="true"></i> ' . $h(implode(', ', $names)) . '</div>' : '')
					. '</li>';
			}
			$html .= $items ? '<h3 class="ch-up-h">Scheduled Articles</h3><ul class="ch-up-list">' . $items . '</ul>'
			                : '<p class="ch-up-empty">Articles for this issue are being prepared.</p>';
			$html .= '</section>';
			$output .= $html;
		} catch (\Throwable $e) {
			// Jangan pernah merusak beranda karena fitur tambahan ini
			error_log('CHEESA Theme upcoming issue: ' . $e->getMessage());
		}
		return false;
	}


	/**
	 * Hook Templates::Issue::Issue::Article — data kartu artikel dari database OJS:
	 * penulis (afiliasi, negara dari metadata Country, ORCID), DOI, Views (abstrak) & Downloads (galley).
	 */
	public function addSummaryData($hookName, $args) {
		$smarty = $args[1]; $output =& $args[2];
		try {
			$article = $smarty->getTemplateVars('article');
			if (!$article || !method_exists($article, 'getCurrentPublication')) return false;
			$pub = $article->getCurrentPublication();
			$authors = [];
			foreach ((array) $pub->getData('authors') as $a) {
				$cc = strtoupper((string) $a->getData('country'));
				$authors[] = [
					'name' => $a->getFullName(),
					'aff' => trim((string) $a->getLocalizedData('affiliation')),
					'cc' => $cc,
					'country' => $cc ? $this->_countryName($cc) : '',
					'orcid' => (string) $a->getData('orcid'),
				];
			}
			$m = $this->_metrics($article->getId());
			$data = [
				'authors' => $authors,
				'doi' => (string) $pub->getStoredPubId('doi'),
				'views' => $m['views'], 'downloads' => $m['downloads'],
				'date' => $pub->getData('datePublished') ? date('Y-m-d', strtotime($pub->getData('datePublished'))) : '',
			];
			$output .= '<script type="application/json" class="ch-sum-data">'
				. json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
				. '</script>';
		} catch (\Throwable $e) {
			error_log('CHEESA Theme card data: ' . $e->getMessage());
		}
		return false;
	}

	/** Hook Templates::Article::Main — data penulis & DOI untuk kepala halaman artikel. */
	public function addArticleData($hookName, $args) {
		$smarty = $args[1]; $output =& $args[2];
		try {
			$article = $smarty->getTemplateVars('article');
			$pub = $smarty->getTemplateVars('publication') ?: ($article ? $article->getCurrentPublication() : null);
			if (!$pub) return false;
			$authors = [];
			$primary = (int) $pub->getData('primaryContactId');
			$showMail = $this->getOption('chCorrEmail') !== 'mark';
			foreach ((array) $pub->getData('authors') as $a) {
				$cc = strtoupper((string) $a->getData('country'));
				$row = [
					'name' => $a->getFullName(),
					'aff' => trim((string) $a->getLocalizedData('affiliation')),
					'cc' => $cc,
					'country' => $cc ? $this->_countryName($cc) : '',
					'orcid' => (string) $a->getData('orcid'),
					'corr' => (int) $a->getId() === $primary,
				];
				if ($row['corr'] && $showMail && $a->getEmail()) $row['email'] = $a->getEmail();
				$authors[] = $row;
			}
			$m = $article ? $this->_metrics($article->getId()) : ['views' => 0, 'downloads' => 0];
			$data = ['authors' => $authors, 'doi' => (string) $pub->getStoredPubId('doi'), 'views' => $m['views'], 'downloads' => $m['downloads']];
			if ($article && $this->getOption('chArticleFacts') !== 'off') {
				$data['facts'] = $this->_facts($article, $pub, $smarty->getTemplateVars('issue'), $this->getOption('chArticleFacts') !== 'noeditor');
			}
			$output .= '<script type="application/json" id="ch-article-data">'
				. json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
				. '</script>';
		} catch (\Throwable $e) {
			error_log('CHEESA Theme article data: ' . $e->getMessage());
		}
		return false;
	}


	/** Tanggal proses naskah, editor yang menangani, hak cipta & lisensi (OJS 3.3). */
	private function _facts($article, $pub, $issue, $withEditors) {
		$fmt = function ($d) { return $d ? date('j M Y', strtotime($d)) : ''; };
		// Published = tanggal terbit artikel (metadata publikasi), bukan tanggal log/terbit ulang issue.
		// Available online = tanggal terbit versi pertama artikel (sama dengan Published bila hanya satu versi).
		$first = $pub->getData('datePublished');
		foreach ((array) $article->getData('publications') as $p) {
			$d = is_object($p) ? $p->getData('datePublished') : null;
			if ($d && (!$first || strtotime($d) < strtotime($first))) $first = $d;
		}
		$f = ['received' => $fmt($article->getDateSubmitted()), 'revised' => '', 'accepted' => '', 'online' => $fmt($first), 'published' => $fmt($pub->getData('datePublished'))];
		import('lib.pkp.classes.db.DAO');
		$dao = new DAO();
		$sid = (int) $article->getId();
		try {   // revisi terakhir yang diunggah penulis (file stage 15 = review revision)
			foreach ($dao->retrieve('SELECT MAX(created_at) AS d FROM submission_files WHERE submission_id = ? AND file_stage = ?', [$sid, 15]) as $r) { $r = (array) $r; $f['revised'] = $fmt($r['d']); }
		} catch (\Throwable $e) {}
		try {   // keputusan editor: Accept (1)
			foreach ($dao->retrieve('SELECT MAX(date_decided) AS d FROM edit_decisions WHERE submission_id = ? AND decision = ?', [$sid, 1]) as $r) { $r = (array) $r; $f['accepted'] = $fmt($r['d']); }
		} catch (\Throwable $e) {}
		$editors = [];
		if ($withEditors) {
			try {   // editor & section editor yang ditugaskan pada naskah (role 16 = manager/editor, 17 = section editor)
				$userDao = DAORegistry::getDAO('UserDAO');
				foreach ($dao->retrieve('SELECT DISTINCT sa.user_id FROM stage_assignments sa JOIN user_groups ug ON ug.user_group_id = sa.user_group_id WHERE sa.submission_id = ? AND ug.role_id IN (?, ?) AND sa.recommend_only = 0', [$sid, 16, 17]) as $r) {
					$r = (array) $r;
					$u = $userDao->getById((int) $r['user_id']);
					if ($u) $editors[] = $u->getFullName();
				}
			} catch (\Throwable $e) {}
		}
		$f['editors'] = array_values(array_unique($editors));
		$f['copyrightHolder'] = (string) $pub->getLocalizedData('copyrightHolder');
		$f['copyrightYear'] = (string) $pub->getData('copyrightYear');
		$f['licenseUrl'] = (string) $pub->getData('licenseUrl');
		return $f;
	}

	/** Statistik OJS 3.3 (tabel metrics): abstrak dilihat & berkas diunduh. */
	private function _metrics($submissionId) {
		$out = ['views' => 0, 'downloads' => 0];
		try {
			import('lib.pkp.classes.db.DAO');
			$dao = new DAO();
			foreach ($dao->retrieve('SELECT assoc_type, SUM(metric) AS n FROM metrics WHERE submission_id = ? AND metric_type = ? AND assoc_type IN (?, ?) GROUP BY assoc_type',
				[(int) $submissionId, 'ojs::counter', 1048585, 515]) as $r) {
				$r = (array) $r;
				if ((int) $r['assoc_type'] === 1048585) $out['views'] = (int) $r['n']; else $out['downloads'] = (int) $r['n'];
			}
		} catch (\Throwable $e) {}
		return $out;
	}

	private function _countryName($code) {
		static $countries = null;
		try {
			if ($countries === null) { $f = new \Sokil\IsoCodes\IsoCodesFactory(); $countries = $f->getCountries(); }
			$c = $countries->getByAlpha2($code);
			if ($c) return $c->getLocalName();
		} catch (\Throwable $e) {}
		return $code;
	}

	/** "halaman, halaman | blok, blok" per baris -> [[ [halaman...], [blok...] ], ...] */
	private function _rules($v) {
		$out = [];
		foreach (preg_split('/\r\n|\r|\n/', (string) $v) as $line) {
			if (strpos($line, '|') === false) continue;
			list($pages, $blocks) = array_map('trim', explode('|', $line, 2));
			$p = array_values(array_filter(array_map(function ($x) { return strtolower(trim($x, " /\t")); }, explode(',', $pages)), 'strlen'));
			$b = array_values(array_filter(array_map(function ($x) { return strtolower(trim($x)); }, explode(',', $blocks)), 'strlen'));
			if ($p && $b) $out[] = [$p, $b];
		}
		return $out;
	}

	/* ---------------- helper ---------------- */
	/* ---------- kontras warna (WCAG) ---------- */
	private function _rgb($hex) {
		$h = ltrim($hex, '#');
		if (strlen($h) === 3 || strlen($h) === 4) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
	}
	private function _lum($hex) {
		$l = array_map(function ($c) { $c /= 255; return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4); }, $this->_rgb($hex));
		return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
	}
	private function _contrast($a, $b) {
		$x = $this->_lum($a); $y = $this->_lum($b);
		return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
	}
	/** Warna teks di atas latar $bg: gelap atau putih, mana yang lebih kontras. */
	private function _onColor($bg) {
		return $this->_contrast($bg, '#1a1300') >= $this->_contrast($bg, '#ffffff') ? '#1a1300' : '#ffffff';
	}
	/** Campur $c dengan putih sedikit demi sedikit sampai kontrasnya terhadap $bg >= $min (rona warna tetap). */
	private function _lighten($c, $bg, $min) {
		if ($this->_contrast($c, $bg) >= $min) return $c;
		list($r, $g, $b) = $this->_rgb($c);
		for ($t = 0.1; $t <= 1.0001; $t += 0.05) {
			$x = sprintf('#%02x%02x%02x', round($r + (255 - $r) * $t), round($g + (255 - $g) * $t), round($b + (255 - $b) * $t));
			if ($this->_contrast($x, $bg) >= $min) return $x;
		}
		return '#ffffff';
	}
	/** $c bila cukup terbaca di atas $bg (kontras >= 3), jika tidak amber CHEESA / putih. */
	private function _readable($c, $bg) {
		foreach ([$c, '#ed991f', '#ffd27a', '#ffffff'] as $x) if ($this->_contrast($x, $bg) >= 3) return $x;
		return '#ffffff';
	}

	private function _color($v, $fallback) {
		return (is_string($v) && preg_match('/^#[0-9a-f]{3,8}$/i', trim($v))) ? trim($v) : $fallback;
	}
	private function _list($v) {
		$out = [];
		foreach (explode(',', (string) $v) as $s) { $s = strtolower(trim($s)); if ($s !== '') $out[] = $s; }
		return $out;
	}
	private function _lines($v, $cols) {
		$out = [];
		foreach (preg_split('/\r\n|\r|\n/', (string) $v) as $line) {
			$line = trim($line);
			if ($line === '') continue;
			$p = array_map('trim', explode('|', $line));
			$out[] = array_pad(array_slice($p, 0, $cols), $cols, '');
		}
		return $out;
	}
}
