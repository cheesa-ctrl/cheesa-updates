<?php
/**
 * @file plugins/generic/articleStatisticsDisplay/ArticleStatisticsDisplayPlugin.inc.php
 *
 * Copyright (c) 2026 Kerim Sarıgül
 * Distributed under the GNU GPL v3.
 *
 * @class ArticleStatisticsDisplayPlugin
 * @brief Plugin to display article view and galley download statistics
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class ArticleStatisticsDisplayPlugin extends GenericPlugin {

    /** @var bool Flag to prevent duplicate rendering on article page */
    private $_articleStatsRendered = false;

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        if ($this->getEnabled($mainContextId)) {
            // Add custom CSS
            HookRegistry::register('TemplateManager::display', array($this, 'addCustomStyles'));
            
            // Hook for article summary in issue TOC and homepage
            HookRegistry::register('Templates::Issue::Issue::Article', array($this, 'displayArticleSummaryStats'));
            
            // Hook for article detail page
            // We'll inject via a hidden div and use JS to move it to the right position
            HookRegistry::register('Templates::Article::Main', array($this, 'displayArticleMainStats'));
            
            // Add positioning JavaScript
            HookRegistry::register('TemplateManager::display', array($this, 'addPositioningScript'));
        }

        return true;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName() {
        return __('plugins.generic.articleStatisticsDisplay.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription() {
        return __('plugins.generic.articleStatisticsDisplay.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs) {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $actions = parent::getActions($request, $actionArgs);
        
        if ($this->getEnabled()) {
            $actions = array_merge(
                array(
                    new LinkAction(
                        'settings',
                        new AjaxModal(
                            $router->url($request, null, null, 'manage', null, array(
                                'verb' => 'settings',
                                'plugin' => $this->getName(),
                                'category' => 'generic'
                            )),
                            $this->getDisplayName()
                        ),
                        __('manager.plugins.settings'),
                        null
                    ),
                ),
                $actions
            );
        }
        
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $this->import('ArticleStatisticsDisplaySettingsForm');
                $form = new ArticleStatisticsDisplaySettingsForm($this);
                
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                }
                
                $form->initData();
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Add custom CSS styles to the page
     */
    public function addCustomStyles($hookName, $args) {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        
        $templateMgr->addStyleSheet(
            'articleStatisticsDisplay',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/articleStatisticsDisplay.css',
            array(
                'contexts' => array('frontend'),
                'priority' => STYLE_SEQUENCE_LAST
            )
        );
        
        return false;
    }

    /**
     * Add JavaScript for positioning and button style
     */
    public function addPositioningScript($hookName, $args) {
        $templateMgr = $args[0];
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        $displayStyle = $this->getSetting($context->getId(), 'displayStyle') ?: 'badge';
        $position = $this->getSetting($context->getId(), 'articleDetailPosition') ?: 'underTitle';
        
        // Add main positioning script
        $templateMgr->addJavaScript(
            'articleStatisticsDisplayMain',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/main.js',
            array(
                'contexts' => array('frontend'),
                'priority' => STYLE_SEQUENCE_LAST
            )
        );
        
        // Pass settings to JavaScript
        $templateMgr->addHeader(
            'asdSettings',
            '<script>var asdSettings = ' . json_encode(array(
                'position' => $position,
                'style' => $displayStyle
            )) . ';</script>',
            array(
                'contexts' => array('frontend')
            )
        );
        
        return false;
    }

    /**
     * Display statistics in article summary (Issue TOC & Homepage)
     */
    public function displayArticleSummaryStats($hookName, $args) {
        $templateMgr =& $args[1];
        $output =& $args[2];
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        $showInIssueToc = $this->getSetting($context->getId(), 'showInIssueToc');
        $showInHomepage = $this->getSetting($context->getId(), 'showInHomepage');
        
        $requestedPage = $request->getRequestedPage();
        $isHomepage = empty($requestedPage) || $requestedPage === 'index';
        
        if (($isHomepage && !$showInHomepage) || (!$isHomepage && !$showInIssueToc)) {
            return false;
        }
        
        $article = $templateMgr->getTemplateVars('article');
        if (!$article) {
            return false;
        }
        
        $showAbstractViews = $this->getSetting($context->getId(), 'showAbstractViews');
        $showGalleyDownloads = $this->getSetting($context->getId(), 'showGalleyDownloads');
        $displayStyle = $this->getSetting($context->getId(), 'displayStyle') ?: 'badge';
        
        $statsHtml = $this->buildStatsHtml($article, $showAbstractViews, $showGalleyDownloads, 'summary', $displayStyle);
        
        if (!empty($statsHtml)) {
            $output .= $statsHtml;
        }
        
        return false;
    }

    /**
     * Display statistics in article main view (under title)
     */
    public function displayArticleMainStats($hookName, $args) {
        // Prevent duplicate rendering
        if ($this->_articleStatsRendered) {
            return false;
        }
        
        $templateMgr =& $args[1];
        $output =& $args[2];
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        if (!$this->getSetting($context->getId(), 'showInArticleDetail')) {
            return false;
        }
        
        $article = $templateMgr->getTemplateVars('article');
        if (!$article) {
            return false;
        }
        
        $showAbstractViews = $this->getSetting($context->getId(), 'showAbstractViews');
        $showGalleyDownloads = $this->getSetting($context->getId(), 'showGalleyDownloads');
        $displayStyle = $this->getSetting($context->getId(), 'displayStyle') ?: 'badge';
        
        // For button style on article page, we handle it differently (via JS)
        // But we still show abstract views with a button
        $statsHtml = $this->buildStatsHtml($article, $showAbstractViews, $showGalleyDownloads, 'detail', $displayStyle);
        
        if (!empty($statsHtml)) {
            $output .= $statsHtml;
            $this->_articleStatsRendered = true;
        }
        
        return false;
    }

    /**
     * Build the HTML for statistics display
     */
    protected function buildStatsHtml($article, $showAbstractViews, $showGalleyDownloads, $displayContext, $displayStyle) {
        // Get abstract views
        $abstractViews = 0;
        if ($showAbstractViews && method_exists($article, 'getViews')) {
            $abstractViews = (int)$article->getViews();
        }
        
        // Get galley downloads
        $galleyStats = array();
        if ($showGalleyDownloads) {
            $galleys = $article->getGalleys();
            if ($galleys) {
                foreach ($galleys as $galley) {
                    $views = 0;
                    if (method_exists($galley, 'getViews')) {
                        $views = (int)$galley->getViews();
                    }
                    $galleyStats[] = array(
                        'label' => $galley->getGalleyLabel(),
                        'views' => $views,
                        'fileType' => $galley->getFileType()
                    );
                }
            }
        }
        
        // Summary (list views) - always use compact style
        if ($displayContext === 'summary') {
            return $this->buildSummaryHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads);
        }
        
        // Detail page - use selected style (badge or inline)
        if ($displayStyle === 'inline') {
            return $this->buildInlineHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads, $displayContext);
        } else {
            return $this->buildBadgeHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads, $displayContext);
        }
    }

    /**
     * Build Summary HTML (Compact buttons for list views)
     */
    protected function buildSummaryHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads) {
        $html = '<span class="asd-stats asd-stats-summary">';
        
        if ($showAbstractViews) {
            $html .= '<span class="asd-stat-compact asd-views" title="' . __('plugins.generic.articleStatisticsDisplay.abstractViews') . '">';
            $html .= '<span class="asd-icon">&#128065;</span> ';
            $html .= '<span class="asd-count">' . number_format($abstractViews) . '</span>';
            $html .= '</span>';
        }
        
        if ($showGalleyDownloads && !empty($galleyStats)) {
            foreach ($galleyStats as $galley) {
                $html .= '<span class="asd-stat-compact asd-downloads" title="' . htmlspecialchars($galley['label']) . ' ' . __('plugins.generic.articleStatisticsDisplay.downloads') . '">';
                $html .= '<span class="asd-icon">&#128229;</span> ';
                $html .= '<span class="asd-count">' . number_format($galley['views']) . '</span>';
                $html .= '</span>';
            }
        }
        
        $html .= '</span>';
        
        return $html;
    }

    /**
     * Build Badge Style HTML (Default - Colorful badges with labels)
     */
    protected function buildBadgeHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads, $displayContext) {
        $containerClass = ($displayContext === 'detail') ? 'asd-stats asd-stats-detail asd-style-badge' : 'asd-stats asd-stats-summary asd-style-badge';
        
        // Use span for summary (inline), div for detail (block)
        $tag = ($displayContext === 'detail') ? 'div' : 'span';
        
        $html = '<' . $tag . ' class="' . $containerClass . '">';
        
        if ($showAbstractViews) {
            $html .= '<span class="asd-stat asd-abstract-views" title="' . __('plugins.generic.articleStatisticsDisplay.abstractViews') . '">';
            $html .= '<span class="asd-icon">&#128065;</span> ';
            $html .= '<span class="asd-label">' . __('plugins.generic.articleStatisticsDisplay.abstractViews') . ':</span> ';
            $html .= '<span class="asd-count">' . number_format($abstractViews) . '</span>';
            $html .= '</span>';
        }
        
        if ($showGalleyDownloads && !empty($galleyStats)) {
            foreach ($galleyStats as $galley) {
                $html .= '<span class="asd-stat asd-galley-downloads" title="' . htmlspecialchars($galley['label']) . ' ' . __('plugins.generic.articleStatisticsDisplay.downloads') . '">';
                $html .= '<span class="asd-icon">&#128229;</span> ';
                $html .= '<span class="asd-label">' . htmlspecialchars($galley['label']) . ' ' . __('plugins.generic.articleStatisticsDisplay.downloads') . ':</span> ';
                $html .= '<span class="asd-count">' . number_format($galley['views']) . '</span>';
                $html .= '</span>';
            }
        }
        
        $html .= '</' . $tag . '>';
        
        return $html;
    }

    /**
     * Build Inline Style HTML (Clean text with labels)
     */
    protected function buildInlineHtml($abstractViews, $galleyStats, $showAbstractViews, $showGalleyDownloads, $displayContext) {
        $containerClass = ($displayContext === 'detail') ? 'asd-stats asd-stats-detail asd-style-inline' : 'asd-stats asd-stats-summary asd-style-inline';
        
        // Use span for summary (inline), div for detail (block)
        $tag = ($displayContext === 'detail') ? 'div' : 'span';
        
        $html = '<' . $tag . ' class="' . $containerClass . '">';
        
        if ($showAbstractViews) {
            $html .= '<span class="asd-stat-inline">';
            $html .= '<span class="asd-icon">&#128065;</span> ';
            $html .= '<span class="asd-label">' . __('plugins.generic.articleStatisticsDisplay.abstractViews') . ':</span> ';
            $html .= '<span class="asd-count">' . number_format($abstractViews) . '</span>';
            $html .= '</span>';
        }
        
        if ($showGalleyDownloads && !empty($galleyStats)) {
            foreach ($galleyStats as $galley) {
                $html .= '<span class="asd-stat-inline">';
                $html .= '<span class="asd-icon">&#128229;</span> ';
                $html .= '<span class="asd-label">' . htmlspecialchars($galley['label']) . ' ' . __('plugins.generic.articleStatisticsDisplay.downloads') . ':</span> ';
                $html .= '<span class="asd-count">' . number_format($galley['views']) . '</span>';
                $html .= '</span>';
            }
        }
        
        $html .= '</' . $tag . '>';
        
        return $html;
    }

    /**
     * @copydoc Plugin::getInstallSitePluginSettingsFile()
     */
    public function getInstallSitePluginSettingsFile() {
        return $this->getPluginPath() . '/settings.xml';
    }
}
