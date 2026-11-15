{**
 * plugins/generic/apcLoa/templates/verify.tpl
 *
 * Public verification page reached by scanning a document's QR code.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.apcLoa.verify.title"}

<div class="page apcloa apcloa-verify">
	<h1>{translate key="plugins.generic.apcLoa.verify.title"}</h1>

	{if !$apcVerify}
		<div class="apcloa-verdict apcloa-verdict--bad">
			<b>✖ {translate key="plugins.generic.apcLoa.verify.notFound"}</b>
			<p>{translate key="plugins.generic.apcLoa.verify.notFound.desc"}</p>
		</div>
	{else}
		{assign var="r" value=$apcVerify.record}
		{if $r.status == 'revoked'}
			<div class="apcloa-verdict apcloa-verdict--bad">
				<b>⚠ {translate key="plugins.generic.apcLoa.verify.revoked"}</b>
				<p>{translate key="plugins.generic.apcLoa.verify.revoked.desc"}</p>
			</div>
		{else}
			<div class="apcloa-verdict apcloa-verdict--ok">
				<b>✔ {translate key="plugins.generic.apcLoa.verify.valid"}</b>
				<p>{translate key="plugins.generic.apcLoa.verify.valid.desc"}</p>
			</div>
		{/if}

		<table class="apcloa-table apcloa-table--kv">
			<tr><th>{translate key="plugins.generic.apcLoa.verify.manuscript"}</th><td><b>{$apcVerify.title|escape}</b></td></tr>
			<tr><th>{translate key="plugins.generic.apcLoa.verify.authors"}</th><td>{$apcVerify.authorsText|escape}</td></tr>
			<tr><th>{translate key="plugins.generic.apcLoa.doc.loa"}</th><td>{$r.loa_number|escape} — {$apcVerify.acceptedId|escape}</td></tr>
			{if $r.invoice_number}
				<tr><th>{translate key="plugins.generic.apcLoa.doc.invoice"}</th><td>{$r.invoice_number|escape} — {$apcVerify.amountText|escape}</td></tr>
			{/if}
			{if $r.receipt_number && $r.status == 'paid'}
				<tr><th>{translate key="plugins.generic.apcLoa.doc.receipt"}</th><td>{$r.receipt_number|escape} — {$apcVerify.paidId|escape}</td></tr>
			{/if}
			<tr><th>{translate key="plugins.generic.apcLoa.verify.paymentStatus"}</th><td><span class="apcloa-badge apcloa-badge--{$r.status|escape}">{$apcVerify.statusText|escape}</span></td></tr>
		</table>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
