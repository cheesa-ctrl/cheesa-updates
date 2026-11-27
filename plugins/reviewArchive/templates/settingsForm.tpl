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
		{fbvFormSection title="Google Drive API Key" for="gdriveApiKey"}
			<p>Kunci API Google (Drive API) untuk membaca folder Drive publik. Dapatkan di Google Cloud Console &rarr; Credentials &rarr; API key, lalu batasi ke Google Drive API. Folder harus di-share "Anyone with the link".</p>
			{fbvElement type="text" id="gdriveApiKey" value=$gdriveApiKey size=$fbvStyles.size.LARGE maxlength="120"}
		{/fbvFormSection}
		{fbvFormSection title="Folder kumpulan Google Drive" for="gdriveCollectionFolder"}
			<p>Tempel <strong>satu</strong> tautan folder kumpulan yang berisi subfolder tiap artikel (mis. <code>Art-1-24394-Mara</code>, <code>Art-2-23348-Setiorini</code>, &hellip;). Tiap artikel otomatis dicocokkan ke subfolder-nya berdasarkan nomor ID/DOI pada nama folder, lalu nama belakang penulis. Berkasnya muncul di tab <em>Arsip Review</em> tiap artikel. Kosongkan untuk mematikan fitur ini.</p>
			{fbvElement type="text" id="gdriveCollectionFolder" value=$gdriveCollectionFolder size=$fbvStyles.size.LARGE maxlength="300"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
