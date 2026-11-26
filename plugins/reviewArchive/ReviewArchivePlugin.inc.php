<?php

/**
 * @file plugins/generic/reviewArchive/ReviewArchivePlugin.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchivePlugin
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Review archive tools for OJS 3.3:
 *  1. "Arsip Review" tab in the editorial workflow (next to Submission /
 *     Review / Copyediting / Production) with an editor statement for
 *     accreditation assessors, links to the reviewer certificates, and every
 *     review-process file that the normal grids do not show after a Native
 *     XML import.
 *  2. "Import XML Arsip Review" tab under Tools: chunked upload (no 8 MB
 *     limit), online-gambling ("judol") check, automatic [REVIEW] labels with
 *     reviewer names, removal of duplicate / working files, then a normal
 *     Native XML import.
 *  3. Online-gambling check on the core Native XML import as well.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class ReviewArchivePlugin extends GenericPlugin {

	/** Template that renders the workflow stage tabs (lib/pkp/templates). */
	const STAGE_TABS_TEMPLATE = 'workflow/submissionProgressBar.tpl';

	/** Component handler that renders the "Arsip Review" tab. */
	const TAB_COMPONENT = 'plugins.generic.reviewArchive.controllers.tab.ReviewArchiveTabHandler';

	/** Page that hosts the import tool. */
	const IMPORT_PAGE = 'reviewarchive';

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('TemplateResource::getFilename', [$this, 'overrideStageTabs']);
			HookRegistry::register('LoadComponentHandler', [$this, 'setupTabHandler']);
			HookRegistry::register('LoadHandler', [$this, 'loadHandler']);
			HookRegistry::register('Schema::get::submission', [$this, 'addSubmissionProps']);
			HookRegistry::register('Templates::Management::Settings::tools', [$this, 'addToolsTab']);
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.reviewArchive.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.reviewArchive.description');
	}

	/**
	 * Clear compiled templates whenever the plugin is switched on or off,
	 * so the overridden stage-tab template is not served from a stale cache.
	 * @copydoc LazyLoadPlugin::setEnabled()
	 */
	public function setEnabled($enabled) {
		parent::setEnabled($enabled);
		$templateMgr = TemplateManager::getManager(Application::get()->getRequest());
		$templateMgr->clearTemplateCache();
	}

	//
	// Settings
	//
	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		array_unshift($actions,
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
				'importTool',
				new RedirectAction($request->getDispatcher()->url($request, ROUTE_PAGE, null, 'management', 'tools', null, null, 'reviewArchiveImport')),
				__('plugins.generic.reviewArchive.import.open'),
				null
			)
		);
		return $actions;
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) {
		if ($request->getUserVar('verb') === 'settings') {
			import('lib.pkp.classes.core.JSONMessage');
			$context = $request->getContext();
			$this->import('ReviewArchiveSettingsForm');
			$form = new ReviewArchiveSettingsForm($this, $context->getId());
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

	/**
	 * Journal-wide statement shown in every "Arsip Review" tab.
	 * @param $contextId int
	 * @return string
	 */
	public function getDefaultStatement($contextId) {
		$statement = $this->getSetting($contextId, 'defaultStatement');
		if ($statement === null) {
			$file = $this->getPluginPath() . '/data/defaultStatement.txt';
			$statement = file_exists($file) ? trim(file_get_contents($file)) : __('plugins.generic.reviewArchive.statement.default');
		}
		return (string) $statement;
	}

	/**
	 * Reviewer certificate list (CSV: id_artikel,nomor,url[,nama_file]).
	 * @param $contextId int
	 * @return string
	 */
	public function getCertificateCsv($contextId) {
		$csv = $this->getSetting($contextId, 'certificateCsv');
		if ($csv === null) {
			$file = $this->getPluginPath() . '/data/certificates.csv';
			$csv = file_exists($file) ? file_get_contents($file) : '';
		}
		return (string) $csv;
	}

	/**
	 * Certificates for one article, by its ID in the old OJS.
	 * @param $contextId int
	 * @param $sourceId string
	 * @return array [['number' => int, 'url' => string, 'name' => string], ...]
	 */
	public function getCertificates($contextId, $sourceId) {
		$found = [];
		if ($sourceId === null || $sourceId === '') return $found;
		foreach (preg_split('/\r\n|\r|\n/', $this->getCertificateCsv($contextId)) as $line) {
			$cols = str_getcsv(trim($line));
			if (count($cols) < 3 || !ctype_digit(trim($cols[0]))) continue;
			if (trim($cols[0]) !== (string) $sourceId) continue;
			$url = trim($cols[2]);
			if (!preg_match('#^https?://#i', $url)) continue;
			$found[] = ['number' => (int) $cols[1], 'url' => $url, 'name' => trim($cols[3] ?? '')];
		}
		usort($found, function($a, $b) { return $a['number'] <=> $b['number']; });
		return $found;
	}

	/**
	 * The article's ID in the old OJS: taken from the DOI
	 * (10.25273/cheesa.v3i2.7688.88-98 -> 7688), else the submission ID.
	 * @param $submission Submission
	 * @return string
	 */
	public function getSourceId($submission) {
		$this->import('classes.ReviewArchiveXmlProcessor');
		$publication = $submission->getCurrentPublication();
		$doi = $publication ? $publication->getStoredPubId('doi') : null;
		$fromDoi = ReviewArchiveXmlProcessor::sourceIdFromDoi($doi);
		return $fromDoi ?: (string) $submission->getId();
	}

	/**
	 * Escape text and turn URLs into links (line breaks kept).
	 * @param $text string
	 * @return string HTML
	 */
	public function formatText($text) {
		$html = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
		$html = preg_replace_callback('#\bhttps?://[^\s<>"\']+#i', function($m) {
			$url = rtrim($m[0], '.,);:');
			$tail = substr($m[0], strlen($url));
			return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $tail;
		}, $html);
		return nl2br($html);
	}

	/**
	 * Fill {id} {doi} {judul} {jurnal} placeholders.
	 * @param $text string
	 * @param $submission Submission
	 * @param $context Context
	 * @return string
	 */
	public function fillPlaceholders($text, $submission, $context) {
		$publication = $submission->getCurrentPublication();
		return strtr($text, [
			'{id}' => $this->getSourceId($submission),
			'{doi}' => $publication ? (string) $publication->getStoredPubId('doi') : '',
			'{judul}' => $publication ? (string) $publication->getLocalizedTitle() : '',
			'{jurnal}' => $context ? (string) $context->getLocalizedName() : '',
		]);
	}

	//
	// Feature 1: "Arsip Review" workflow tab
	//
	/**
	 * Serve our copy of the workflow stage tabs, which adds one more tab.
	 * @param $hookName string TemplateResource::getFilename
	 * @param $args array [&$filePath, $template]
	 * @return boolean
	 */
	public function overrideStageTabs($hookName, $args) {
		$filePath =& $args[0];
		$template = $args[1];
		if ($template === self::STAGE_TABS_TEMPLATE) {
			$ours = $this->getPluginPath() . '/templates/submissionProgressBar.tpl';
			if (file_exists($ours)) $filePath = $ours;
		}
		return false;
	}

	/**
	 * Let the component router load our tab handler.
	 * @param $hookName string LoadComponentHandler
	 * @param $params array [&$component, &$op]
	 * @return boolean
	 */
	public function setupTabHandler($hookName, $params) {
		$component =& $params[0];
		if ($component === self::TAB_COMPONENT) {
			import($component);
			ReviewArchiveTabHandler::setPlugin($this);
			return true;
		}
		return false;
	}

	/**
	 * Store the per-article editor statement in submission_settings.
	 * @param $hookName string Schema::get::submission
	 * @param $args array [&$schema]
	 * @return boolean
	 */
	public function addSubmissionProps($hookName, $args) {
		$schema =& $args[0];
		foreach (['reviewArchiveStatement', 'reviewArchiveStatementBy', 'reviewArchiveStatementAt'] as $prop) {
			$schema->properties->{$prop} = (object) [
				'type' => 'string',
				'apiSummary' => false,
				'validation' => ['nullable'],
			];
		}
		return false;
	}

	//
	// Feature 2: import tool under Tools
	//
	/**
	 * Add the "Import XML Arsip Review" tab under Tools.
	 * @param $hookName string Templates::Management::Settings::tools
	 * @param $args array [&$params, $smarty, &$output]
	 * @return boolean
	 */
	public function addToolsTab($hookName, $args) {
		$output =& $args[2];
		$request = Application::get()->getRequest();
		$url = $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::IMPORT_PAGE, 'index');
		$output .= '<li><a name="reviewArchiveImport" href="' . htmlspecialchars($url) . '">'
			. htmlspecialchars(__('plugins.generic.reviewArchive.import.tabTitle')) . '</a></li>';
		return false;
	}

	/**
	 * LoadHandler: our import page, and the judol check on the core
	 * Native XML import.
	 * @param $hookName string LoadHandler
	 * @param $args array [&$page, &$op, &$sourceFile]
	 * @return boolean
	 */
	public function loadHandler($hookName, $args) {
		$page = $args[0];
		if ($page === self::IMPORT_PAGE) {
			$this->import('pages.ReviewArchiveImportHandler');
			ReviewArchiveImportHandler::setPlugin($this);
			define('HANDLER_CLASS', 'ReviewArchiveImportHandler');
			return true;
		}
		return $this->checkNativeImport($hookName, $args);
	}

	//
	// Feature 3: judol check on the core Native XML import
	//
	/**
	 * Intercept management/importexport/plugin/NativeImportExportPlugin/import
	 * and scan the uploaded XML before the Native XML plugin imports it.
	 * @param $hookName string LoadHandler
	 * @param $args array [&$page, &$op, &$sourceFile]
	 * @return boolean
	 */
	public function checkNativeImport($hookName, $args) {
		$page = $args[0];
		$op = $args[1];
		if ($page !== 'management' || $op !== 'importexport') return false;

		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		if (!is_a($router, 'PKPPageRouter')) return false;
		$requestArgs = $router->getRequestedArgs($request);
		if (count($requestArgs) < 3
			|| $requestArgs[0] !== 'plugin'
			|| $requestArgs[1] !== 'NativeImportExportPlugin'
			|| $requestArgs[2] !== 'import') {
			return false;
		}

		$user = $request->getUser();
		$context = $request->getContext();
		if (!$user || !$context || !$request->checkCSRF()) return false;
		if (!$this->isManager($user, $context)) return false;

		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile((int) $request->getUserVar('temporaryFileId'), $user->getId());
		if (!$temporaryFile) return false;
		$filePath = $temporaryFile->getFilePath();
		if (!is_readable($filePath)) return false;

		$this->import('classes.JudolScanner');
		$scanner = new JudolScanner();
		$report = $scanner->scanFile($filePath);
		if (empty($report['findings'])) return false;

		$this->logBlocked($user, $temporaryFile->getOriginalFileName(), $report);
		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage(true, $this->renderJudolReport($request, $report, $temporaryFile->getOriginalFileName()));
		header('Content-Type: application/json');
		echo $json->getString();
		exit;
	}

	/**
	 * @param $user User
	 * @param $context Context
	 * @return boolean
	 */
	public function isManager($user, $context) {
		return $user->hasRole([ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN], $context->getId())
			|| $user->hasRole([ROLE_ID_SITE_ADMIN], CONTEXT_SITE);
	}

	/**
	 * @param $request PKPRequest
	 * @param $report array JudolScanner result
	 * @param $fileName string
	 * @return string HTML
	 */
	public function renderJudolReport($request, $report, $fileName) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'judolReport' => $report,
			'judolFileName' => $fileName,
		]);
		return $templateMgr->fetch($this->getTemplateResource('judolReport.tpl'));
	}

	/**
	 * @param $user User
	 * @param $fileName string
	 * @param $report array
	 */
	public function logBlocked($user, $fileName, $report) {
		error_log(sprintf(
			'[reviewArchive] XML import blocked for user %d: %d judol finding(s) in "%s".',
			$user->getId(), count($report['findings']), $fileName
		));
	}
}
