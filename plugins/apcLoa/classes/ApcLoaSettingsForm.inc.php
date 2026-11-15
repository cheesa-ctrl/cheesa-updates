<?php

/**
 * @file plugins/generic/apcLoa/classes/ApcLoaSettingsForm.inc.php
 *
 * @class ApcLoaSettingsForm
 * @brief Journal settings for LoA & APC.
 */

import('lib.pkp.classes.form.Form');

class ApcLoaSettingsForm extends Form {

	private $_plugin;
	private $_contextId;

	public function __construct($plugin, $contextId) {
		$this->_plugin = $plugin;
		$this->_contextId = $contextId;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorCustom($this, 'currency', 'required', 'plugins.generic.apcLoa.settings.currency.invalid', function ($v) {
			return (bool) preg_match('/^[A-Za-z]{3}$/', trim((string) $v));
		}));
		$this->addCheck(new FormValidatorCustom($this, 'apcFee', 'required', 'plugins.generic.apcLoa.settings.apcFee.invalid', function ($v) {
			return is_numeric($v) && (float) $v >= 0;
		}));
	}

	public function initData() {
		foreach (array_keys(ApcLoaPlugin::DEFAULTS) as $name) {
			$this->setData($name, $this->_plugin->setting($this->_contextId, $name));
		}
	}

	public function readInputData() {
		$this->readUserVars(array_keys(ApcLoaPlugin::DEFAULTS));
	}

	public function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();
		$templateMgr->assign([
			'pluginName' => $this->_plugin->getName(),
			'sendToOptions' => [
				'all' => __('plugins.generic.apcLoa.settings.sendTo.all'),
				'primary' => __('plugins.generic.apcLoa.settings.sendTo.primary'),
			],
			'lineOptions' => [
				'double' => __('plugins.generic.apcLoa.settings.line.double'),
				'single' => __('plugins.generic.apcLoa.settings.line.single'),
				'thick' => __('plugins.generic.apcLoa.settings.line.thick'),
				'none' => __('plugins.generic.apcLoa.settings.line.none'),
			],
			'paperOptions' => ['A4' => 'A4 (210 × 297 mm)', 'F4' => 'F4 / Folio (215 × 330 mm)', 'Letter' => 'Letter (8.5 × 11 in)'],
			'midtransNotifyUrl' => $this->_plugin->pageUrl($request, 'notify', 'midtrans'),
			'xenditNotifyUrl' => $this->_plugin->pageUrl($request, 'notify', 'xendit'),
			'dashboardUrl' => $this->_plugin->pageUrl($request, 'dashboard'),
			'previewUrl' => $this->_plugin->pageUrl($request, 'preview', 'loa'),
			'authorPageUrl' => $this->_plugin->pageUrl($request),
		]);
		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs) {
		foreach (ApcLoaPlugin::DEFAULTS as $name => $default) {
			$value = $this->getData($name);
			if (in_array($name, ApcLoaPlugin::CHECKBOXES)) {
				$this->_plugin->updateSetting($this->_contextId, $name, (bool) $value, 'bool');
			} elseif ($name === 'dueDays') {
				$this->_plugin->updateSetting($this->_contextId, $name, max(1, (int) $value), 'int');
			} elseif ($name === 'apcFee') {
				$this->_plugin->updateSetting($this->_contextId, $name, (string) max(0, (float) $value), 'string');
			} elseif ($name === 'currency') {
				$this->_plugin->updateSetting($this->_contextId, $name, strtoupper(trim((string) $value)), 'string');
			} else {
				$this->_plugin->updateSetting($this->_contextId, $name, trim((string) $value), 'string');
			}
		}
		parent::execute(...$functionArgs);
	}
}
