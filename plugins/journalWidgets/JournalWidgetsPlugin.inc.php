<?php

/**
 * @file plugins/generic/journalWidgets/JournalWidgetsPlugin.inc.php
 *
 * Journal Portal Widgets (OJS 3.3)
 * Plugin generic yang mendaftarkan 12 blok sidebar. Blok muncul di
 * Settings > Website > Appearance > Sidebar Management dan bisa dipakai dengan tema apa pun.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.db.DAO');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class JournalWidgetsPlugin extends GenericPlugin {

	/** id widget => nama yang tampil di Sidebar Management */
	const WIDGETS = [
		'issue'     => 'Journal Issue (cover)',
		'actions'   => 'Quick Actions (Submit / Reviewer / Social)',
		'menu'      => 'Journal Menu (with icons)',
		'diversity' => 'Author Diversity',
		'news'      => 'News (announcements)',
		'keywords'  => 'Keywords Cloud',
		'popular'   => 'Most Accessed',
		'editorial' => 'Editorial Board',
		'volumes'   => 'Published Volumes',
		'visitors'  => 'Visitors (OJS statistics)',
		'scholar'   => 'Google Scholar Citation',
		'scopus'    => 'Scopus Citation',
		'poster'    => 'Poster / Flyer',
	];

	/* nilai konstanta OJS 3.3 (ditulis langsung agar tidak bergantung pada urutan pemuatan kelas) */
	const ST_PUBLISHED = 3;          // STATUS_PUBLISHED
	const AT_SUBMISSION = 1048585;   // ASSOC_TYPE_SUBMISSION (0x0100009)
	const AT_PUBLICATION = 1048588;  // ASSOC_TYPE_PUBLICATION (0x010000c)

	/** pengaturan + nilai bawaan */
	public static function defaults() {
		return [
			'primary' => '#030271',
			'accent' => '#ed991f',
			'cacheHours' => '12',
			'submitUrl' => 'about/submissions',
			'reviewerUrl' => 'user/register',
			'social' => '',
			'menuItems' => "fa-bullseye|Focus & Scope|about\nfa-paper-plane|Submissions|about/submissions\nfa-users|Editorial Team|about/editorialTeam\nfa-shield|Privacy Statement|about/privacy\nfa-envelope|Contact|about/contact",
			'diversityMax' => '6',
			'diversityMore' => '',
			'newsMax' => '3',
			'keywordsMax' => '30',
			'popularMax' => '5',
			'popularPeriod' => 'all',
			'editorialList' => '',
			'editorialMore' => 'about/editorialTeam',
			'volumesYears' => '5',
			'visitorsMax' => '10',
			'scholarUrl' => '', 'scholarSince' => '', 'scholarAll' => '', 'scholarAllSince' => '',
			'scholarH' => '', 'scholarHSince' => '', 'scholarI10' => '', 'scholarI10Since' => '',
			'scholarYears' => '', 'scholarUpdated' => '',
			'scopusUrl' => '', 'scopusCiteScore' => '', 'scopusCiteYear' => '', 'scopusSjr' => '', 'scopusSnip' => '', 'scopusQuartile' => '',
			'scopusCitations' => '', 'scopusDocs' => '', 'scopusH' => '', 'scopusYears' => '', 'scopusUpdated' => '',
			'posterTitle' => '', 'posterImg' => '', 'posterUrl' => '', 'posterAlt' => '',
		];
	}

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId) && !CheesaLicense::checkRequest(__DIR__)) {
			$this->import('JournalWidgetBlockPlugin');
			$this->import('JwRender');
			foreach (self::WIDGETS as $id => $label) {
				PluginRegistry::register('blocks', new JournalWidgetBlockPlugin($id, $label, $this), $this->getPluginPath());
			}
			HookRegistry::register('TemplateManager::display', [$this, 'addAssets']);
			// data statistik (Author Diversity, Keywords, Volumes, ...) dihitung ulang saat artikel/terbitan diterbitkan atau ditarik
			foreach (['Publication::publish', 'Publication::unpublish', 'IssueGridHandler::publishIssue', 'IssueGridHandler::unpublishIssue'] as $h) {
				HookRegistry::register($h, [$this, 'onPublishChange']);
			}
		}
		return $success;
	}

	public function getDisplayName() { return 'Journal Portal Widgets'; }

	public function getDescription() {
		return '12 blok sidebar: Journal Issue, Quick Actions, Journal Menu, Author Diversity, News, Keywords Cloud, Most Accessed, Editorial Board, Published Volumes, Visitors, Google Scholar Citation, Poster. Aktifkan, atur di Settings, lalu tarik blok ke sidebar di Appearance.' . CheesaLicense::notice(CheesaLicense::checkRequest(__DIR__));
	}

	/* ---------------- CSS di halaman pembaca ---------------- */
	public function addAssets($hookName, $args) {
		static $done = false;
		if ($done) return false;
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;
		$done = true;
		$tm = $args[0];
		$tm->addStyleSheet('journalWidgets', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/widgets.css' . (preg_match('#<release>\\s*([0-9.]+)#', (string) @file_get_contents(__DIR__ . '/version.xml'), $vm) ? '?v=' . $vm[1] : ''), ['contexts' => 'frontend']);
		$cid = $context->getId();
		$p = $this->color($this->opt($cid, 'primary'), '#030271');
		$a = $this->color($this->opt($cid, 'accent'), '#ed991f');
		$tm->addStyleSheet('journalWidgetsVars', '.jw-block{--jw-primary:' . $p . ';--jw-accent:' . $a . ';}', ['contexts' => 'frontend', 'inline' => true]);
		return false;
	}

	/* ---------------- Pengaturan ---------------- */
	public function opt($contextId, $key) {
		$v = $this->getSetting($contextId, $key);
		if ($v === '-') return '';   // "-" = sengaja dikosongkan (mis. menyembunyikan tombol)
		if ($v === null || $v === '') { $d = self::defaults(); return isset($d[$key]) ? $d[$key] : ''; }
		return $v;
	}

	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $request->getRouter();
		array_unshift($actions, new LinkAction(
			'settings',
			new AjaxModal($router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']), $this->getDisplayName()),
			__('manager.plugins.settings'),
			null
		));
		return $actions;
	}

	public function manage($args, $request) {
		if ($request->getUserVar('verb') === 'settings') {
			$this->import('JournalWidgetsSettingsForm');
			$form = new JournalWidgetsSettingsForm($this);
			if (!$request->getUserVar('save')) {
				$form->initData();
				return new JSONMessage(true, $form->fetch($request));
			}
			$form->readInputData();
			if ($form->validate()) {
				$form->execute();
				return new JSONMessage(true);
			}
			return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/** Hook terbit/tarik artikel atau terbitan: kosongkan cache widget untuk jurnal itu. */
	public function onPublishChange($hookName, $args) {
		try {
			$cid = null;
			foreach ($args as $a) {
				if (is_object($a) && method_exists($a, 'getData') && $a->getData('contextId')) { $cid = (int) $a->getData('contextId'); break; }
				if (is_object($a) && method_exists($a, 'getJournalId') && $a->getJournalId()) { $cid = (int) $a->getJournalId(); break; }
			}
			if (!$cid) { $ctx = Application::get()->getRequest()->getContext(); $cid = $ctx ? (int) $ctx->getId() : 0; }
			if ($cid) $this->clearCache($cid);
		} catch (\Throwable $e) {
			error_log('journalWidgets cache: ' . $e->getMessage());
		}
		return false;
	}

	public function clearCache($contextId) {
		foreach (['diversity', 'keywords', 'popular', 'visitors', 'volumes'] as $k) $this->updateSetting($contextId, 'cache_' . $k, '', 'string');
	}

	/* ---------------- Render satu widget ---------------- */
	public function renderWidget($id, $request = null) {
		try {
			$request = $request ?: Application::get()->getRequest();
			$context = $request->getContext();
			if (!$context || !isset(self::WIDGETS[$id])) return '';
			$method = 'data' . ucfirst($id);
			return JwRender::$id($this->$method($context, $request));
		} catch (\Throwable $e) {
			error_log('journalWidgets[' . $id . ']: ' . $e->getMessage());
			return '';
		}
	}

	/* ===================== DATA ===================== */

	private function dataIssue($context, $request) {
		$issue = DAORegistry::getDAO('IssueDAO')->getCurrent($context->getId(), true);
		if (!$issue) return null;
		return [
			'title' => $this->issueLabel($issue),
			'journal' => $context->getLocalizedAcronym() ?: $context->getLocalizedName(),
			'cover' => $issue->getLocalizedCoverImageUrl(),
			'url' => $request->url(null, 'issue', 'view', $issue->getBestIssueId()),
			'archive' => $request->url(null, 'issue', 'archive'),
		];
	}

	private function dataActions($context, $request) {
		$cid = $context->getId();
		return [
			'buttons' => [
				['icon' => 'paper-plane', 'label' => 'Submit Your Paper', 'url' => $this->resolveUrl($this->opt($cid, 'submitUrl'), $request)],
				['icon' => 'user-circle', 'label' => 'Become a Reviewer', 'url' => $this->resolveUrl($this->opt($cid, 'reviewerUrl'), $request)],
			],
			'social' => array_map(function ($r) use ($request) {
				$icons = ['instagram' => 'instagram', 'whatsapp' => 'whatsapp', 'facebook' => 'facebook', 'twitter' => 'twitter', 'x' => 'twitter',
					'youtube' => 'youtube-play', 'linkedin' => 'linkedin', 'telegram' => 'telegram', 'email' => 'envelope', 'mail' => 'envelope', 'website' => 'globe'];
				$k = strtolower($r[0]);
				return ['name' => $r[0], 'icon' => isset($icons[$k]) ? $icons[$k] : 'link', 'url' => $this->resolveUrl($r[1], $request)];
			}, array_filter($this->lines($this->opt($cid, 'social'), 2), function ($r) { return $r[0] !== '' && $r[1] !== ''; })),
		];
	}

	private function dataMenu($context, $request) {
		$out = [];
		foreach ($this->lines($this->opt($context->getId(), 'menuItems'), 3) as $r) {
			if ($r[1] === '' || $r[2] === '') continue;
			$url = $this->resolveUrl($r[2], $request);
			$out[] = ['icon' => $r[0] ?: 'angle-right', 'label' => $r[1], 'url' => $url, 'ext' => (bool) preg_match('#^https?://#i', $r[2])];
		}
		return $out;
	}

	private function dataDiversity($context, $request) {
		$cid = $context->getId();
		$max = max(2, (int) $this->opt($cid, 'diversityMax'));
		// dihitung langsung setiap halaman dimuat (realtime; query ringan)
		$counts = call_user_func(function () use ($cid) {
			$sql = 'SELECT ast.setting_value AS code, COUNT(*) AS n
				FROM author_settings ast
				JOIN authors a ON a.author_id = ast.author_id
				JOIN submissions s ON s.current_publication_id = a.publication_id
				WHERE ast.setting_name = ? AND ast.setting_value <> ? AND s.context_id = ? AND s.status = ?
				GROUP BY ast.setting_value ORDER BY n DESC';
			$rows = [];
			foreach ($this->query($sql, ['country', '', (int) $cid, self::ST_PUBLISHED]) as $r) $rows[strtoupper($r['code'])] = (int) $r['n'];
			return $rows;
		});
		if (!$counts) return null;
		$total = array_sum($counts);
		$rows = []; $other = 0; $i = 0;
		foreach ($counts as $code => $n) {
			if ($i++ < $max - 1) $rows[] = ['code' => $code, 'name' => $this->countryName($code), 'pct' => $n / $total * 100];
			else $other += $n;
		}
		if ($other) $rows[] = ['code' => '', 'name' => 'Other', 'pct' => $other / $total * 100];
		$more = $this->opt($cid, 'diversityMore');
		return ['rows' => $rows, 'countries' => count($counts), 'more' => $more ? $this->resolveUrl($more, $request) : ''];
	}

	private function dataNews($context, $request) {
		$max = max(1, (int) $this->opt($context->getId(), 'newsMax'));
		$dao = DAORegistry::getDAO('AnnouncementDAO');
		// OJS 3.3 dapat mengembalikan DAOResultFactory, Generator, atau array: semuanya diterima
		if (method_exists($dao, 'getByAssocId')) {
			$res = $dao->getByAssocId(Application::get()->getContextAssocType(), $context->getId());
		} else {
			$res = Services::get('announcement')->getMany(['contextIds' => [$context->getId()], 'count' => $max * 3]);
		}
		$list = [];
		if (is_object($res) && method_exists($res, 'toArray') && !($res instanceof \Generator)) $list = $res->toArray();
		elseif (is_array($res)) $list = $res;
		elseif ($res instanceof \Traversable) foreach ($res as $a) { $list[] = $a; if (count($list) >= $max * 3) break; }
		$items = [];
		foreach ($list as $a) {
			if ($a->getDateExpire() && strtotime($a->getDateExpire()) < time()) continue;
			$text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $a->getLocalizedDescriptionShort())));
			if (function_exists('mb_strlen') && mb_strlen($text) > 150) $text = rtrim(mb_substr($text, 0, 147)) . '…';
			$items[] = [
				'title' => $a->getLocalizedTitle(),
				'date' => date('F j, Y', strtotime($a->getDatePosted())),
				'text' => $text,
				'url' => $request->url(null, 'announcement', 'view', $a->getId()),
			];
			if (count($items) >= $max) break;
		}
		return ['items' => $items, 'all' => $request->url(null, 'announcement')];
	}

	private function dataKeywords($context, $request) {
		$cid = $context->getId();
		$max = max(5, (int) $this->opt($cid, 'keywordsMax'));
		$rows = $this->cached($cid, 'keywords', function () use ($cid, $max) {
			$sql = 'SELECT LOWER(TRIM(cves.setting_value)) AS kw, COUNT(DISTINCT cv.assoc_id) AS n
				FROM controlled_vocab_entry_settings cves
				JOIN controlled_vocab_entries cve ON cve.controlled_vocab_entry_id = cves.controlled_vocab_entry_id
				JOIN controlled_vocabs cv ON cv.controlled_vocab_id = cve.controlled_vocab_id
				JOIN submissions s ON s.current_publication_id = cv.assoc_id
				WHERE cv.symbolic = ? AND cv.assoc_type = ? AND cves.setting_name = ? AND s.context_id = ? AND s.status = ?
				GROUP BY LOWER(TRIM(cves.setting_value)) ORDER BY n DESC, kw ASC LIMIT ' . (int) $max;
			$out = [];
			foreach ($this->query($sql, ['submissionKeyword', self::AT_PUBLICATION, 'submissionKeyword', (int) $cid, self::ST_PUBLISHED]) as $r) {
				if ($r['kw'] !== '') $out[] = ['kw' => $r['kw'], 'n' => (int) $r['n']];
			}
			usort($out, function ($a, $b) { return strcmp($a['kw'], $b['kw']); });   // urut abjad seperti awan kata
			return $out;
		});
		foreach ($rows as &$r) $r['url'] = $request->url(null, 'search', 'search', null, ['query' => $r['kw']]);
		return $rows;
	}

	private function dataPopular($context, $request) {
		$cid = $context->getId();
		$max = max(1, (int) $this->opt($cid, 'popularMax'));
		$period = $this->opt($cid, 'popularPeriod');
		$ids = $this->cached($cid, 'popular', function () use ($cid, $max, $period) {
			$params = [(int) $cid, self::AT_SUBMISSION, 'ojs::counter', self::ST_PUBLISHED];
			$where = '';
			if ($period === '12') { $where = ' AND m.month >= ?'; $params[] = date('Ym', strtotime('-12 months')); }
			$sql = 'SELECT m.submission_id AS id, SUM(m.metric) AS n FROM metrics m
				JOIN submissions s ON s.submission_id = m.submission_id
				WHERE m.context_id = ? AND m.assoc_type = ? AND m.metric_type = ? AND s.status = ?' . $where . '
				GROUP BY m.submission_id ORDER BY n DESC LIMIT ' . (int) $max;
			$out = [];
			foreach ($this->query($sql, $params) as $r) $out[] = [(int) $r['id'], (int) $r['n']];
			return $out;
		});
		$items = [];
		foreach ($ids as $x) {
			$sub = Services::get('submission')->get($x[0]);
			if (!$sub) continue;
			$pub = $sub->getCurrentPublication();
			$names = [];
			foreach ((array) $pub->getData('authors') as $au) $names[] = $au->getFullName();
			$authors = count($names) > 2 ? $names[0] . ', ' . $names[1] . ' et al.' : implode(', ', $names);
			$items[] = [
				'title' => $pub->getLocalizedFullTitle(),
				'url' => $request->url(null, 'article', 'view', $sub->getBestId()),
				'views' => $x[1],
				'date' => $pub->getData('datePublished') ? date('F j, Y', strtotime($pub->getData('datePublished'))) : '',
				'authors' => $authors,
			];
		}
		return $items;
	}

	private function dataEditorial($context, $request) {
		$cid = $context->getId();
		$groups = []; $cur = null;
		foreach (preg_split('/\r\n|\r|\n/', (string) $this->opt($cid, 'editorialList')) as $line) {
			$line = trim($line);
			if ($line === '') continue;
			if ($line[0] === '#') { $groups[] = ['role' => trim(ltrim($line, '#')), 'people' => []]; $cur = count($groups) - 1; continue; }
			if ($cur === null) { $groups[] = ['role' => '', 'people' => []]; $cur = 0; }
			$p = array_pad(array_map('trim', explode('|', $line)), 3, '');
			$groups[$cur]['people'][] = ['name' => $p[0], 'aff' => $p[1], 'url' => $p[2] !== '' ? $this->resolveUrl($p[2], $request) : ''];
		}
		$more = $this->opt($cid, 'editorialMore');
		return ['groups' => $groups, 'more' => $more ? $this->resolveUrl($more, $request) : ''];
	}

	private function dataVolumes($context, $request) {
		$cid = $context->getId();
		$keep = max(1, (int) $this->opt($cid, 'volumesYears'));
		// realtime: terbitan baru langsung muncul tanpa menunggu cache
		$data = call_user_func(function () use ($cid) {
			$years = [];
			$result = DAORegistry::getDAO('IssueDAO')->getPublishedIssues($cid);
			while ($issue = $result->next()) {
				$y = $issue->getYear() ?: ($issue->getDatePublished() ? date('Y', strtotime($issue->getDatePublished())) : 'Other');
				$years[$y][] = ['id' => $issue->getBestIssueId(), 'label' => $this->issueLabel($issue, false),
					'v' => (int) $issue->getVolume(), 'n' => (int) $issue->getNumber()];
			}
			krsort($years);
			foreach ($years as &$list) usort($list, function ($a, $b) { return ($b['v'] - $a['v']) ?: ($b['n'] - $a['n']); });
			return $years;
		});
		if (!$data) return null;
		$all = array_keys($data);
		$years = [];
		foreach (array_slice($data, 0, $keep, true) as $y => $list) {
			foreach ($list as $it) $years[$y][] = ['label' => $it['label'], 'url' => $request->url(null, 'issue', 'view', $it['id'])];
		}
		$nums = array_filter($all, 'is_numeric');
		return [
			'years' => $years,
			'range' => $nums ? min($nums) . ' – ' . max($nums) : '',
			'archive' => $request->url(null, 'issue', 'archive'),
		];
	}

	private function dataVisitors($context, $request) {
		$cid = $context->getId();
		$max = max(2, (int) $this->opt($cid, 'visitorsMax'));
		$d = $this->cached($cid, 'visitors', function () use ($cid, $max) {
			$rows = [];
			$sql = 'SELECT country_id AS code, SUM(metric) AS n FROM metrics
				WHERE context_id = ? AND metric_type = ? AND country_id IS NOT NULL AND country_id <> ?
				GROUP BY country_id ORDER BY n DESC LIMIT ' . (int) $max;
			foreach ($this->query($sql, [(int) $cid, 'ojs::counter', '']) as $r) $rows[] = ['code' => strtoupper($r['code']), 'n' => (int) $r['n']];
			$total = 0;
			foreach ($this->query('SELECT SUM(metric) AS n FROM metrics WHERE context_id = ? AND metric_type = ?', [(int) $cid, 'ojs::counter']) as $r) $total = (int) $r['n'];
			return ['rows' => $rows, 'total' => $total];
		});
		foreach ($d['rows'] as &$r) $r['name'] = $this->countryName($r['code']);
		$d['period'] = 'All time';
		return $d;
	}

	private function dataScholar($context, $request) {
		$cid = $context->getId();
		$years = [];
		foreach (preg_split('/[,;\s]+/', (string) $this->opt($cid, 'scholarYears')) as $pair) {
			if (preg_match('/^(\d{4})\s*[:=]\s*(\d+)$/', trim($pair), $m)) $years[$m[1]] = (int) $m[2];
		}
		ksort($years);
		return [
			'url' => $this->opt($cid, 'scholarUrl'),
			'sinceLabel' => $this->opt($cid, 'scholarSince') ?: 'Since ' . (date('Y') - 5),
			'all' => $this->opt($cid, 'scholarAll'), 'since' => $this->opt($cid, 'scholarAllSince'),
			'h' => $this->opt($cid, 'scholarH'), 'hSince' => $this->opt($cid, 'scholarHSince'),
			'i10' => $this->opt($cid, 'scholarI10'), 'i10Since' => $this->opt($cid, 'scholarI10Since'),
			'years' => $years,
			'updated' => $this->opt($cid, 'scholarUpdated'),
		];
	}

	private function dataScopus($context, $request) {
		$cid = $context->getId();
		$years = [];
		foreach (preg_split('/[,;\s]+/', (string) $this->opt($cid, 'scopusYears')) as $pair) {
			if (preg_match('/^(\d{4})\s*[:=]\s*(\d+)$/', trim($pair), $m)) $years[$m[1]] = (int) $m[2];
		}
		ksort($years);
		return [
			'url' => $this->opt($cid, 'scopusUrl'),
			'citeScore' => $this->opt($cid, 'scopusCiteScore'), 'citeYear' => $this->opt($cid, 'scopusCiteYear'),
			'sjr' => $this->opt($cid, 'scopusSjr'), 'snip' => $this->opt($cid, 'scopusSnip'), 'quartile' => $this->opt($cid, 'scopusQuartile'),
			'citations' => $this->opt($cid, 'scopusCitations'), 'docs' => $this->opt($cid, 'scopusDocs'), 'h' => $this->opt($cid, 'scopusH'),
			'years' => $years, 'updated' => $this->opt($cid, 'scopusUpdated'),
		];
	}

	private function dataPoster($context, $request) {
		$cid = $context->getId();
		return [
			'title' => $this->opt($cid, 'posterTitle'),
			'img' => $this->opt($cid, 'posterImg'),
			'url' => $this->opt($cid, 'posterUrl') ? $this->resolveUrl($this->opt($cid, 'posterUrl'), $request) : '',
			'alt' => $this->opt($cid, 'posterAlt') ?: 'Poster',
		];
	}

	/* ===================== helper ===================== */

	private function query($sql, $params) {
		$dao = new DAO();
		$out = [];
		foreach ($dao->retrieve($sql, $params) as $row) $out[] = (array) $row;
		return $out;
	}

	/** Hasil query berat disimpan di plugin settings selama cacheHours jam. */
	private function cached($contextId, $key, $fn) {
		$ttl = max(1, (int) $this->opt($contextId, 'cacheHours')) * 3600;
		$raw = $this->getSetting($contextId, 'cache_' . $key);
		if ($raw) {
			$o = json_decode($raw, true);
			if (is_array($o) && isset($o['t']) && time() - $o['t'] < $ttl) return $o['v'];
		}
		$v = $fn();
		$this->updateSetting($contextId, 'cache_' . $key, json_encode(['t' => time(), 'v' => $v]), 'string');
		return $v;
	}

	private function issueLabel($issue, $withYear = true) {
		$parts = [];
		if ($issue->getVolume()) $parts[] = 'Vol. ' . $issue->getVolume();
		if ($issue->getNumber()) $parts[] = 'No. ' . $issue->getNumber();
		$s = implode(' ', $parts);
		if ($withYear && $issue->getYear()) $s .= ($s ? ' ' : '') . '(' . $issue->getYear() . ')';
		return $s !== '' ? $s : $issue->getIssueIdentification();
	}

	/** "about/submissions" -> URL jurnal; URL penuh, "/path", "#anchor", mailto: dibiarkan. */
	public function resolveUrl($u, $request) {
		$u = trim((string) $u);
		if ($u === '') return '';
		if (preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $u)) return $u;
		$anchor = null;
		if (strpos($u, '#') !== false) list($u, $anchor) = explode('#', $u, 2);
		$query = null;
		if (strpos($u, '?') !== false) { list($u, $qs) = explode('?', $u, 2); parse_str($qs, $query); }
		$path = array_values(array_filter(explode('/', $u), 'strlen'));
		$page = array_shift($path);
		$op = array_shift($path);
		return $request->url(null, $page, $op, $path ?: null, $query, $anchor);
	}

	private function countryName($code) {
		static $countries = null;
		try {
			if ($countries === null) { $f = new \Sokil\IsoCodes\IsoCodesFactory(); $countries = $f->getCountries(); }
			$c = $countries->getByAlpha2(strtoupper($code));
			if ($c) return $c->getLocalName();
		} catch (\Throwable $e) {}
		return strtoupper($code);
	}

	private function lines($v, $cols) {
		$out = [];
		foreach (preg_split('/\r\n|\r|\n/', (string) $v) as $line) {
			$line = trim($line);
			if ($line === '') continue;
			$out[] = array_pad(array_map('trim', explode('|', $line, $cols)), $cols, '');
		}
		return $out;
	}

	private function color($v, $fallback) {
		return preg_match('/^#[0-9a-f]{3,8}$/i', trim((string) $v)) ? trim($v) : $fallback;
	}
}
