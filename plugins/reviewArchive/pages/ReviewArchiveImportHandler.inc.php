<?php

/**
 * @file plugins/generic/reviewArchive/pages/ReviewArchiveImportHandler.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchiveImportHandler
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief "Import XML Arsip Review" (Tools tab):
 *  index        -> the tab content
 *  uploadChunk  -> receives the XML in pieces (bypasses the PHP upload limit)
 *  analyze      -> judol check + preview of labels / removals
 *  import       -> applies the changes and runs the core Native XML import
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class ReviewArchiveImportHandler extends Handler {

	/** Bytes per chunk sent by the browser (kept well below 8 MB). */
	const CHUNK_SIZE = 2097152;

	/** Largest XML accepted. */
	const MAX_SIZE = 536870912; // 512 MB

	/** @var ReviewArchivePlugin */
	static $plugin;

	/**
	 * @param $plugin ReviewArchivePlugin
	 */
	static function setPlugin($plugin) {
		self::$plugin = $plugin;
	}

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN],
			['index', 'page', 'uploadChunk', 'analyze', 'import']
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Operations
	//
	/**
	 * Tab content.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$this->setupTemplate($request);
		$this->_cleanupOldUploads($request);
		$dispatcher = $request->getDispatcher();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'raUploadUrl' => $dispatcher->url($request, ROUTE_PAGE, null, ReviewArchivePlugin::IMPORT_PAGE, 'uploadChunk'),
			'raAnalyzeUrl' => $dispatcher->url($request, ROUTE_PAGE, null, ReviewArchivePlugin::IMPORT_PAGE, 'analyze'),
			'raImportUrl' => $dispatcher->url($request, ROUTE_PAGE, null, ReviewArchivePlugin::IMPORT_PAGE, 'import'),
			'raCsrfToken' => $request->getSession()->getCSRFToken(),
			'raChunkSize' => self::CHUNK_SIZE,
			'raMaxSize' => self::MAX_SIZE,
		]);
		return $this->_json(true, $templateMgr->fetch(self::$plugin->getTemplateResource('importTab.tpl')));
	}

	/**
	 * Full standalone page (for the sidebar menu), wrapping the import tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function page($args, $request) {
		// Alat ini berbasis jQuery; di halaman backend Vue, tag <style>/<script>-nya dibuang.
		// Maka arahkan ke tab Tools (dimuat via AJAX, di luar Vue) tempat ia berfungsi penuh.
		$url = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'management', 'tools', null, null, 'reviewArchiveImport');
		$request->redirectUrl($url);
	}

	/**
	 * Receive one piece of the XML file.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function uploadChunk($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->_json(false, __('form.csrfInvalid'));
		$uploadId = $this->_uploadId($request);
		if (!$uploadId) return $this->_json(false, __('plugins.generic.reviewArchive.import.error.upload'));
		$index = (int) $request->getUserVar('index');
		$total = (int) $request->getUserVar('total');
		$size = (int) $request->getUserVar('size');
		if ($total < 1 || $index < 0 || $index >= $total || $size < 1 || $size > self::MAX_SIZE) {
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.upload'));
		}
		if (empty($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.upload'));
		}

		$path = $this->_uploadPath($request, $uploadId);
		$metaPath = $path . '.json';
		if ($index === 0) {
			@unlink($path);
			$meta = ['next' => 0, 'total' => $total, 'size' => $size, 'name' => substr(basename((string) $request->getUserVar('name')), 0, 200)];
		} else {
			$meta = @json_decode((string) @file_get_contents($metaPath), true);
			if (!is_array($meta) || $meta['next'] !== $index || $meta['total'] !== $total) {
				return $this->_json(false, __('plugins.generic.reviewArchive.import.error.order'));
			}
		}
		$data = file_get_contents($_FILES['chunk']['tmp_name']);
		if ($data === false || file_put_contents($path, $data, FILE_APPEND | LOCK_EX) === false) {
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.upload'));
		}
		clearstatcache(true, $path);
		if (filesize($path) > self::MAX_SIZE) {
			@unlink($path);
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.tooLarge'));
		}
		$meta['next'] = $index + 1;
		file_put_contents($metaPath, json_encode($meta));
		$json = new JSONMessage(true);
		$json->setAdditionalAttributes(['received' => filesize($path)]);
		header('Content-Type: application/json');
		return $json;
	}

	/**
	 * Judol check and preview.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function analyze($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->_json(false, __('form.csrfInvalid'));
		$this->setupTemplate($request);
		list($xml, $meta, $error) = $this->_readUpload($request);
		if ($error) return $this->_json(false, $error);
		$this->_raiseLimits();

		// 1. Online gambling check on the file as uploaded.
		self::$plugin->import('classes.JudolScanner');
		$scanner = new JudolScanner();
		$report = $scanner->scanXmlString($xml);
		if (!empty($report['findings'])) {
			self::$plugin->logBlocked($request->getUser(), $meta['name'], $report);
			$this->_deleteUpload($request);
			$json = new JSONMessage(true, self::$plugin->renderJudolReport($request, $report, $meta['name']));
			$json->setAdditionalAttributes(['blocked' => true]);
			header('Content-Type: application/json');
			return $json;
		}

		// 2. Preview of labels and removals.
		self::$plugin->import('classes.ReviewArchiveXmlProcessor');
		try {
			$processor = new ReviewArchiveXmlProcessor($xml, $this->_options($request));
			$plan = $processor->process();
		} catch (Exception $e) {
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.xml'));
		}
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'raPlan' => $plan,
			'raFileName' => $meta['name'],
			'raScanned' => $report['scannedFiles'],
			'raSkipped' => $report['skipped'],
		]);
		$json = new JSONMessage(true, $templateMgr->fetch(self::$plugin->getTemplateResource('importPlan.tpl')));
		$json->setAdditionalAttributes(['blocked' => false]);
		header('Content-Type: application/json');
		return $json;
	}

	/**
	 * Apply the changes and import through the core Native XML plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function import($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) return $this->_json(false, __('form.csrfInvalid'));
		$this->setupTemplate($request);
		list($xml, $meta, $error) = $this->_readUpload($request);
		if ($error) return $this->_json(false, $error);
		$this->_raiseLimits();

		// Never import without checking again.
		self::$plugin->import('classes.JudolScanner');
		$scanner = new JudolScanner();
		$report = $scanner->scanXmlString($xml);
		if (!empty($report['findings'])) {
			self::$plugin->logBlocked($request->getUser(), $meta['name'], $report);
			$this->_deleteUpload($request);
			return $this->_json(true, self::$plugin->renderJudolReport($request, $report, $meta['name']));
		}

		self::$plugin->import('classes.ReviewArchiveXmlProcessor');
		try {
			$processor = new ReviewArchiveXmlProcessor($xml, $this->_options($request));
			$plan = $processor->process();
			$xml = $processor->getXml();
		} catch (Exception $e) {
			return $this->_json(false, __('plugins.generic.reviewArchive.import.error.xml'));
		}
		unset($processor);

		// Core Native XML import, exactly as NativeImportExportPlugin does it.
		PluginRegistry::loadCategory('importexport', true);
		$nativePlugin = PluginRegistry::getPlugin('importexport', 'NativeImportExportPlugin'); /** @var $nativePlugin NativeImportExportPlugin */
		if (!$nativePlugin) return $this->_json(false, __('plugins.generic.reviewArchive.import.error.native'));
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_MANAGER);

		$document = new DOMDocument('1.0', 'utf-8');
		$document->loadXml($xml, LIBXML_PARSEHUGE);
		$filter = in_array($document->documentElement->tagName, ['article', 'articles']) ? 'native-xml=>article' : 'native-xml=>issue';
		unset($document);

		$context = $request->getContext();
		$user = $request->getUser();
		$deployment = new NativeImportExportDeployment($context, $user);
		libxml_use_internal_errors(true);
		$content = $nativePlugin->importSubmissions($xml, $filter, $deployment);
		$validationErrors = array_filter(libxml_get_errors(), function($a) {
			return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
		});
		libxml_clear_errors();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('content', $content);
		$templateMgr->assign('validationErrors', $validationErrors);
		foreach ([ASSOC_TYPE_ISSUE => 'issuesWarnings', ASSOC_TYPE_SUBMISSION => 'submissionsWarnings', ASSOC_TYPE_SECTION => 'sectionWarnings'] as $assocType => $var) {
			$found = $deployment->getProcessedObjectsWarnings($assocType);
			if (!empty($found)) $templateMgr->assign($var, $found);
		}
		$errorTypes = [ASSOC_TYPE_ISSUE => 'issuesErrors', ASSOC_TYPE_SUBMISSION => 'submissionsErrors', ASSOC_TYPE_SECTION => 'sectionErrors'];
		$foundErrors = false;
		foreach ($errorTypes as $assocType => $var) {
			$found = $deployment->getProcessedObjectsErrors($assocType);
			if (!empty($found)) {
				$templateMgr->assign($var, $found);
				$foundErrors = true;
			}
		}
		if ($foundErrors || !empty($validationErrors)) {
			foreach (array_keys($errorTypes) as $assocType) {
				$deployment->removeImportedObjects($assocType);
			}
		}
		$this->_deleteUpload($request);

		$templateMgr->assign([
			'raPlan' => $plan,
			'raImported' => !$foundErrors && empty($validationErrors),
			'raNativeResults' => $templateMgr->fetch($nativePlugin->getTemplateResource('results.tpl')),
		]);
		return $this->_json(true, $templateMgr->fetch(self::$plugin->getTemplateResource('importResults.tpl')));
	}

	//
	// Helpers
	//
	/**
	 * @param $request PKPRequest
	 * @return array processor options from the form
	 */
	protected function _options($request) {
		return [
			'labelReview' => (bool) $request->getUserVar('labelReview'),
			'dedupe' => (bool) $request->getUserVar('dedupe'),
			'dropProduction' => (bool) $request->getUserVar('dropProduction'),
			'dropOldRevisions' => (bool) $request->getUserVar('dropOldRevisions'),
		];
	}

	/**
	 * @param $request PKPRequest
	 * @return array [xml|null, meta|null, error|null]
	 */
	protected function _readUpload($request) {
		$uploadId = $this->_uploadId($request);
		if (!$uploadId) return [null, null, __('plugins.generic.reviewArchive.import.error.upload')];
		$path = $this->_uploadPath($request, $uploadId);
		$meta = @json_decode((string) @file_get_contents($path . '.json'), true);
		if (!is_array($meta) || $meta['next'] !== $meta['total'] || !is_readable($path)) {
			return [null, null, __('plugins.generic.reviewArchive.import.error.incomplete')];
		}
		clearstatcache(true, $path);
		if (filesize($path) !== $meta['size']) {
			return [null, null, __('plugins.generic.reviewArchive.import.error.incomplete')];
		}
		return [file_get_contents($path), $meta, null];
	}

	/**
	 * @param $request PKPRequest
	 * @return string|null
	 */
	protected function _uploadId($request) {
		$id = (string) $request->getUserVar('uploadId');
		return preg_match('/^[a-f0-9]{32}$/', $id) ? $id : null;
	}

	/**
	 * files_dir/reviewArchive/u{userId}/{uploadId}.xml
	 * @param $request PKPRequest
	 * @param $uploadId string
	 * @return string
	 */
	protected function _uploadPath($request, $uploadId) {
		$dir = $this->_uploadDir($request);
		if (!is_dir($dir)) mkdir($dir, 0700, true);
		return $dir . '/' . $uploadId . '.xml';
	}

	/**
	 * @param $request PKPRequest
	 * @return string
	 */
	protected function _uploadDir($request) {
		return rtrim(Config::getVar('files', 'files_dir'), '/') . '/reviewArchive/u' . (int) $request->getUser()->getId();
	}

	/**
	 * @param $request PKPRequest
	 */
	protected function _deleteUpload($request) {
		$uploadId = $this->_uploadId($request);
		if (!$uploadId) return;
		$path = $this->_uploadPath($request, $uploadId);
		@unlink($path);
		@unlink($path . '.json');
	}

	/**
	 * Remove this user's uploads older than one day.
	 * @param $request PKPRequest
	 */
	protected function _cleanupOldUploads($request) {
		$dir = $this->_uploadDir($request);
		if (!is_dir($dir)) return;
		foreach (glob($dir . '/*') ?: [] as $file) {
			if (is_file($file) && filemtime($file) < time() - 86400) @unlink($file);
		}
	}

	/**
	 * Large XML files need time and memory.
	 */
	protected function _raiseLimits() {
		@set_time_limit(0);
		$limit = ini_get('memory_limit');
		if ($limit !== '-1' && $this->_bytes($limit) < 1073741824) @ini_set('memory_limit', '1024M');
	}

	/**
	 * @param $value string e.g. "256M"
	 * @return int
	 */
	protected function _bytes($value) {
		$value = trim((string) $value);
		$number = (int) $value;
		switch (strtoupper(substr($value, -1))) {
			case 'G': return $number * 1073741824;
			case 'M': return $number * 1048576;
			case 'K': return $number * 1024;
		}
		return $number;
	}

	/**
	 * @param $status boolean
	 * @param $content string
	 * @return JSONMessage
	 */
	protected function _json($status, $content) {
		header('Content-Type: application/json');
		return new JSONMessage($status, $content);
	}
}
