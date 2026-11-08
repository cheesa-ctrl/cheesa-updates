{**
 * templates/dashboard.tpl
 *
 * Admin dashboard for Country Statistics plugin.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="cs-dashboard" id="countryStatsDashboard">

	{* Plugin CSS *}
	<link rel="stylesheet" href="{$pluginPath}/css/countryStats.css" />

	{* Header *}
	<div class="cs-page-header">
		<h1>{translate key="plugins.generic.countryStats.dashboard.title"}</h1>
		{if $isAdmin}
		<div class="cs-header-right">
			<select class="cs-filter-select" id="csTimeFilter">
				<option value="all">{translate key="plugins.generic.countryStats.filter.allTime"}</option>
				<option value="12">{translate key="plugins.generic.countryStats.filter.last12"}</option>
				<option value="24">{translate key="plugins.generic.countryStats.filter.last24"}</option>
			</select>
			<select class="cs-filter-select" id="csStrategyFilter">
				<option value="orcid" {if $settings.uniqueStrategy == 'orcid'}selected{/if}>{translate key="plugins.generic.countryStats.filter.uniqueBy"}: {translate key="plugins.generic.countryStats.filter.orcid"}</option>
				<option value="email" {if $settings.uniqueStrategy == 'email'}selected{/if}>{translate key="plugins.generic.countryStats.filter.uniqueBy"}: {translate key="plugins.generic.countryStats.filter.email"}</option>
				<option value="name"  {if $settings.uniqueStrategy == 'name'}selected{/if}>{translate key="plugins.generic.countryStats.filter.uniqueBy"}: {translate key="plugins.generic.countryStats.filter.fullName"}</option>
			</select>
		</div>
		{/if}
	</div>

	{* Summary Cards *}
	<div class="cs-cards">
		<div class="cs-card">
			<div class="cs-card-icon cs-green">&#x1F4C4;</div>
			<div class="cs-card-value" id="csCardArticles">{$totalArticles}</div>
			<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.publishedArticles"}</div>
		</div>
		<div class="cs-card">
			<div class="cs-card-icon cs-teal">&#x270D;</div>
			<div class="cs-card-value" id="csCardAuthors">{$totalAuthors}</div>
			<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.uniqueAuthors"}</div>
		</div>
		<div class="cs-card">
			<div class="cs-card-icon cs-amber">&#x1F30D;</div>
			<div class="cs-card-value" id="csCardCountries">{$totalCountries}</div>
			<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.countries"}</div>
		</div>
		<div class="cs-card">
			<div class="cs-card-icon cs-blue">&#x1F4CA;</div>
			<div class="cs-card-value" id="csCardTopShare">{$topCountryShare|escape}</div>
			<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.topCountryShare"}</div>
		</div>
	</div>

	{* Charts Row *}
	<div class="cs-charts-row">
		<div class="cs-chart-card">
			<div class="cs-chart-title" id="csBarTitle">{translate key="plugins.generic.countryStats.dashboard.topCountries" count=12}</div>
			<div class="cs-bar-chart" id="csBarChart"></div>
		</div>
		<div class="cs-chart-card">
			<div class="cs-chart-title">{translate key="plugins.generic.countryStats.dashboard.authorDistribution"}</div>
			<div class="cs-donut-wrapper">
				<svg class="cs-donut-svg" viewBox="0 0 200 200" id="csDonutChart"></svg>
				<div class="cs-donut-legend" id="csDonutLegend"></div>
			</div>
		</div>
	</div>

	{* Table Tabs *}
	<div class="cs-tabs">
		{if $settings.showAuthorsTab}
		<div class="cs-tab active" data-tab="authors">&#x270D; {translate key="plugins.generic.countryStats.dashboard.authorsByCountry"}</div>
		{/if}
		{if $settings.showArticlesTab}
		<div class="cs-tab{if !$settings.showAuthorsTab} active{/if}" data-tab="articles">&#x1F4C4; {translate key="plugins.generic.countryStats.dashboard.articlesByCountry"}</div>
		{/if}
	</div>

	{* Authors Tab *}
	{if $settings.showAuthorsTab}
	<div class="cs-tab-content active" id="cs-tab-authors">
		<div class="cs-table-section">
			<div class="cs-table-toolbar">
				<input type="text" class="cs-search-box" placeholder="{translate key="plugins.generic.countryStats.dashboard.searchCountry"}" id="csSearchAuthors" />
				{if $isAdmin}
				<div class="cs-export-btns">
					<a class="cs-btn" href="{url page="countrystats" op="exportCSV" type="authors"}" target="_blank">&#x2913; {translate key="plugins.generic.countryStats.dashboard.exportCSV"}</a>
				</div>
				{/if}
			</div>
			<div class="cs-table-wrap">
				<table class="cs-table">
					<thead>
						<tr>
							<th style="width:40px">#</th>
							<th>{translate key="plugins.generic.countryStats.dashboard.country"}</th>
							<th style="width:80px">{translate key="plugins.generic.countryStats.dashboard.authors"}</th>
							<th style="width:100px">{translate key="plugins.generic.countryStats.dashboard.distribution"}</th>
							<th style="width:60px">{translate key="plugins.generic.countryStats.dashboard.share"}</th>
						</tr>
					</thead>
					<tbody id="csAuthorsBody"></tbody>
				</table>
			</div>
		</div>
	</div>
	{/if}

	{* Articles Tab *}
	{if $settings.showArticlesTab}
	<div class="cs-tab-content{if !$settings.showAuthorsTab} active{/if}" id="cs-tab-articles">
		<div class="cs-table-section">
			<div class="cs-table-toolbar">
				<input type="text" class="cs-search-box" placeholder="{translate key="plugins.generic.countryStats.dashboard.searchCountry"}" id="csSearchArticles" />
				{if $isAdmin}
				<div class="cs-export-btns">
					<a class="cs-btn" href="{url page="countrystats" op="exportCSV" type="articles"}" target="_blank">&#x2913; {translate key="plugins.generic.countryStats.dashboard.exportCSV"}</a>
				</div>
				{/if}
			</div>
			<div class="cs-table-wrap">
				<table class="cs-table">
					<thead>
						<tr>
							<th style="width:40px">#</th>
							<th>{translate key="plugins.generic.countryStats.dashboard.country"}</th>
							<th style="width:80px">{translate key="plugins.generic.countryStats.dashboard.articles"}</th>
							<th style="width:100px">{translate key="plugins.generic.countryStats.dashboard.distribution"}</th>
							<th style="width:60px">{translate key="plugins.generic.countryStats.dashboard.share"}</th>
						</tr>
					</thead>
					<tbody id="csArticlesBody"></tbody>
				</table>
			</div>
		</div>
	</div>
	{/if}

	{* Data Notes *}
	<div class="cs-data-note">
		<span><span class="cs-note-dot"></span> {translate key="plugins.generic.countryStats.note.uniqueBy" strategy=$settings.uniqueStrategy|upper}</span>
		<span><span class="cs-note-dot"></span> {translate key="plugins.generic.countryStats.note.publishedOnly"}</span>
		<span><span class="cs-note-dot"></span> {translate key="plugins.generic.countryStats.note.articleCounting"}</span>
	</div>

	{* Developer Credit *}
	<div class="cs-developer-credit">
		<a href="https://ojs-services.com" target="_blank" rel="noopener noreferrer">
			<span class="cs-dev-icon">&#x2666;</span>
			Developed by <strong>ojs-services.com</strong>
		</a>
	</div>
</div>

{* Inject data as JSON and load JS *}
<script>
	window.countryStatsData = {ldelim}
		authorsByCountry:  {$authorsByCountry},
		articlesByCountry: {$articlesByCountry},
		totalArticles:     {$totalArticles},
		totalAuthors:      {$totalAuthors},
		totalCountries:    {$totalCountries},
		countryDisplay:    '{$settings.countryDisplay|escape}',
		sortOrder:         '{$settings.sortOrder|escape}',
		isAdmin:           {if $isAdmin}true{else}false{/if},
		fetchUrl:          '{url page="countrystats" op="fetchData" escape=false}'
	{rdelim};
</script>
<script src="{$pluginPath}/js/countryStats.js"></script>

{/block}
