<?php
/**
 * @file plugins/generic/countryStats/CountryStatsPlugin.inc.php
 *
 * Copyright (c) 2024 Country Statistics Plugin
 * Distributed under the GNU GPL v3.
 *
 * @class CountryStatsPlugin
 * @brief Main class for the Country Statistics plugin.
 *        Provides geographic distribution dashboards for authors and articles.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

define('NMI_TYPE_COUNTRY_STATS', 'NMI_TYPE_COUNTRY_STATS');

class CountryStatsPlugin extends GenericPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);

		if ($success && $this->getEnabled()) {
			// Register page handler for /countryStats URLs
			HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));

			// Add sidebar navigation item in management area
			HookRegistry::register('TemplateManager::display', array($this, 'addSidebarLink'));

			// Register frontend Navigation Menu Item type
			HookRegistry::register('NavigationMenus::itemTypes', array($this, 'addNavigationMenuItemTypes'));
			HookRegistry::register('NavigationMenus::displaySettings', array($this, 'setNavigationMenuItemDisplaySettings'));
		}

		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.countryStats.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.countryStats.description');
	}

	/**
	 * @copydoc Plugin::isSitePlugin()
	 */
	public function isSitePlugin() {
		return false;
	}

	// ------------------------------------------------------------------
	//  Actions (Plugin Gallery buttons)
	// ------------------------------------------------------------------

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $actionArgs) {
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		import('lib.pkp.classes.linkAction.LinkAction');

		$router = $request->getRouter();
		$actions = array();

		if ($this->getEnabled()) {
			// "View Statistics" button → opens dashboard page
			$dispatcher = $request->getDispatcher();
			$actions[] = new LinkAction(
				'dashboard',
				new RedirectAction(
					$dispatcher->url($request, ROUTE_PAGE, null, 'countrystats', 'dashboard')
				),
				__('plugins.generic.countryStats.dashboard'),
				null
			);

			// "Settings" button → opens modal
			$actions[] = new LinkAction(
				'settings',
				new AjaxModal(
					$router->url($request, null, null, 'manage', null,
						array_merge($actionArgs, array('verb' => 'settings'))
					),
					$this->getDisplayName()
				),
				__('manager.plugins.settings'),
				null
			);
		}

		return array_merge($actions, parent::getActions($request, $actionArgs));
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$this->import('CountryStatsSettingsForm');
				$form = new CountryStatsSettingsForm($this, $request->getContext()->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	// ------------------------------------------------------------------
	//  Hook Callbacks
	// ------------------------------------------------------------------

	/**
	 * Route /countrystats/* requests to our handler.
	 */
	public function callbackLoadHandler($hookName, $args) {
		$page =& $args[0];

		if ($page === 'countrystats') {
			require_once($this->getPluginPath() . '/CountryStatsHandler.inc.php');
			define('HANDLER_CLASS', 'CountryStatsHandler');
			return true;
		}
		return false;
	}

	/**
	 * Inject "Country Statistics" link into the management sidebar.
	 * Works on ALL backend pages so the menu item is always visible.
	 */
	public function addSidebarLink($hookName, $args) {
		$templateMgr = $args[0];

		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;

		// Only works with PageRouter (backend pages)
		$router = $request->getRouter();
		$routerClass = get_class($router);
		if (strpos($routerClass, 'PageRouter') === false) {
			return false;
		}

		// Check if the template state has a 'menu' — this indicates a backend page
		$menu = (array) $templateMgr->getState('menu');
		if (empty($menu)) {
			return false;
		}

		// Don't inject twice
		if (isset($menu['countrystats'])) {
			return false;
		}

		// Determine if we're currently on the countrystats page
		$requestedPage = $router->getRequestedPage($request);

		$dispatcher = $request->getDispatcher();
		$menu['countrystats'] = array(
			'name' => __('plugins.generic.countryStats.menuTitle'),
			'url'  => $dispatcher->url($request, ROUTE_PAGE, null, 'countrystats', 'dashboard'),
			'isCurrent' => ($requestedPage === 'countrystats'),
		);
		$templateMgr->setState(array('menu' => $menu));

		return false;
	}

	// ------------------------------------------------------------------
	//  Navigation Menu Item Type
	// ------------------------------------------------------------------

	/**
	 * Register custom Navigation Menu Item type so editors can add
	 * "Country Statistics" to any frontend navigation menu.
	 */
	public function addNavigationMenuItemTypes($hookName, $args) {
		$types =& $args[0];
		$types[NMI_TYPE_COUNTRY_STATS] = array(
			'title'       => __('plugins.generic.countryStats.navMenuItem.title'),
			'description' => __('plugins.generic.countryStats.navMenuItem.description'),
		);
	}

	/**
	 * Set URL and visibility for the Country Statistics NMI type.
	 */
	public function setNavigationMenuItemDisplaySettings($hookName, $args) {
		$navigationMenuItem = $args[0];

		if ($navigationMenuItem->getType() !== NMI_TYPE_COUNTRY_STATS) {
			return;
		}

		$request = Application::get()->getRequest();
		$context = $request->getContext();

		$isVisible = false;
		if ($context) {
			$isVisible = (bool) $this->getSetting($context->getId(), 'enablePublicPage');
		}
		$navigationMenuItem->setIsDisplayed($isVisible);

		if ($context) {
			$dispatcher = $request->getDispatcher();
			$navigationMenuItem->setUrl($dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'countrystats',
				'publicpage'
			));
		}
	}

	// ------------------------------------------------------------------
	//  Settings helpers
	// ------------------------------------------------------------------

	/**
	 * Get a plugin setting with default fallback.
	 */
	public function getSettingWithDefault($contextId, $name) {
		$val = $this->getSetting($contextId, $name);
		$defaults = $this->getDefaultSettings();
		return ($val !== null && $val !== '') ? $val : (isset($defaults[$name]) ? $defaults[$name] : null);
	}

	/**
	 * Default settings values.
	 */
	public function getDefaultSettings() {
		return array(
			'uniqueStrategy'   => 'orcid',
			'orcidFallback'    => 'email_name',  // email_name | email | name | none
			'countryDisplay'   => 'both',         // code | name | both
			'sortOrder'        => 'count_desc',   // count_desc | name_asc
			'minThreshold'     => 1,
			'includeUnknown'   => 0,
			'enablePublicPage' => 0,
			'publicPageTitle'  => 'International Reach',
			'showAuthorsTab'   => 1,
			'showArticlesTab'  => 1,
		);
	}

	/**
	 * Get all current settings merged with defaults.
	 */
	public function getAllSettings($contextId) {
		$defaults = $this->getDefaultSettings();
		$settings = array();
		foreach ($defaults as $key => $default) {
			$settings[$key] = $this->getSettingWithDefault($contextId, $key);
		}
		return $settings;
	}

	// ------------------------------------------------------------------
	//  DAO accessor
	// ------------------------------------------------------------------

	/**
	 * @return CountryStatsDAO
	 */
	public function getDAO() {
		$this->import('classes.CountryStatsDAO');
		return new CountryStatsDAO();
	}
}
