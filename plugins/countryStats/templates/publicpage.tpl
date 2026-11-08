{**
 * templates/publicpage.tpl
 *
 * Public-facing statistics page for Country Statistics plugin.
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$pageTitle}

<div class="page cs-public-page">

	<link rel="stylesheet" href="{$pluginPath}/css/countryStats.css" />

	<header class="cs-public-header">
		<h2>{$pageTitle|escape}</h2>
		<p class="cs-public-subtitle">{translate key="plugins.generic.countryStats.public.subtitle"}</p>
	</header>

	<div class="cs-dashboard" id="countryStatsDashboard">

		{* Summary Cards *}
		<div class="cs-cards">
			<div class="cs-card">
				<div class="cs-card-icon cs-green">&#x1F4C4;</div>
				<div class="cs-card-value">{$totalArticles}</div>
				<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.publishedArticles"}</div>
			</div>
			<div class="cs-card">
				<div class="cs-card-icon cs-teal">&#x270D;</div>
				<div class="cs-card-value">{$totalAuthors}</div>
				<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.uniqueAuthors"}</div>
			</div>
			<div class="cs-card">
				<div class="cs-card-icon cs-amber">&#x1F30D;</div>
				<div class="cs-card-value">{$totalCountries}</div>
				<div class="cs-card-label">{translate key="plugins.generic.countryStats.dashboard.countries"}</div>
			</div>
		</div>

		{* Charts *}
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
			<div class="cs-tab active" data-tab="authors">{translate key="plugins.generic.countryStats.dashboard.authorsByCountry"}</div>
			{/if}
			{if $settings.showArticlesTab}
			<div class="cs-tab{if !$settings.showAuthorsTab} active{/if}" data-tab="articles">{translate key="plugins.generic.countryStats.dashboard.articlesByCountry"}</div>
			{/if}
		</div>

		{if $settings.showAuthorsTab}
		<div class="cs-tab-content active" id="cs-tab-authors">
			<div class="cs-table-section">
				<div class="cs-table-toolbar">
					<input type="text" class="cs-search-box" placeholder="{translate key="plugins.generic.countryStats.dashboard.searchCountry"}" id="csSearchAuthors" />
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

		{if $settings.showArticlesTab}
		<div class="cs-tab-content{if !$settings.showAuthorsTab} active{/if}" id="cs-tab-articles">
			<div class="cs-table-section">
				<div class="cs-table-toolbar">
					<input type="text" class="cs-search-box" placeholder="{translate key="plugins.generic.countryStats.dashboard.searchCountry"}" id="csSearchArticles" />
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
</div>

<script>
	window.countryStatsData = {ldelim}
		authorsByCountry:  {$authorsByCountry},
		articlesByCountry: {$articlesByCountry},
		totalArticles:     {$totalArticles},
		totalAuthors:      {$totalAuthors},
		totalCountries:    {$totalCountries},
		countryDisplay:    '{$settings.countryDisplay|escape}',
		sortOrder:         '{$settings.sortOrder|escape}',
		isAdmin:           false,
		fetchUrl:          null
	{rdelim};
</script>
<script src="{$pluginPath}/js/countryStats.js"></script>

{include file="frontend/components/footer.tpl"}
