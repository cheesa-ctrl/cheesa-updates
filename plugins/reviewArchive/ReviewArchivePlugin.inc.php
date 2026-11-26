<?php

/**
 * @file plugins/generic/reviewArchive/ReviewArchivePlugin.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchivePlugin
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Two features for OJS 3.3:
 *  1. Adds an "Arsip Review" tab next to Submission / Review / Copyediting /
 *     Production in the editorial workflow. It lists files whose name
 *     contains "[REVIEW]" plus review-stage / attachment files that the
 *     normal workflow grids do not show (e.g. after a Native XML import,
 *     which does not carry review rounds).
 *  2. Scans Native XML files for online-gambling ("judol") spam before they
 *     are imported, including the embedded .doc/.docx/.pdf files, and stops
 *     the import with a report when something is found.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class ReviewArchivePlugin extends GenericPlugin {

	/** Template that renders the workflow stage tabs (lib/pkp/templates). */
	const STAGE_TABS_TEMPLATE = 'workflow/submissionProgressBar.tpl';

	/** Component handler that renders the "Arsip Review" tab. */
	const TAB_COMPONENT = 'plugins.generic.reviewArchive.controllers.tab.ReviewArchiveTabHandler';

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('TemplateResource::getFilename', [$this, 'overrideStageTabs']);
			HookRegistry::register('LoadComponentHandler', [$this, 'setupTabHandler']);
			HookRegistry::register('LoadHandler', [$this, 'checkNativeImport']);
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
		$request = Application::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->clearTemplateCache();
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

	//
	// Feature 2: online-gambling ("judol") check before Native XML import
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

		// Only for logged-in journal managers / site admins with a valid CSRF
		// token; anything else is left for the core to accept or reject.
		$user = $request->getUser();
		$context = $request->getContext();
		if (!$user || !$context || !$request->checkCSRF()) return false;
		if (!$user->hasRole([ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN], $context->getId())
			&& !$user->hasRole([ROLE_ID_SITE_ADMIN], CONTEXT_SITE)) {
			return false;
		}

		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile((int) $request->getUserVar('temporaryFileId'), $user->getId());
		if (!$temporaryFile) return false;
		$filePath = $temporaryFile->getFilePath();
		if (!is_readable($filePath)) return false;

		$this->import('classes.JudolScanner');
		$scanner = new JudolScanner();
		$report = $scanner->scanFile($filePath);
		if (empty($report['findings'])) return false;

		// Something was found: do not import, show the report instead.
		error_log(sprintf(
			'[reviewArchive] Native XML import blocked for user %d: %d judol finding(s) in "%s".',
			$user->getId(), count($report['findings']), $temporaryFile->getOriginalFileName()
		));

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'judolReport' => $report,
			'judolFileName' => $temporaryFile->getOriginalFileName(),
		]);
		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('judolReport.tpl')));
		header('Content-Type: application/json');
		echo $json->getString();
		exit;
	}
}
