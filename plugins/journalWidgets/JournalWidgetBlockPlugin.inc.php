<?php

/**
 * @file plugins/generic/journalWidgets/JournalWidgetBlockPlugin.inc.php
 *
 * Satu blok sidebar (dibuat 12 kali oleh JournalWidgetsPlugin), meniru pola Custom Block Manager OJS 3.3.
 */

import('lib.pkp.classes.plugins.BlockPlugin');

class JournalWidgetBlockPlugin extends BlockPlugin {

	/** @var string */
	private $_widgetId;
	/** @var string */
	private $_label;
	/** @var JournalWidgetsPlugin */
	private $_parentPlugin;

	public function __construct($widgetId, $label, $parentPlugin) {
		$this->_widgetId = $widgetId;
		$this->_label = $label;
		$this->_parentPlugin = $parentPlugin;
		parent::__construct();
	}

	public function getManagerPlugin() { return $this->_parentPlugin; }

	/** Nama unik blok (dipakai OJS untuk menyimpan urutan sidebar). */
	public function getName() { return 'journalwidget' . $this->_widgetId; }

	public function getPluginPath() { return $this->_parentPlugin->getPluginPath(); }

	public function getTemplatePath($inCore = false) { return $this->_parentPlugin->getTemplatePath($inCore); }

	/** Tidak tampil sebagai plugin terpisah di daftar plugin; diatur dari plugin induk. */
	public function getHideManagement() { return true; }

	public function getDisplayName() { return 'Widget: ' . $this->_label; }

	public function getDescription() { return 'Journal Portal Widgets — ' . $this->_label; }

	public function getContents($templateMgr, $request = null) {
		return $this->_parentPlugin->renderWidget($this->_widgetId, $request);
	}

	public function getBlockContext() {
		if (!Config::getVar('general', 'installed')) return BLOCK_CONTEXT_SIDEBAR;
		return parent::getBlockContext();
	}

	public function getEnabled($contextId = null) {
		if (!Config::getVar('general', 'installed')) return true;
		return parent::getEnabled($contextId);
	}
}
