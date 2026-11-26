{**
 * plugins/generic/reviewArchive/templates/settingsForm.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Settings: journal-wide editor statement and reviewer certificate list.
 *}
<script>
	$(function() {ldelim}
		$('#reviewArchiveSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="reviewArchiveSettings" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewArchiveSettingsNotification"}

	{fbvFormArea id="reviewArchiveStatementArea"}
		{fbvFormSection title="plugins.generic.reviewArchive.settings.statement"}
			<p>{translate key="plugins.generic.reviewArchive.settings.statement.description"}</p>
			{fbvElement type="textarea" id="defaultStatement" value=$defaultStatement rich=false height=$fbvStyles.height.TALL}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.reviewArchive.settings.certificates"}
			<p>{translate key="plugins.generic.reviewArchive.settings.certificates.description" count=$certificateRows}</p>
			{fbvElement type="textarea" id="certificateCsv" value=$certificateCsv rich=false height=$fbvStyles.height.TALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
