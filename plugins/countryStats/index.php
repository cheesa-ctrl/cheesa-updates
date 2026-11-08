<?php
/**
 * @defgroup plugins_generic_countryStats Country Statistics Plugin
 */

/**
 * @file plugins/generic/countryStats/index.php
 *
 * Copyright (c) 2024 Country Statistics Plugin
 * Distributed under the GNU GPL v3.
 *
 * @brief Wrapper for the Country Statistics plugin.
 */

require_once('CountryStatsPlugin.inc.php');

return new CountryStatsPlugin();
