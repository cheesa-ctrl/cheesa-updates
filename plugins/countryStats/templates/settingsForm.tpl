<script>
	$(function() {ldelim}
		$('#countryStatsSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="countryStatsSettings" method="post"
      action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}

	{fbvFormArea id="csScope" title="plugins.generic.countryStats.settings.scope"}
		{fbvFormSection title="plugins.generic.countryStats.settings.uniqueStrategy" description="plugins.generic.countryStats.settings.uniqueStrategy.desc"}
			{fbvElement type="select" id="uniqueStrategy" from=$uniqueStrategyOptions selected=$uniqueStrategy translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.countryStats.settings.orcidFallback" description="plugins.generic.countryStats.settings.orcidFallback.desc"}
			{fbvElement type="select" id="orcidFallback" from=$orcidFallbackOptions selected=$orcidFallback translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="csAppearance" title="plugins.generic.countryStats.settings.appearance"}
		{fbvFormSection title="plugins.generic.countryStats.settings.countryDisplay"}
			{fbvElement type="select" id="countryDisplay" from=$countryDisplayOptions selected=$countryDisplay translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.countryStats.settings.sortOrder"}
			{fbvElement type="select" id="sortOrder" from=$sortOrderOptions selected=$sortOrder translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.countryStats.settings.minThreshold" description="plugins.generic.countryStats.settings.minThreshold.desc"}
			{fbvElement type="text" id="minThreshold" value=$minThreshold size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="includeUnknown" checked=$includeUnknown label="plugins.generic.countryStats.settings.includeUnknown"}
			{fbvElement type="checkbox" id="showAuthorsTab" checked=$showAuthorsTab label="plugins.generic.countryStats.settings.showAuthorsTab"}
			{fbvElement type="checkbox" id="showArticlesTab" checked=$showArticlesTab label="plugins.generic.countryStats.settings.showArticlesTab"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="csPublic" title="plugins.generic.countryStats.settings.publicPage"}
		{fbvFormSection list=true description="plugins.generic.countryStats.settings.enablePublicPage.desc"}
			{fbvElement type="checkbox" id="enablePublicPage" checked=$enablePublicPage label="plugins.generic.countryStats.settings.enablePublicPage"}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.countryStats.settings.publicPageTitle"}
			{fbvElement type="text" id="publicPageTitle" value=$publicPageTitle size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.countryStats.settings.publicPageUrl" description="plugins.generic.countryStats.settings.publicPageUrl.desc"}
			<div style="padding:8px 14px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:8px;font-size:13px;word-break:break-all;display:flex;align-items:center;gap:8px;">
				<span style="font-size:16px;">&#x1F517;</span>
				<a href="{$publicPageUrl|escape}" target="_blank" style="color:#0f766e;text-decoration:none;font-weight:600;">
					{$publicPageUrl|escape}
				</a>
			</div>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
