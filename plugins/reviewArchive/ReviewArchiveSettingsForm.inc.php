<?php

/**
 * @file plugins/generic/reviewArchive/ReviewArchiveSettingsForm.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchiveSettingsForm
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Journal-wide editor statement and reviewer certificate list.
 */

import('lib.pkp.classes.form.Form');

class ReviewArchiveSettingsForm extends Form {

	/** @var ReviewArchivePlugin */
	protected $_plugin;

	/** @var int */
	protected $_contextId;

	/**
	 * @param $plugin ReviewArchivePlugin
	 * @param $contextId int
	 */
	public function __construct($plugin, $contextId) {
		$this->_plugin = $plugin;
		$this->_contextId = $contextId;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	public function initData() {
		$this->setData('defaultStatement', $this->_plugin->getDefaultStatement($this->_contextId));
		$this->setData('certificateCsv', $this->_plugin->getCertificateCsv($this->_contextId));
		$this->setData('gdriveApiKey', (string) $this->_plugin->getSetting($this->_contextId, 'gdriveApiKey'));
		$this->setData('gdriveCollectionFolder', (string) $this->_plugin->getSetting($this->_contextId, 'gdriveCollectionFolder'));
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
		$this->readUserVars(['defaultStatement', 'certificateCsv', 'gdriveApiKey', 'gdriveCollectionFolder']);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	public function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$csv = (string) $this->getData('certificateCsv');
		$rows = 0;
		foreach (preg_split('/\r\n|\r|\n/', $csv) as $line) {
			$cols = str_getcsv(trim($line));
			if (count($cols) >= 3 && ctype_digit(trim($cols[0]))) $rows++;
		}
		$templateMgr->assign([
			'pluginName' => $this->_plugin->getName(),
			'certificateRows' => $rows,
		]);
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	public function execute(...$functionArgs) {
		$this->_plugin->updateSetting($this->_contextId, 'defaultStatement', trim((string) $this->getData('defaultStatement')), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'certificateCsv', (string) $this->getData('certificateCsv'), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'gdriveApiKey', trim((string) $this->getData('gdriveApiKey')), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'gdriveCollectionFolder', trim((string) $this->getData('gdriveCollectionFolder')), 'string');
		// A changed collection folder invalidates the cached sub-folder list.
		$this->_plugin->updateSetting($this->_contextId, 'gdriveCollCache', '', 'string');
		parent::execute(...$functionArgs);
	}
}
