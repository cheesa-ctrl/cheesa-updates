<?php
/**
 * @file plugins/generic/articleStatisticsDisplay/ArticleStatisticsDisplaySettingsForm.inc.php
 *
 * Copyright (c) 2026 Kerim Sarıgül
 * Distributed under the GNU GPL v3.
 *
 * @class ArticleStatisticsDisplaySettingsForm
 * @brief Form for journal managers to modify Article Statistics Display plugin settings
 */

import('lib.pkp.classes.form.Form');

class ArticleStatisticsDisplaySettingsForm extends Form {

    /** @var ArticleStatisticsDisplayPlugin */
    private $_plugin;

    /** @var int */
    private $_contextId;

    /**
     * Constructor
     * @param $plugin ArticleStatisticsDisplayPlugin
     */
    public function __construct($plugin) {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->_plugin = $plugin;
        $this->_contextId = Application::get()->getRequest()->getContext()->getId();

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Initialize form data
     */
    public function initData() {
        $plugin = $this->_plugin;
        $contextId = $this->_contextId;

        $this->setData('showInArticleDetail', $plugin->getSetting($contextId, 'showInArticleDetail'));
        $this->setData('showInIssueToc', $plugin->getSetting($contextId, 'showInIssueToc'));
        $this->setData('showInHomepage', $plugin->getSetting($contextId, 'showInHomepage'));
        $this->setData('showAbstractViews', $plugin->getSetting($contextId, 'showAbstractViews'));
        $this->setData('showGalleyDownloads', $plugin->getSetting($contextId, 'showGalleyDownloads'));
        $this->setData('displayStyle', $plugin->getSetting($contextId, 'displayStyle') ?: 'badge');
        $this->setData('articleDetailPosition', $plugin->getSetting($contextId, 'articleDetailPosition') ?: 'underTitle');
    }

    /**
     * Assign form data to user-submitted data
     */
    public function readInputData() {
        $this->readUserVars(array(
            'showInArticleDetail',
            'showInIssueToc',
            'showInHomepage',
            'showAbstractViews',
            'showGalleyDownloads',
            'displayStyle',
            'articleDetailPosition'
        ));
    }

    /**
     * Fetch the form
     * @param $request PKPRequest
     * @param $template string|null
     * @param $display bool
     * @return string
     */
    public function fetch($request, $template = null, $display = false) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->_plugin->getName());
        
        // Only badge and inline styles
        $displayStyles = array(
            'badge' => __('plugins.generic.articleStatisticsDisplay.style.badge'),
            'inline' => __('plugins.generic.articleStatisticsDisplay.style.inline')
        );
        $templateMgr->assign('displayStyles', $displayStyles);
        
        // Three position options
        $articlePositions = array(
            'underTitle' => __('plugins.generic.articleStatisticsDisplay.position.underTitle'),
            'underAbstract' => __('plugins.generic.articleStatisticsDisplay.position.underAbstract'),
            'sidebar' => __('plugins.generic.articleStatisticsDisplay.position.sidebar')
        );
        $templateMgr->assign('articlePositions', $articlePositions);
        
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs) {
        $plugin = $this->_plugin;
        $contextId = $this->_contextId;

        $plugin->updateSetting($contextId, 'showInArticleDetail', $this->getData('showInArticleDetail') ? true : false);
        $plugin->updateSetting($contextId, 'showInIssueToc', $this->getData('showInIssueToc') ? true : false);
        $plugin->updateSetting($contextId, 'showInHomepage', $this->getData('showInHomepage') ? true : false);
        $plugin->updateSetting($contextId, 'showAbstractViews', $this->getData('showAbstractViews') ? true : false);
        $plugin->updateSetting($contextId, 'showGalleyDownloads', $this->getData('showGalleyDownloads') ? true : false);
        $plugin->updateSetting($contextId, 'displayStyle', $this->getData('displayStyle'));
        $plugin->updateSetting($contextId, 'articleDetailPosition', $this->getData('articleDetailPosition'));

        parent::execute(...$functionArgs);
    }
}
