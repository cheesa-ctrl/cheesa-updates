{**
 * plugins/generic/apcLoa/templates/author.tpl
 *
 * Author page "APC & LoA": documents, online payment, transfer proof upload.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.apcLoa.author.title"}

<div class="page apcloa">
	<h1>{translate key="plugins.generic.apcLoa.author.title"}</h1>
	<p class="apcloa-lead">{translate key="plugins.generic.apcLoa.author.lead"}</p>

	{if $apc.msgText}<div class="apcloa-flash">{$apc.msgText|escape}</div>{/if}
	{if $apc.isEditor}<p><a class="apcloa-btn apcloa-btn--ghost" href="{$apc.dashboardUrl|escape}">{translate key="plugins.generic.apcLoa.dashboard"} →</a></p>{/if}

	{if !$apc.rows}
		<div class="apcloa-empty">{translate key="plugins.generic.apcLoa.author.empty"}</div>
	{/if}

	{foreach from=$apc.rows item=row}
		{assign var="r" value=$row.record}
		<article class="apcloa-card">
			<header class="apcloa-card__head">
				<div>
					<h2 class="apcloa-card__title">{$row.title|escape}</h2>
					<div class="apcloa-card__meta">LoA {$r.loa_number|escape}{if $r.invoice_number} · Invoice {$r.invoice_number|escape}{/if}</div>
				</div>
				<span class="apcloa-badge apcloa-badge--{$r.status|escape}">{$row.statusText|escape}</span>
			</header>

			{if $row.isFee}
				<dl class="apcloa-facts">
					<div><dt>{translate key="plugins.generic.apcLoa.amount"}</dt><dd><b>{$row.amountText|escape}</b></dd></div>
					<div><dt>{translate key="plugins.generic.apcLoa.dueDate"}</dt><dd>{$row.dueId|escape}</dd></div>
					{if $r.status == 'paid'}<div><dt>{translate key="plugins.generic.apcLoa.paidDate"}</dt><dd>{$row.paidId|escape}</dd></div>{/if}
				</dl>
			{/if}

			<div class="apcloa-docs">
				<a class="apcloa-btn" href="{$row.loaUrl|escape}" target="_blank">📄 {translate key="plugins.generic.apcLoa.doc.loa"}</a>
				{if $r.invoice_number}<a class="apcloa-btn" href="{$row.invoiceUrl|escape}" target="_blank">🧾 {translate key="plugins.generic.apcLoa.doc.invoice"}</a>{/if}
				{if $r.status == 'paid' && $r.receipt_number}<a class="apcloa-btn" href="{$row.receiptUrl|escape}" target="_blank">✅ {translate key="plugins.generic.apcLoa.doc.receipt"}</a>{/if}
			</div>

			{if $r.status == 'pending'}
				<p class="apcloa-note">{translate key="plugins.generic.apcLoa.author.pending"}{if $row.proofUrl} — <a href="{$row.proofUrl|escape}" target="_blank">{translate key="plugins.generic.apcLoa.author.viewProof"}</a>{/if}</p>
			{/if}
			{if $r.status == 'unpaid' && $r.editor_note}
				<p class="apcloa-note apcloa-note--warn"><b>{translate key="plugins.generic.apcLoa.author.editorNote"}:</b> {$r.editor_note|escape}</p>
			{/if}

			{if $row.canPay}
				<div class="apcloa-pay">
					{if $row.methodMidtrans || $row.methodXendit}
						<div class="apcloa-pay__online">
							<h3>{translate key="plugins.generic.apcLoa.author.payOnline"}</h3>
							<p class="apcloa-small">{translate key="plugins.generic.apcLoa.author.payOnline.desc"}</p>
							{if $row.methodMidtrans}
								<form method="post" action="{$apc.payUrl|escape}">
									{csrf}
									<input type="hidden" name="recordId" value="{$r.record_id|escape}">
									<input type="hidden" name="gateway" value="midtrans">
									<button type="submit" class="apcloa-btn apcloa-btn--primary">{translate key="plugins.generic.apcLoa.author.payMidtrans"}</button>
								</form>
							{/if}
							{if $row.methodXendit}
								<form method="post" action="{$apc.payUrl|escape}">
									{csrf}
									<input type="hidden" name="recordId" value="{$r.record_id|escape}">
									<input type="hidden" name="gateway" value="xendit">
									<button type="submit" class="apcloa-btn apcloa-btn--primary">{translate key="plugins.generic.apcLoa.author.payXendit"}</button>
								</form>
							{/if}
						</div>
					{/if}

					{if $row.methodBank}
						<div class="apcloa-pay__bank">
							<h3>{translate key="plugins.generic.apcLoa.author.payBank"}</h3>
							{if $apc.bankHtml}<div class="apcloa-bankbox">{$apc.bankHtml}</div>{/if}
							{if $apc.settings.qrisUrl}<img class="apcloa-qris" src="{$apc.settings.qrisUrl|escape}" alt="QRIS">{/if}
							<form method="post" action="{$apc.uploadUrl|escape}" enctype="multipart/form-data" class="apcloa-upload">
								{csrf}
								<input type="hidden" name="recordId" value="{$r.record_id|escape}">
								<label>{translate key="plugins.generic.apcLoa.author.proofFile"}
									<input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required>
								</label>
								<label>{translate key="plugins.generic.apcLoa.author.proofNote"}
									<input type="text" name="note" maxlength="500" placeholder="{translate|escape key="plugins.generic.apcLoa.author.proofNote.placeholder"}">
								</label>
								<button type="submit" class="apcloa-btn apcloa-btn--primary">{if $r.status == 'pending'}{translate key="plugins.generic.apcLoa.author.reupload"}{else}{translate key="plugins.generic.apcLoa.author.upload"}{/if}</button>
								<small>{translate key="plugins.generic.apcLoa.author.proofRules"}</small>
							</form>
						</div>
					{/if}
				</div>
			{/if}
		</article>
	{/foreach}
</div>

{include file="frontend/components/footer.tpl"}
