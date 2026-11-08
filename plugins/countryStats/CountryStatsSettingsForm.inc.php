<?php
/**
 * @file plugins/generic/countryStats/CountryStatsSettingsForm.inc.php
 *
 * @class CountryStatsSettingsForm
 * @brief Settings form for the Country Statistics plugin.
 */

import('lib.pkp.classes.form.Form');

class CountryStatsSettingsForm extends Form {

	/** @var CountryStatsPlugin */
	private $_plugin;

	/** @var int */
	private $_contextId;

	/**
	 * Constructor
	 */
	public function __construct($plugin, $contextId) {
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->_plugin = $plugin;
		$this->_contextId = (int) $contextId;

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data from current settings.
	 */
	public function initData() {
		$settings = $this->_plugin->getAllSettings($this->_contextId);
		foreach ($settings as $key => $value) {
			$this->setData($key, $value);
		}
	}

	/**
	 * Read user input.
	 */
	public function readInputData() {
		$this->readUserVars(array(
			'uniqueStrategy',
			'orcidFallback',
			'countryDisplay',
			'sortOrder',
			'minThreshold',
			'includeUnknown',
			'enablePublicPage',
			'publicPageTitle',
			'showAuthorsTab',
			'showArticlesTab',
		));
	}

	/**
	 * Fetch the form template.
	 */
	public function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());

		// Build and assign the public page URL
		$dispatcher = $request->getDispatcher();
		$publicPageUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'countrystats', 'publicpage');
		$templateMgr->assign('publicPageUrl', $publicPageUrl);

		// Select options for the form
		$templateMgr->assign('uniqueStrategyOptions', array(
			'orcid' => __('plugins.generic.countryStats.filter.orcid'),
			'email' => __('plugins.generic.countryStats.filter.email'),
			'name'  => __('plugins.generic.countryStats.filter.fullName'),
		));
		$templateMgr->assign('orcidFallbackOptions', array(
			'email_name' => __('plugins.generic.countryStats.settings.orcidFallback.emailName'),
			'email'      => __('plugins.generic.countryStats.settings.orcidFallback.email'),
			'name'       => __('plugins.generic.countryStats.settings.orcidFallback.name'),
			'none'       => __('plugins.generic.countryStats.settings.orcidFallback.none'),
		));
		$templateMgr->assign('countryDisplayOptions', array(
			'both' => __('plugins.generic.countryStats.settings.countryDisplay.both'),
			'code' => __('plugins.generic.countryStats.settings.countryDisplay.code'),
			'name' => __('plugins.generic.countryStats.settings.countryDisplay.name'),
		));
		$templateMgr->assign('sortOrderOptions', array(
			'count_desc' => __('plugins.generic.countryStats.settings.sortOrder.countDesc'),
			'name_asc'   => __('plugins.generic.countryStats.settings.sortOrder.nameAsc'),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save settings.
	 */
	public function execute(...$functionArgs) {
		$settingsToSave = array(
			'uniqueStrategy'   => array('type' => 'string', 'default' => 'orcid'),
			'orcidFallback'    => array('type' => 'string', 'default' => 'email_name'),
			'countryDisplay'   => array('type' => 'string', 'default' => 'both'),
			'sortOrder'        => array('type' => 'string', 'default' => 'count_desc'),
			'minThreshold'     => array('type' => 'int',    'default' => 1),
			'includeUnknown'   => array('type' => 'bool',   'default' => false),
			'enablePublicPage' => array('type' => 'bool',   'default' => false),
			'publicPageTitle'  => array('type' => 'string', 'default' => 'International Reach'),
			'showAuthorsTab'   => array('type' => 'bool',   'default' => true),
			'showArticlesTab'  => array('type' => 'bool',   'default' => true),
		);

		foreach ($settingsToSave as $name => $meta) {
			$value = $this->getData($name);

			switch ($meta['type']) {
				case 'int':
					$value = (int) $value;
					break;
				case 'bool':
					$value = $value ? 1 : 0;
					break;
				case 'string':
				default:
					$value = trim((string) $value);
					if ($value === '') $value = $meta['default'];
					break;
			}

			$this->_plugin->updateSetting($this->_contextId, $name, $value, $meta['type'] === 'int' ? 'int' : 'string');
		}

		parent::execute(...$functionArgs);
	}
}
