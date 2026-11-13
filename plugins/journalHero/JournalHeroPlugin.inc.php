<?php

/**
 * @file plugins/generic/journalHero/JournalHeroPlugin.inc.php
 *
 * @class JournalHeroPlugin
 * @brief Shows a journal description "hero" (title, description, highlights,
 *        accreditation, ISSN, issue-cover carousel and a feature strip)
 *        directly below the site header, independent of the active theme.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class JournalHeroPlugin extends GenericPlugin {

	/** Default values for every setting. */
	const DEFAULTS = [
		'style' => 'carousel',          // carousel | profile
		'pages' => 'home',              // home | all
		'position' => 'afterHeader',    // beforeHeader | beforeMenu | afterHeader | beforeContent
		'hideSiteName' => false,
		'hideSelectors' => '.ch-issn > a',
		'layout' => 'boxed',            // boxed | full
		'boxWidth' => 0,                // px, 0 = match the site's content width
		'size' => 'compact',            // compact | normal
		'heroTitle' => '',
		'eyebrow' => '',
		'heroDescription' => '',
		'descriptionLength' => 210,
		'backgroundUrl' => '',
		'useHomepageImage' => true,
		'rankLabel' => '',
		'rankValue' => '',
		'rankUrl' => '',
		'printIssnUrl' => '',
		'onlineIssnUrl' => '',
		'showIssn' => true,
		'showFeatures' => true,
		'showCovers' => true,
		'coverCount' => 6,
		'showStrip' => true,
		'subtitle' => '',
		'stats' => '',
		'doiPrefix' => '',
		'license' => '',
		'frequency' => '',
		'showSubmit' => true,
		'font' => 'inter',              // inter | interSerif | jakarta | outfit | theme
		'colorPrimary' => '#0F2A4A',
		'colorAccent' => '#F2B630',
		'colorRank' => '#2563EB',
	];

	/** Ensure the hero is injected once per page. */
	private $_injected = false;

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('TemplateManager::display', [$this, 'handleDisplay']);
		}
		return $success;
	}

	public function getDisplayName() {
		return __('plugins.generic.journalHero.displayName');
	}

	public function getDescription() {
		return __('plugins.generic.journalHero.description');
	}

	/**
	 * Read a setting with its default.
	 */
	public function getHeroSetting($contextId, $name) {
		$value = $this->getSetting($contextId, $name);
		return $value === null ? self::DEFAULTS[$name] : $value;
	}

	/**
	 * Prepare the hero and register an output filter that inserts it.
	 */
	public function handleDisplay($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];

		if (strpos((string) $template, 'frontend/pages/') !== 0) return false;

		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;
		$contextId = $context->getId();

		if ($this->getHeroSetting($contextId, 'pages') === 'home' && $template !== 'frontend/pages/indexJournal.tpl') {
			return false;
		}

		$baseUrl = $request->getBaseUrl() . '/' . $this->getPluginPath();
		$fonts = [
			'inter' => 'family=Inter:wght@400;500;600;700',
			'interSerif' => 'family=Inter:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;0,8..60,700;1,8..60,500',
			'jakarta' => 'family=Plus+Jakarta+Sans:wght@400;500;600;700;800',
			'outfit' => 'family=Outfit:wght@400;500;600;700;800',
			'theme' => '',
		];
		$font = $this->getHeroSetting($contextId, 'font');
		if (!isset($fonts[$font])) $font = 'inter';
		$families = $fonts[$font];
		if ($this->getHeroSetting($contextId, 'style') === 'profile' && strpos($families, 'Source+Serif') === false) {
			$families .= ($families ? '&' : '') . 'family=Source+Serif+4:ital,opsz,wght@0,8..60,600;0,8..60,700;1,8..60,500';
		}
		if ($families !== '') {
			$templateMgr->addStyleSheet('journalHeroFont', 'https://fonts.googleapis.com/css2?' . $families . '&display=swap', ['contexts' => 'frontend']);
		}
		$templateMgr->addStyleSheet('journalHero', $baseUrl . '/css/hero.css', ['contexts' => 'frontend']);
		$templateMgr->addStyleSheet('journalHeroVars', $this->_cssVariables($contextId), ['contexts' => 'frontend', 'inline' => true]);
		if ($this->getHeroSetting($contextId, 'hideSiteName')) {
			// Join the banner to the top of the page: hide the site name/logo,
			// extra header elements (e.g. the theme's ISSN bar) and the header's top padding.
			$selectors = ['.pkp_structure_head .pkp_site_name'];
			$extra = trim((string) $this->getHeroSetting($contextId, 'hideSelectors'));
			// v1.3.x default hid the whole ISSN bar, which on small screens also holds the user menu
			if ($extra === '.ch-issn') $extra = '.ch-issn > a';
			foreach (preg_split('/[\r\n,]+/', $extra) as $sel) {
				$sel = trim($sel);
				if ($sel !== '' && preg_match('/^[a-zA-Z0-9_\-\.#\s>:\[\]="\'*]+$/', $sel)) $selectors[] = $sel;
			}
			// Prefix with "html:root body" so theme rules such as ":root body .x{display:flex!important}" cannot win
			$selectors = array_map(function ($sel) {
				return preg_match('/^(html|body|:root)\b/i', $sel) ? $sel : 'html:root body ' . $sel;
			}, $selectors);
			$css = implode(',', $selectors) . '{display:none!important}'
				. 'html:root body .pkp_structure_head .pkp_head_wrapper{padding-top:0!important}'
				. 'html:root body .pkp_structure_head .pkp_site_name_wrapper{min-height:0!important;padding-top:0!important;padding-bottom:0!important}';
			$templateMgr->addStyleSheet('journalHeroHideName', $css, ['contexts' => 'frontend', 'inline' => true]);
		}
		$templateMgr->addJavaScript('journalHero', $baseUrl . '/js/hero.js', ['contexts' => 'frontend']);

		$templateMgr->assign('jh', $this->_buildData($request, $context));
		$html = $templateMgr->fetch($this->getTemplateResource('hero.tpl'));

		$position = $this->getHeroSetting($contextId, 'position');
		$plugin = $this;
		$templateMgr->registerFilter('output', function ($output, $smarty) use ($plugin, $html, $position) {
			return $plugin->injectHero($output, $html, $position);
		});

		return false;
	}

	/**
	 * Insert the hero HTML into the full page output.
	 */
	public function injectHero($output, $html, $position) {
		if ($this->_injected || strpos($output, '<body') === false) return $output;

		$patterns = [
			// Before the whole site header
			'beforeHeader' => ['/<header[^>]*class="[^"]*\bpkp_structure_head\b[^"]*"[^>]*>/i', '/<header\b[^>]*>/i'],
			// Inside the header, just before the main menu
			'beforeMenu' => ['/<nav[^>]*class="[^"]*\bpkp_site_nav_menu\b[^"]*"[^>]*>/i', '/<div[^>]*class="[^"]*\bpkp_navigation_primary_row\b[^"]*"[^>]*>/i'],
			// Before the main content area
			'beforeContent' => ['/<div[^>]*class="[^"]*\bpkp_structure_content\b[^"]*"[^>]*>/i'],
		];

		if (isset($patterns[$position])) {
			foreach ($patterns[$position] as $pattern) {
				if (preg_match($pattern, $output, $m, PREG_OFFSET_CAPTURE)) {
					$this->_injected = true;
					return substr_replace($output, $html, $m[0][1], 0);
				}
			}
		}

		// Default / fallback: right after the first closing </header>
		$pos = stripos($output, '</header>');
		if ($pos !== false) {
			$this->_injected = true;
			return substr_replace($output, $html, $pos + strlen('</header>'), 0);
		}
		if (preg_match($patterns['beforeContent'][0], $output, $m, PREG_OFFSET_CAPTURE)) {
			$this->_injected = true;
			return substr_replace($output, $html, $m[0][1], 0);
		}
		return $output;
	}

	/**
	 * Collect everything the hero template needs.
	 */
	private function _buildData($request, $context) {
		$contextId = $context->getId();
		$s = function ($name) use ($contextId) { return $this->getHeroSetting($contextId, $name); };

		// Title & description
		$title = trim((string) $s('heroTitle'));
		if ($title === '') $title = $context->getLocalizedName();

		$description = trim((string) $s('heroDescription'));
		if ($description === '') {
			$description = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $context->getLocalizedData('description')), ENT_QUOTES, 'UTF-8')));
			$max = max(0, (int) $s('descriptionLength'));
			if ($max && mb_strlen($description) > $max) {
				$description = rtrim(mb_substr($description, 0, $max)) . '…';
			}
		}

		// Background image
		$background = trim((string) $s('backgroundUrl'));
		if ($background === '' && $s('useHomepageImage')) {
			$image = $context->getLocalizedData('homepageImage');
			if (is_array($image) && !empty($image['uploadName'])) {
				import('classes.file.PublicFileManager');
				$publicFileManager = new PublicFileManager();
				$background = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($contextId) . '/' . rawurlencode($image['uploadName']);
			}
		}

		// Recent issues for the cover carousel
		$covers = [];
		if ($s('showCovers')) {
			$count = max(1, min(12, (int) $s('coverCount')));
			try {
				$issueDao = DAORegistry::getDAO('IssueDAO');
				import('lib.pkp.classes.db.DBResultRange');
				$issues = $issueDao->getPublishedIssues($contextId, new DBResultRange($count, 1))->toArray();
				$dispatcher = $request->getDispatcher();
				foreach ($issues as $issue) {
					$covers[] = [
						'url' => $dispatcher->url($request, ROUTE_PAGE, null, 'issue', 'view', $issue->getBestIssueId()),
						'image' => $issue->getLocalizedCoverImageUrl(),
						'alt' => $issue->getLocalizedCoverImageAltText(),
						'title' => $issue->getLocalizedTitle() ?: ($context->getLocalizedAcronym() ?: $context->getLocalizedName()),
						'label' => $issue->getIssueIdentification(['showTitle' => false]),
					];
				}
			} catch (Exception $e) {
				$covers = [];
			}
		}

		// Links: Sinta (optional), ISSN (custom URL or the ISSN Portal by default)
		$cleanUrl = function ($url) {
			$url = trim((string) $url);
			return preg_match('#^https?://#i', $url) ? $url : '';
		};
		$issnUrl = function ($custom, $issn) use ($cleanUrl) {
			$custom = $cleanUrl($custom);
			if ($custom !== '') return $custom;
			return $issn ? 'https://portal.issn.org/resource/ISSN/' . rawurlencode($issn) : '';
		};
		$printIssn = $s('showIssn') ? (string) $context->getData('printIssn') : '';
		$onlineIssn = $s('showIssn') ? (string) $context->getData('onlineIssn') : '';

		$dispatcher = $request->getDispatcher();
		$profile = ($s('style') === 'profile') ? $this->_buildProfileData($request, $context, $s) : null;

		return [
			'style' => $profile ? 'profile' : 'carousel',
			'profile' => $profile,
			'font' => in_array($s('font'), ['inter', 'interSerif', 'jakarta', 'outfit', 'theme']) ? $s('font') : 'inter',
			'layout' => $s('layout') === 'full' ? 'full' : 'boxed',
			'size' => $s('size') === 'normal' ? 'normal' : 'compact',
			'boxWidth' => max(0, (int) $s('boxWidth')),
			'title' => $title,
			'eyebrow' => trim((string) $s('eyebrow')),
			'description' => $description,
			'background' => $background,
			'rankLabel' => trim((string) $s('rankLabel')) ?: __('plugins.generic.journalHero.rank'),
			'rankValue' => trim((string) $s('rankValue')),
			'rankUrl' => $cleanUrl($s('rankUrl')),
			'printIssn' => $printIssn,
			'onlineIssn' => $onlineIssn,
			'printIssnUrl' => $issnUrl($s('printIssnUrl'), $printIssn),
			'onlineIssnUrl' => $issnUrl($s('onlineIssnUrl'), $onlineIssn),
			'showFeatures' => (bool) $s('showFeatures'),
			'covers' => $covers,
			'showStrip' => (bool) $s('showStrip'),
			'journalName' => $context->getLocalizedAcronym() ?: $context->getLocalizedName(),
			'editorialUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'about', 'editorialTeam'),
			'aboutUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'about'),
		];
	}

	/**
	 * Extra data for the "profile" style: current-issue cover, info tiles,
	 * statistics and submission links.
	 */
	private function _buildProfileData($request, $context, $s) {
		$contextId = $context->getId();
		$dispatcher = $request->getDispatcher();

		// Current issue cover
		$cover = null;
		try {
			$issue = DAORegistry::getDAO('IssueDAO')->getCurrent($contextId);
			if ($issue) {
				$cover = [
					'url' => $dispatcher->url($request, ROUTE_PAGE, null, 'issue', 'view', $issue->getBestIssueId()),
					'image' => $issue->getLocalizedCoverImageUrl(),
					'alt' => $issue->getLocalizedCoverImageAltText(),
					'label' => $issue->getIssueIdentification(['showTitle' => false]),
					'year' => $issue->getYear(),
				];
			}
		} catch (Exception $e) {
			$cover = null;
		}

		// Info tiles
		$tiles = [];
		$rank = trim((string) $s('rankValue'));
		if ($rank !== '') {
			$badge = preg_match('/sinta\s*(\d)/i', $rank, $m) ? 'S' . $m[1] : mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $rank), 0, 2));
			$tiles[] = ['badge' => $badge, 'label' => __('plugins.generic.journalHero.tile.accreditation'), 'value' => $rank, 'url' => preg_match('#^https?://#i', (string) $s('rankUrl')) ? $s('rankUrl') : ''];
		}
		if ($s('showIssn')) {
			foreach (['printIssn' => 'tile.printIssn', 'onlineIssn' => 'tile.onlineIssn'] as $field => $key) {
				$issn = (string) $context->getData($field);
				if ($issn === '') continue;
				$custom = (string) $s($field . 'Url');
				$tiles[] = ['badge' => 'ISSN', 'label' => __('plugins.generic.journalHero.' . $key), 'value' => $issn,
					'url' => preg_match('#^https?://#i', $custom) ? $custom : 'https://portal.issn.org/resource/ISSN/' . rawurlencode($issn)];
			}
		}
		$doiPrefix = trim((string) $s('doiPrefix'));
		if ($doiPrefix === '') {
			$doiPlugin = PluginRegistry::getPlugin('pubIds', 'doipubidplugin');
			if ($doiPlugin && $doiPlugin->getSetting($contextId, 'enabled')) {
				$doiPrefix = (string) $doiPlugin->getSetting($contextId, 'doiPrefix');
			}
		}
		if ($doiPrefix !== '') {
			$tiles[] = ['badge' => 'DOI', 'label' => __('plugins.generic.journalHero.tile.doiPrefix'), 'value' => $doiPrefix, 'url' => ''];
		}
		$license = trim((string) $s('license'));
		$licenseUrl = (string) $context->getData('licenseUrl');
		if ($license === '' && preg_match('#creativecommons\.org/licenses/([a-z\-]+)/#i', $licenseUrl, $m)) {
			$license = 'CC ' . strtoupper($m[1]);
		}
		if ($license !== '') {
			$tiles[] = ['badge' => 'CC', 'label' => __('plugins.generic.journalHero.tile.license'), 'value' => $license, 'url' => $licenseUrl];
		}
		$frequency = trim((string) $s('frequency'));
		if ($frequency !== '') {
			$badge = preg_match('/(\d+)/', $frequency, $m) ? $m[1] . '×' : '↻';
			$tiles[] = ['badge' => $badge, 'label' => __('plugins.generic.journalHero.tile.frequency'), 'value' => $frequency, 'url' => ''];
		}

		// Statistics: one "value | label" per line
		$stats = [];
		foreach (preg_split('/\r\n|\r|\n/', (string) $s('stats')) as $line) {
			$parts = array_map('trim', explode('|', $line, 2));
			if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
				$stats[] = ['value' => $parts[0], 'label' => $parts[1]];
			}
			if (count($stats) >= 4) break;
		}

		return [
			'subtitle' => trim((string) $s('subtitle')),
			'cover' => $cover,
			'tiles' => $tiles,
			'stats' => $stats,
			'showSubmit' => (bool) $s('showSubmit'),
			'submitUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'about', 'submissions'),
			'contactUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'about', 'contact'),
		];
	}

	/**
	 * Inline CSS variables for the colours.
	 */
	private function _cssVariables($contextId) {
		$color = function ($name) use ($contextId) {
			$v = (string) $this->getHeroSetting($contextId, $name);
			return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v) ? $v : self::DEFAULTS[$name];
		};
		return '.jh-root{--jh-primary:' . $color('colorPrimary') . ';--jh-accent:' . $color('colorAccent') . ';--jh-rank:' . $color('colorRank') . ';}';
	}

	/**
	 * Settings link in the plugin list.
	 */
	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;

		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		array_unshift($actions, new LinkAction(
			'settings',
			new AjaxModal(
				$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		));
		return $actions;
	}

	/**
	 * Settings form handling.
	 */
	public function manage($args, $request) {
		if ($request->getUserVar('verb') === 'settings') {
			$context = $request->getContext();
			$this->import('JournalHeroSettingsForm');
			$form = new JournalHeroSettingsForm($this, $context->getId());
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
