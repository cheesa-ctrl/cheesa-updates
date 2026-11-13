<?php

/**
 * @file plugins/generic/journalHero/JournalHeroSettingsForm.inc.php
 *
 * @class JournalHeroSettingsForm
 * @brief Settings form for the Journal Hero plugin.
 */

import('lib.pkp.classes.form.Form');

class JournalHeroSettingsForm extends Form {

	/** @var JournalHeroPlugin */
	private $_plugin;

	/** @var int */
	private $_contextId;

	/** Checkbox settings (absent from POST when unchecked). */
	const CHECKBOXES = ['hideSiteName', 'showSubmit', 'useHomepageImage', 'showIssn', 'showFeatures', 'showCovers', 'showStrip'];

	public function __construct($plugin, $contextId) {
		$this->_plugin = $plugin;
		$this->_contextId = $contextId;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	public function initData() {
		foreach (array_keys(JournalHeroPlugin::DEFAULTS) as $name) {
			$this->setData($name, $this->_plugin->getHeroSetting($this->_contextId, $name));
		}
	}

	public function readInputData() {
		$this->readUserVars(array_keys(JournalHeroPlugin::DEFAULTS));
	}

	public function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'pluginName' => $this->_plugin->getName(),
			'pagesOptions' => [
				'home' => __('plugins.generic.journalHero.pages.home'),
				'all' => __('plugins.generic.journalHero.pages.all'),
			],
			'styleOptions' => [
				'carousel' => __('plugins.generic.journalHero.style.carousel'),
				'profile' => __('plugins.generic.journalHero.style.profile'),
			],
			'mediaOptions' => [
				'carousel' => __('plugins.generic.journalHero.media.carousel'),
				'current' => __('plugins.generic.journalHero.media.current'),
				'logo' => __('plugins.generic.journalHero.media.logo'),
				'image' => __('plugins.generic.journalHero.media.image'),
				'none' => __('plugins.generic.journalHero.media.none'),
			],
			'fontOptions' => [
				'inter' => __('plugins.generic.journalHero.font.inter'),
				'interSerif' => __('plugins.generic.journalHero.font.interSerif'),
				'jakarta' => __('plugins.generic.journalHero.font.jakarta'),
				'outfit' => __('plugins.generic.journalHero.font.outfit'),
				'theme' => __('plugins.generic.journalHero.font.theme'),
			],
			'layoutOptions' => [
				'boxed' => __('plugins.generic.journalHero.layout.boxed'),
				'full' => __('plugins.generic.journalHero.layout.full'),
			],
			'sizeOptions' => [
				'compact' => __('plugins.generic.journalHero.size.compact'),
				'normal' => __('plugins.generic.journalHero.size.normal'),
			],
			'positionOptions' => [
				'beforeHeader' => __('plugins.generic.journalHero.position.beforeHeader'),
				'beforeMenu' => __('plugins.generic.journalHero.position.beforeMenu'),
				'afterHeader' => __('plugins.generic.journalHero.position.afterHeader'),
				'beforeContent' => __('plugins.generic.journalHero.position.beforeContent'),
			],
		]);
		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs) {
		foreach (JournalHeroPlugin::DEFAULTS as $name => $default) {
			$value = $this->getData($name);
			if (in_array($name, self::CHECKBOXES)) {
				$value = (bool) $value;
				$type = 'bool';
			} elseif (is_int($default)) {
				$value = (int) $value;
				$type = 'int';
			} else {
				$value = trim((string) $value);
				$type = 'string';
			}
			$this->_plugin->updateSetting($this->_contextId, $name, $value, $type);
		}
		parent::execute(...$functionArgs);
	}
}
