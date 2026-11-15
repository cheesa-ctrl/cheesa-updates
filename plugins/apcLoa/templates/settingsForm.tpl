{**
 * plugins/generic/apcLoa/templates/settingsForm.tpl
 *
 * LoA & APC — journal settings.
 *}
<script>
	$(function() {ldelim}
		$('#apcLoaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="apcLoaSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="apcLoaSettingsFormNotification"}

	<p>
		<a href="{$dashboardUrl|escape}" target="_blank" class="pkp_button">{translate key="plugins.generic.apcLoa.dashboard"}</a>
		<a href="{$previewUrl|escape}" target="_blank" class="pkp_button">{translate key="plugins.generic.apcLoa.settings.preview"}</a>
	</p>
	<p class="description">{translate key="plugins.generic.apcLoa.settings.authorPage"} <a href="{$authorPageUrl|escape}" target="_blank">{$authorPageUrl|escape}</a></p>

	{* ---------------- Fee ---------------- *}
	{fbvFormArea id="apcLoaFee" title="plugins.generic.apcLoa.settings.section.fee"}
		{fbvFormSection title="plugins.generic.apcLoa.settings.apcFee" description="plugins.generic.apcLoa.settings.apcFee.description"}
			{fbvElement type="text" id="apcFee" value=$apcFee label="plugins.generic.apcLoa.settings.apcFee.amount" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="currency" value=$currency label="plugins.generic.apcLoa.settings.currency" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="dueDays" value=$dueDays label="plugins.generic.apcLoa.settings.dueDays" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.sendTo"}
			{fbvElement type="select" id="sendTo" from=$sendToOptions selected=$sendTo translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="triggerProduction" checked=$triggerProduction label="plugins.generic.apcLoa.settings.triggerProduction"}
		{/fbvFormSection}
	{/fbvFormArea}

	{* ---------------- Payment ---------------- *}
	{fbvFormArea id="apcLoaPayment" title="plugins.generic.apcLoa.settings.section.payment"}
		{fbvFormSection list=true title="plugins.generic.apcLoa.settings.methods"}
			{fbvElement type="checkbox" id="methodBank" checked=$methodBank label="plugins.generic.apcLoa.settings.methodBank"}
			{fbvElement type="checkbox" id="methodMidtrans" checked=$methodMidtrans label="plugins.generic.apcLoa.settings.methodMidtrans"}
			{fbvElement type="checkbox" id="methodXendit" checked=$methodXendit label="plugins.generic.apcLoa.settings.methodXendit"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.bankInstructions" description="plugins.generic.apcLoa.settings.bankInstructions.description"}
			{fbvElement type="textarea" id="bankInstructions" value=$bankInstructions height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.qrisUrl" description="plugins.generic.apcLoa.settings.qrisUrl.description"}
			{fbvElement type="text" id="qrisUrl" value=$qrisUrl}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.apcLoa.settings.midtrans" description="plugins.generic.apcLoa.settings.midtrans.description"}
			{fbvElement type="text" id="midtransServerKey" value=$midtransServerKey label="plugins.generic.apcLoa.settings.midtransServerKey"}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="midtransProduction" checked=$midtransProduction label="plugins.generic.apcLoa.settings.midtransProduction"}
		{/fbvFormSection}
		<p class="description">{translate key="plugins.generic.apcLoa.settings.notifyUrl"} <code>{$midtransNotifyUrl|escape}</code></p>

		{fbvFormSection title="plugins.generic.apcLoa.settings.xendit" description="plugins.generic.apcLoa.settings.xendit.description"}
			{fbvElement type="text" id="xenditSecretKey" value=$xenditSecretKey label="plugins.generic.apcLoa.settings.xenditSecretKey"}
			{fbvElement type="text" id="xenditCallbackToken" value=$xenditCallbackToken label="plugins.generic.apcLoa.settings.xenditCallbackToken"}
		{/fbvFormSection}
		<p class="description">{translate key="plugins.generic.apcLoa.settings.notifyUrl"} <code>{$xenditNotifyUrl|escape}</code></p>
	{/fbvFormArea}

	{* ---------------- Letterhead ---------------- *}
	{fbvFormArea id="apcLoaLetterhead" title="plugins.generic.apcLoa.settings.section.letterhead"}
		{fbvFormSection title="plugins.generic.apcLoa.settings.logos" description="plugins.generic.apcLoa.settings.logos.description"}
			{fbvElement type="text" id="logoLeftUrl" value=$logoLeftUrl label="plugins.generic.apcLoa.settings.logoLeftUrl"}
			{fbvElement type="text" id="logoRightUrl" value=$logoRightUrl label="plugins.generic.apcLoa.settings.logoRightUrl"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.headerLines" description="plugins.generic.apcLoa.settings.headerLines.description"}
			{fbvElement type="textarea" id="headerLines" value=$headerLines height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.layout"}
			{fbvElement type="select" id="headerLineStyle" from=$lineOptions selected=$headerLineStyle translate=false label="plugins.generic.apcLoa.settings.headerLineStyle" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="select" id="paperSize" from=$paperOptions selected=$paperSize translate=false label="plugins.generic.apcLoa.settings.paperSize" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="accentColor" value=$accentColor label="plugins.generic.apcLoa.settings.accentColor" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.city" description="plugins.generic.apcLoa.settings.city.description"}
			{fbvElement type="text" id="city" value=$city size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{* ---------------- Numbering ---------------- *}
	{fbvFormArea id="apcLoaNumbering" title="plugins.generic.apcLoa.settings.section.numbering"}
		{fbvFormSection title="plugins.generic.apcLoa.settings.numberPattern" description="plugins.generic.apcLoa.settings.numberPattern.description"}
			{fbvElement type="text" id="numberPattern" value=$numberPattern}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.prefixes"}
			{fbvElement type="text" id="prefixLoa" value=$prefixLoa label="plugins.generic.apcLoa.settings.prefixLoa" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="prefixInvoice" value=$prefixInvoice label="plugins.generic.apcLoa.settings.prefixInvoice" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="prefixReceipt" value=$prefixReceipt label="plugins.generic.apcLoa.settings.prefixReceipt" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{* ---------------- Signatures ---------------- *}
	{fbvFormArea id="apcLoaSigners" title="plugins.generic.apcLoa.settings.section.signers"}
		{fbvFormSection title="plugins.generic.apcLoa.settings.signer1"}
			{fbvElement type="text" id="signer1Name" value=$signer1Name label="plugins.generic.apcLoa.settings.signerName" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="signer1Title" value=$signer1Title label="plugins.generic.apcLoa.settings.signerTitle" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="signer1Sign" value=$signer1Sign label="plugins.generic.apcLoa.settings.signerSign"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.signer2" description="plugins.generic.apcLoa.settings.signer2.description"}
			{fbvElement type="text" id="signer2Name" value=$signer2Name label="plugins.generic.apcLoa.settings.signerName" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="signer2Title" value=$signer2Title label="plugins.generic.apcLoa.settings.signerTitle" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="signer2Sign" value=$signer2Sign label="plugins.generic.apcLoa.settings.signerSign"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.stampUrl" description="plugins.generic.apcLoa.settings.stampUrl.description"}
			{fbvElement type="text" id="stampUrl" value=$stampUrl}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="showQr" checked=$showQr label="plugins.generic.apcLoa.settings.showQr"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.footerText"}
			{fbvElement type="textarea" id="footerText" value=$footerText height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
	{/fbvFormArea}

	{* ---------------- Wording & email ---------------- *}
	{fbvFormArea id="apcLoaWording" title="plugins.generic.apcLoa.settings.section.wording"}
		{fbvFormSection title="plugins.generic.apcLoa.settings.loaBodyId" description="plugins.generic.apcLoa.settings.loaBody.description"}
			{fbvElement type="textarea" id="loaBodyId" value=$loaBodyId height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.loaBodyEn"}
			{fbvElement type="textarea" id="loaBodyEn" value=$loaBodyEn height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.emailSubject"}
			{fbvElement type="text" id="emailSubject" value=$emailSubject}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.apcLoa.settings.emailBody" description="plugins.generic.apcLoa.settings.emailBody.description"}
			{fbvElement type="textarea" id="emailBody" value=$emailBody height=$fbvStyles.height.TALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
