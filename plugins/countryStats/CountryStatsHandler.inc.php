<?php
/**
 * @file plugins/generic/countryStats/CountryStatsHandler.inc.php
 *
 * @class CountryStatsHandler
 * @brief Request handler for Country Statistics pages (admin dashboard & public page).
 */

import('classes.handler.Handler');

class CountryStatsHandler extends Handler {

	/** @var CountryStatsPlugin */
	protected $_plugin;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Admin operations require Manager or Site Admin role
		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER),
			array('dashboard', 'fetchData', 'exportCSV')
		);

		// publicpage is NOT assigned to any role — authorize() handles it
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	public function authorize($request, &$args, $roleAssignments) {
		$op = $request->getRouter()->getRequestedOp($request);

		// Public page: context only, no role check — open to everyone
		if ($op === 'publicpage') {
			import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
			$this->addPolicy(new ContextRequiredPolicy($request));
			$this->markRoleAssignmentsChecked();
			return parent::authorize($request, $args, array());
		}

		// Admin pages: require context + role
		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));

		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach ($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(
				new RoleBasedHandlerOperationPolicy($request, $role, $operations)
			);
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	// ------------------------------------------------------------------
	//  Helper: get plugin instance
	// ------------------------------------------------------------------

	/**
	 * @return CountryStatsPlugin
	 */
	protected function _getPlugin() {
		if (!$this->_plugin) {
			$this->_plugin = PluginRegistry::getPlugin('generic', 'countrystatsplugin');
		}
		return $this->_plugin;
	}

	// ------------------------------------------------------------------
	//  Admin Dashboard
	// ------------------------------------------------------------------

	/**
	 * Display the admin statistics dashboard.
	 */
	public function dashboard($args, $request) {
		$this->_setupTemplate($request, true);

		$plugin   = $this->_getPlugin();
		$context  = $request->getContext();
		$contextId = $context->getId();

		$settings = $plugin->getAllSettings($contextId);
		$dao      = $plugin->getDAO();

		// Gather data
		$authorsByCountry  = $dao->getAuthorsByCountry(
			$contextId,
			$settings['uniqueStrategy'],
			$settings['orcidFallback']
		);
		$articlesByCountry = $dao->getArticlesByCountry($contextId);
		$totals            = $dao->getTotals(
			$contextId,
			$settings['uniqueStrategy'],
			$settings['orcidFallback']
		);

		// Filter by minThreshold
		$minThreshold = (int) $settings['minThreshold'];
		if ($minThreshold > 1) {
			$authorsByCountry  = array_filter($authorsByCountry, function($r) use ($minThreshold) {
				return (int)$r['author_count'] >= $minThreshold;
			});
			$articlesByCountry = array_filter($articlesByCountry, function($r) use ($minThreshold) {
				return (int)$r['article_count'] >= $minThreshold;
			});
		}

		// Filter unknown
		if (!$settings['includeUnknown']) {
			$authorsByCountry  = array_filter($authorsByCountry, function($r) {
				return !empty($r['country']);
			});
			$articlesByCountry = array_filter($articlesByCountry, function($r) {
				return !empty($r['country']);
			});
		}

		// Re-index arrays
		$authorsByCountry  = array_values($authorsByCountry);
		$articlesByCountry = array_values($articlesByCountry);

		// Build country name map using Sokil ISO codes (ships with OJS)
		$countryNames = $this->_getCountryNames();

		// Enrich data with country names
		$authorsByCountry  = $this->_enrichCountryData($authorsByCountry, $countryNames, 'author_count');
		$articlesByCountry = $this->_enrichCountryData($articlesByCountry, $countryNames, 'article_count');

		// Top country share
		$topCountryShare = '';
		if (!empty($authorsByCountry)) {
			$topRow = $authorsByCountry[0];
			$pct = $totals['totalAuthors'] > 0
				? round(($topRow['count'] / $totals['totalAuthors']) * 100, 1)
				: 0;
			$topCountryShare = $pct . '% (' . $topRow['code'] . ')';
		}

		// Pass data to template
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pageWidth'         => 'full',
			'pluginPath'        => $request->getBaseUrl() . '/' . $plugin->getPluginPath(),
			'authorsByCountry'  => json_encode($authorsByCountry),
			'articlesByCountry' => json_encode($articlesByCountry),
			'totalArticles'     => $totals['totalArticles'],
			'totalAuthors'      => $totals['totalAuthors'],
			'totalCountries'    => $totals['totalCountries'],
			'topCountryShare'   => $topCountryShare,
			'settings'          => $settings,
			'isAdmin'           => true,
		));

		$templateMgr->display($plugin->getTemplateResource('dashboard.tpl'));
	}

	// ------------------------------------------------------------------
	//  Public Page
	// ------------------------------------------------------------------

	/**
	 * Display the public statistics page.
	 */
	public function publicpage($args, $request) {
		$plugin   = $this->_getPlugin();
		$context  = $request->getContext();
		$contextId = $context->getId();

		$settings = $plugin->getAllSettings($contextId);

		// Check if public page is enabled
		if (!$settings['enablePublicPage']) {
			$request->getDispatcher()->handle404();
			return;
		}

		$this->_setupTemplate($request, false);

		$dao = $plugin->getDAO();

		$authorsByCountry  = $dao->getAuthorsByCountry(
			$contextId,
			$settings['uniqueStrategy'],
			$settings['orcidFallback']
		);
		$articlesByCountry = $dao->getArticlesByCountry($contextId);
		$totals            = $dao->getTotals(
			$contextId,
			$settings['uniqueStrategy'],
			$settings['orcidFallback']
		);

		// Filter
		$minThreshold = (int) $settings['minThreshold'];
		if ($minThreshold > 1) {
			$authorsByCountry  = array_filter($authorsByCountry, function($r) use ($minThreshold) {
				return (int)$r['author_count'] >= $minThreshold;
			});
			$articlesByCountry = array_filter($articlesByCountry, function($r) use ($minThreshold) {
				return (int)$r['article_count'] >= $minThreshold;
			});
		}
		if (!$settings['includeUnknown']) {
			$authorsByCountry  = array_filter($authorsByCountry, function($r) {
				return !empty($r['country']);
			});
			$articlesByCountry = array_filter($articlesByCountry, function($r) {
				return !empty($r['country']);
			});
		}

		$authorsByCountry  = array_values($authorsByCountry);
		$articlesByCountry = array_values($articlesByCountry);

		$countryNames = $this->_getCountryNames();
		$authorsByCountry  = $this->_enrichCountryData($authorsByCountry, $countryNames, 'author_count');
		$articlesByCountry = $this->_enrichCountryData($articlesByCountry, $countryNames, 'article_count');

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginPath'        => $request->getBaseUrl() . '/' . $plugin->getPluginPath(),
			'authorsByCountry'  => json_encode($authorsByCountry),
			'articlesByCountry' => json_encode($articlesByCountry),
			'totalArticles'     => $totals['totalArticles'],
			'totalAuthors'      => $totals['totalAuthors'],
			'totalCountries'    => $totals['totalCountries'],
			'topCountryShare'   => '',
			'settings'          => $settings,
			'isAdmin'           => false,
			'pageTitle'         => $settings['publicPageTitle'],
		));

		$templateMgr->display($plugin->getTemplateResource('publicpage.tpl'));
	}

	// ------------------------------------------------------------------
	//  AJAX Data Fetch (for filters)
	// ------------------------------------------------------------------

	/**
	 * Return JSON data for AJAX filter updates.
	 */
	public function fetchData($args, $request) {
		$plugin    = $this->_getPlugin();
		$context   = $request->getContext();
		$contextId = $context->getId();

		// Whitelist user input
		$allowedStrategies = array('orcid', 'email', 'name');
		$allowedFallbacks  = array('email_name', 'email', 'name', 'none');

		$strategy  = $request->getUserVar('strategy');
		$strategy  = in_array($strategy, $allowedStrategies) ? $strategy : 'orcid';

		$fallback  = $request->getUserVar('fallback');
		$fallback  = in_array($fallback, $allowedFallbacks) ? $fallback : 'email_name';

		$dateStart = $request->getUserVar('dateStart');
		$dateStart = ($dateStart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) ? $dateStart : null;

		$dateEnd   = $request->getUserVar('dateEnd');
		$dateEnd   = ($dateEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) ? $dateEnd : null;

		$threshold = max(1, (int)($request->getUserVar('threshold') ?: 1));

		$dao = $plugin->getDAO();

		$authorsByCountry  = $dao->getAuthorsByCountry($contextId, $strategy, $fallback, $dateStart, $dateEnd);
		$articlesByCountry = $dao->getArticlesByCountry($contextId, $dateStart, $dateEnd);
		$totals            = $dao->getTotals($contextId, $strategy, $fallback, $dateStart, $dateEnd);

		if ($threshold > 1) {
			$authorsByCountry  = array_values(array_filter($authorsByCountry, function($r) use ($threshold) {
				return (int)$r['author_count'] >= $threshold;
			}));
			$articlesByCountry = array_values(array_filter($articlesByCountry, function($r) use ($threshold) {
				return (int)$r['article_count'] >= $threshold;
			}));
		}

		$countryNames = $this->_getCountryNames();
		$authorsByCountry  = $this->_enrichCountryData($authorsByCountry, $countryNames, 'author_count');
		$articlesByCountry = $this->_enrichCountryData($articlesByCountry, $countryNames, 'article_count');

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array(
			'authorsByCountry'  => $authorsByCountry,
			'articlesByCountry' => $articlesByCountry,
			'totals'            => $totals,
		));
		exit;
	}

	// ------------------------------------------------------------------
	//  CSV Export
	// ------------------------------------------------------------------

	/**
	 * Export data as CSV download.
	 */
	public function exportCSV($args, $request) {
		$plugin    = $this->_getPlugin();
		$context   = $request->getContext();
		$contextId = $context->getId();
		$settings  = $plugin->getAllSettings($contextId);
		$type      = $request->getUserVar('type');
		$type      = ($type === 'articles') ? 'articles' : 'authors';

		$dao = $plugin->getDAO();

		if ($type === 'articles') {
			$data = $dao->getArticlesByCountry($contextId);
			$countryNames = $this->_getCountryNames();
			$data = $this->_enrichCountryData($data, $countryNames, 'article_count');
			$header = array('Country Code', 'Country Name', 'Article Count');
			$filename = 'articles_by_country_' . date('Y-m-d') . '.csv';
		} else {
			$data = $dao->getAuthorsByCountry(
				$contextId,
				$settings['uniqueStrategy'],
				$settings['orcidFallback']
			);
			$countryNames = $this->_getCountryNames();
			$data = $this->_enrichCountryData($data, $countryNames, 'author_count');
			$header = array('Country Code', 'Country Name', 'Author Count');
			$filename = 'authors_by_country_' . date('Y-m-d') . '.csv';
		}

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		$fp = fopen('php://output', 'w');
		// UTF-8 BOM for Excel
		fwrite($fp, "\xEF\xBB\xBF");
		fputcsv($fp, $header);

		foreach ($data as $row) {
			fputcsv($fp, array($row['code'], $row['name'], $row['count']));
		}

		fclose($fp);
		exit;
	}

	// ------------------------------------------------------------------
	//  Private helpers
	// ------------------------------------------------------------------

	/**
	 * Set up the template (backend page = true, frontend = false).
	 *
	 * For backend pages we also define APT_BACKEND_PAGE so that other
	 * plugins (e.g. ThesesPlugin) that maintain their own list of known
	 * backend pages can still inject their sidebar menu items when the
	 * user is on our custom /countryStats page.
	 */
	protected function _setupTemplate($request, $isBackend = true) {
		if ($isBackend) {
			$this->_isBackendPage = true;
			if (!defined('APT_BACKEND_PAGE')) {
				define('APT_BACKEND_PAGE', true);
			}
		}
		parent::setupTemplate($request);
	}

	/**
	 * Get ISO 3166-1 country name map.
	 * Uses Sokil ISO Codes library (bundled with OJS 3.3).
	 *
	 * @return array ['TR' => 'Turkey', 'US' => 'United States', ...]
	 */
	protected function _getCountryNames() {
		static $map = null;
		if ($map !== null) return $map;

		$map = array();

		// Try Sokil ISO codes (bundled with PKP lib)
		try {
			if (class_exists('\Sokil\IsoCodes\IsoCodesFactory')) {
				$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
				$countries = $isoCodes->getCountries();
				foreach ($countries as $country) {
					$map[$country->getAlpha2()] = $country->getName();
				}
			}
		} catch (\Exception $e) {
			// Fallback below
		}

		// If Sokil is not available, use a minimal hardcoded list
		if (empty($map)) {
			$map = $this->_getFallbackCountryNames();
		}

		return $map;
	}

	/**
	 * Fallback country name list (ISO 3166-1 alpha-2 → English name).
	 */
	protected function _getFallbackCountryNames() {
		return array(
			'AF'=>'Afghanistan','AL'=>'Albania','DZ'=>'Algeria','AD'=>'Andorra','AO'=>'Angola',
			'AR'=>'Argentina','AM'=>'Armenia','AU'=>'Australia','AT'=>'Austria','AZ'=>'Azerbaijan',
			'BH'=>'Bahrain','BD'=>'Bangladesh','BY'=>'Belarus','BE'=>'Belgium','BA'=>'Bosnia and Herzegovina',
			'BR'=>'Brazil','BG'=>'Bulgaria','CA'=>'Canada','CL'=>'Chile','CN'=>'China',
			'CO'=>'Colombia','HR'=>'Croatia','CU'=>'Cuba','CY'=>'Cyprus','CZ'=>'Czechia',
			'DK'=>'Denmark','EG'=>'Egypt','EE'=>'Estonia','ET'=>'Ethiopia','FI'=>'Finland',
			'FR'=>'France','GE'=>'Georgia','DE'=>'Germany','GH'=>'Ghana','GR'=>'Greece',
			'HU'=>'Hungary','IN'=>'India','ID'=>'Indonesia','IR'=>'Iran','IQ'=>'Iraq',
			'IE'=>'Ireland','IL'=>'Israel','IT'=>'Italy','JP'=>'Japan','JO'=>'Jordan',
			'KZ'=>'Kazakhstan','KE'=>'Kenya','KR'=>'South Korea','KW'=>'Kuwait','LB'=>'Lebanon',
			'LY'=>'Libya','LT'=>'Lithuania','LU'=>'Luxembourg','MY'=>'Malaysia','MX'=>'Mexico',
			'MA'=>'Morocco','NL'=>'Netherlands','NZ'=>'New Zealand','NG'=>'Nigeria','NO'=>'Norway',
			'OM'=>'Oman','PK'=>'Pakistan','PS'=>'Palestine','PE'=>'Peru','PH'=>'Philippines',
			'PL'=>'Poland','PT'=>'Portugal','QA'=>'Qatar','RO'=>'Romania','RU'=>'Russia',
			'SA'=>'Saudi Arabia','RS'=>'Serbia','SG'=>'Singapore','SK'=>'Slovakia','SI'=>'Slovenia',
			'ZA'=>'South Africa','ES'=>'Spain','SD'=>'Sudan','SE'=>'Sweden','CH'=>'Switzerland',
			'SY'=>'Syria','TW'=>'Taiwan','TH'=>'Thailand','TN'=>'Tunisia','TR'=>'Turkey',
			'UA'=>'Ukraine','AE'=>'United Arab Emirates','GB'=>'United Kingdom','US'=>'United States',
			'UY'=>'Uruguay','UZ'=>'Uzbekistan','VE'=>'Venezuela','VN'=>'Vietnam','YE'=>'Yemen',
		);
	}

	/**
	 * Enrich raw DB data with country names and normalize structure.
	 *
	 * @param array  $rows       Raw DB rows
	 * @param array  $nameMap    Country code → name
	 * @param string $countField 'author_count' or 'article_count'
	 * @return array  [ ['code'=>'TR', 'name'=>'Turkey', 'count'=>549], ... ]
	 */
	protected function _enrichCountryData($rows, $nameMap, $countField) {
		$result = array();
		foreach ($rows as $row) {
			$code = strtoupper(trim($row['country']));
			if (empty($code)) {
				$code = 'XX';
				$name = 'Unknown';
			} else {
				$name = isset($nameMap[$code]) ? $nameMap[$code] : $code;
			}
			$result[] = array(
				'code'  => $code,
				'name'  => $name,
				'count' => (int) $row[$countField],
			);
		}
		return $result;
	}
}
