{**
 * plugins/generic/journalHero/templates/settingsForm.tpl
 *
 * Journal Hero — settings form.
 *}
<script>
	$(function() {ldelim}
		$('#journalHeroSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="journalHeroSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="journalHeroSettingsFormNotification"}

	{fbvFormArea id="journalHeroDisplay" title="plugins.generic.journalHero.section.display"}
		{fbvFormSection title="plugins.generic.journalHero.style" description="plugins.generic.journalHero.style.description"}
			{fbvElement type="select" id="style" from=$styleOptions selected=$style translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.pages"}
			{fbvElement type="select" id="pages" from=$pagesOptions selected=$pages translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.position" description="plugins.generic.journalHero.position.description"}
			{fbvElement type="select" id="position" from=$positionOptions selected=$position translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="hideSiteName" checked=$hideSiteName label="plugins.generic.journalHero.hideSiteName"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.hideSelectors" description="plugins.generic.journalHero.hideSelectors.description"}
			{fbvElement type="text" id="hideSelectors" value=$hideSelectors}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.layout" description="plugins.generic.journalHero.layout.description"}
			{fbvElement type="select" id="layout" from=$layoutOptions selected=$layout translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.boxWidth" description="plugins.generic.journalHero.boxWidth.description"}
			{fbvElement type="text" id="boxWidth" value=$boxWidth size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.size"}
			{fbvElement type="select" id="size" from=$sizeOptions selected=$size translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="showFeatures" checked=$showFeatures label="plugins.generic.journalHero.showFeatures"}
			{fbvElement type="checkbox" id="showIssn" checked=$showIssn label="plugins.generic.journalHero.showIssn"}
			{fbvElement type="checkbox" id="showCovers" checked=$showCovers label="plugins.generic.journalHero.showCovers"}
			{fbvElement type="checkbox" id="showStrip" checked=$showStrip label="plugins.generic.journalHero.showStrip"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.coverCount"}
			{fbvElement type="text" id="coverCount" value=$coverCount size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="journalHeroContent" title="plugins.generic.journalHero.section.content"}
		{fbvFormSection title="plugins.generic.journalHero.heroTitle" description="plugins.generic.journalHero.heroTitle.description"}
			{fbvElement type="text" id="heroTitle" value=$heroTitle}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.eyebrow" description="plugins.generic.journalHero.eyebrow.description"}
			{fbvElement type="text" id="eyebrow" value=$eyebrow}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.heroDescription" description="plugins.generic.journalHero.heroDescription.description"}
			{fbvElement type="textarea" id="heroDescription" value=$heroDescription height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.descriptionLength"}
			{fbvElement type="text" id="descriptionLength" value=$descriptionLength size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.rank" description="plugins.generic.journalHero.rank.description"}
			{fbvElement type="text" id="rankLabel" value=$rankLabel label="plugins.generic.journalHero.rankLabel" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="rankValue" value=$rankValue label="plugins.generic.journalHero.rankValue" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.rankUrl" description="plugins.generic.journalHero.rankUrl.description"}
			{fbvElement type="text" id="rankUrl" value=$rankUrl}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.issnLinks" description="plugins.generic.journalHero.issnLinks.description"}
			{fbvElement type="text" id="printIssnUrl" value=$printIssnUrl label="plugins.generic.journalHero.printIssnUrl"}
			{fbvElement type="text" id="onlineIssnUrl" value=$onlineIssnUrl label="plugins.generic.journalHero.onlineIssnUrl"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="journalHeroProfile" title="plugins.generic.journalHero.section.profile"}
		{fbvFormSection title="plugins.generic.journalHero.subtitle" description="plugins.generic.journalHero.subtitle.description"}
			{fbvElement type="text" id="subtitle" value=$subtitle}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.stats" description="plugins.generic.journalHero.stats.description"}
			{fbvElement type="textarea" id="stats" value=$stats height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.doiPrefix" description="plugins.generic.journalHero.doiPrefix.description"}
			{fbvElement type="text" id="doiPrefix" value=$doiPrefix size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.license" description="plugins.generic.journalHero.license.description"}
			{fbvElement type="text" id="license" value=$license size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.frequency" description="plugins.generic.journalHero.frequency.description"}
			{fbvElement type="text" id="frequency" value=$frequency size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="showSubmit" checked=$showSubmit label="plugins.generic.journalHero.showSubmit"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="journalHeroLook" title="plugins.generic.journalHero.section.look"}
		{fbvFormSection title="plugins.generic.journalHero.backgroundUrl" description="plugins.generic.journalHero.backgroundUrl.description"}
			{fbvElement type="text" id="backgroundUrl" value=$backgroundUrl}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="useHomepageImage" checked=$useHomepageImage label="plugins.generic.journalHero.useHomepageImage"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.font"}
			{fbvElement type="select" id="font" from=$fontOptions selected=$font translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.journalHero.colors" description="plugins.generic.journalHero.colors.description"}
			{fbvElement type="text" id="colorPrimary" value=$colorPrimary label="plugins.generic.journalHero.colorPrimary" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="colorAccent" value=$colorAccent label="plugins.generic.journalHero.colorAccent" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="colorRank" value=$colorRank label="plugins.generic.journalHero.colorRank" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
