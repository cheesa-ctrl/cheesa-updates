<?php

/**
 * @file plugins/generic/journalWidgets/JournalWidgetsSettingsForm.inc.php
 */

import('lib.pkp.classes.form.Form');

class JournalWidgetsSettingsForm extends Form {

	/** @var JournalWidgetsPlugin */
	public $plugin;

	public function __construct($plugin) {
		parent::__construct($plugin->getTemplateResource('settings.tpl'));
		$this->plugin = $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	private function fields() { return array_keys(JournalWidgetsPlugin::defaults()); }

	public function initData() {
		$contextId = Application::get()->getRequest()->getContext()->getId();
		foreach ($this->fields() as $f) $this->setData($f, $this->plugin->opt($contextId, $f));
		parent::initData();
	}

	public function readInputData() {
		$this->readUserVars($this->fields());
		parent::readInputData();
	}

	public function fetch($request, $template = null, $display = false) {
		$tm = TemplateManager::getManager($request);
		$tm->assign('pluginName', $this->plugin->getName());
		$tm->assign('jwWidgets', JournalWidgetsPlugin::WIDGETS);
		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$contextId = $request->getContext()->getId();
		foreach ($this->fields() as $f) {
			$this->plugin->updateSetting($contextId, $f, trim((string) $this->getData($f)), 'string');
		}
		$this->plugin->clearCache($contextId);
		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_SUCCESS);
		return parent::execute(...$functionArgs);
	}
}
