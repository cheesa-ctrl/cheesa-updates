{**
 * plugins/generic/apcLoa/templates/dashboard.tpl
 *
 * Editor dashboard: payment status of all accepted manuscripts.
 *}
{assign var="isFullWidth" value=true}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.apcLoa.dashboard"}

<div class="page apcloa apcloa-dash">
	<h1>{translate key="plugins.generic.apcLoa.dashboard"}</h1>
	{if $apcDash.msgText}<div class="apcloa-flash">{$apcDash.msgText|escape}</div>{/if}

	<div class="apcloa-stats">
		<a href="{$apcDash.filterUrls.unpaid|escape}" class="apcloa-stat apcloa-stat--unpaid{if $apcDash.filter == 'unpaid'} is-active{/if}"><b>{$apcDash.counts.unpaid}</b><span>{translate key="plugins.generic.apcLoa.status.unpaid"}</span></a>
		<a href="{$apcDash.filterUrls.pending|escape}" class="apcloa-stat apcloa-stat--pending{if $apcDash.filter == 'pending'} is-active{/if}"><b>{$apcDash.counts.pending}</b><span>{translate key="plugins.generic.apcLoa.status.pending"}</span></a>
		<a href="{$apcDash.filterUrls.paid|escape}" class="apcloa-stat apcloa-stat--paid{if $apcDash.filter == 'paid'} is-active{/if}"><b>{$apcDash.counts.paid}</b><span>{translate key="plugins.generic.apcLoa.status.paid"}</span></a>
		<a href="{$apcDash.filterUrls.waived|escape}" class="apcloa-stat apcloa-stat--waived{if $apcDash.filter == 'waived'} is-active{/if}"><b>{$apcDash.counts.waived}</b><span>{translate key="plugins.generic.apcLoa.status.waived"}</span></a>
		<a href="{$apcDash.baseUrl|escape}" class="apcloa-stat{if !$apcDash.filter} is-active{/if}"><b>{$apcDash.total}</b><span>{translate key="plugins.generic.apcLoa.dash.all"}</span></a>
		<div class="apcloa-stat apcloa-stat--money"><b>{$apcDash.received|escape}</b><span>{translate key="plugins.generic.apcLoa.dash.received"}</span></div>
		<div class="apcloa-stat apcloa-stat--money"><b>{$apcDash.outstanding|escape}</b><span>{translate key="plugins.generic.apcLoa.dash.outstanding"}</span></div>
	</div>

	<p class="apcloa-small">
		{translate key="plugins.generic.apcLoa.dash.preview"}:
		<a href="{$apcDash.previewUrls.loa|escape}" target="_blank">LoA</a> ·
		<a href="{$apcDash.previewUrls.invoice|escape}" target="_blank">Invoice</a> ·
		<a href="{$apcDash.previewUrls.receipt|escape}" target="_blank">{translate key="plugins.generic.apcLoa.doc.receipt"}</a>
	</p>

	{if !$apcDash.rows}
		<div class="apcloa-empty">{translate key="plugins.generic.apcLoa.dash.empty"}</div>
	{else}
		<div class="apcloa-tablewrap">
		<table class="apcloa-table">
			<thead>
				<tr>
					<th>#</th>
					<th>{translate key="plugins.generic.apcLoa.verify.manuscript"}</th>
					<th>{translate key="plugins.generic.apcLoa.dash.numbers"}</th>
					<th>{translate key="plugins.generic.apcLoa.amount"}</th>
					<th>Status</th>
					<th>{translate key="plugins.generic.apcLoa.dash.actions"}</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$apcDash.rows item=row}
				{assign var="r" value=$row.record}
				<tr class="row-{$r.status|escape}">
					<td><a href="{$row.workflowUrl|escape}" target="_blank">{$r.submission_id|escape}</a></td>
					<td class="apcloa-titlecell">
						<b>{$row.title|escape|truncate:120:"…"}</b><br>
						<small>{$row.authorsText|escape}</small><br>
						<small>{translate key="plugins.generic.apcLoa.dash.accepted"} {$row.acceptedId|escape}{if $r.loa_sent_at} · {translate key="plugins.generic.apcLoa.dash.loaSent"}{/if}</small>
						{if $r.editor_note}<br><small class="apcloa-editornote">📝 {$r.editor_note|escape}</small>{/if}
					</td>
					<td class="apcloa-nums">
						<a href="{$row.loaUrl|escape}" target="_blank">{$r.loa_number|escape}</a>
						{if $r.invoice_number}<br><a href="{$row.invoiceUrl|escape}" target="_blank">{$r.invoice_number|escape}</a>{/if}
						{if $r.receipt_number && $r.status == 'paid'}<br><a href="{$row.receiptUrl|escape}" target="_blank">{$r.receipt_number|escape}</a>{/if}
					</td>
					<td>
						{if $row.isFee}{$row.amountText|escape}{else}—{/if}
						{if in_array($r.status, ['unpaid','pending','nofee','waived'])}
							<form method="post" action="{$apcDash.actionUrl|escape}" class="apcloa-inline">
								{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="amount">
								<input type="text" name="amount" value="{$r.amount|escape}" size="10" aria-label="{translate|escape key="plugins.generic.apcLoa.amount"}">
								<button type="submit" class="apcloa-mini">{translate key="plugins.generic.apcLoa.dash.setAmount"}</button>
							</form>
						{/if}
					</td>
					<td>
						<span class="apcloa-badge apcloa-badge--{$r.status|escape}">{$row.statusText|escape}</span>
						{if $r.method}<br><small>{$r.method|escape}</small>{/if}
						{if $row.proofUrl}<br><a href="{$row.proofUrl|escape}" target="_blank">📎 {translate key="plugins.generic.apcLoa.dash.proof"}</a>{/if}
						{if $r.proof_note}<br><small>“{$r.proof_note|escape}”</small>{/if}
					</td>
					<td class="apcloa-actions">
						{if in_array($r.status, ['unpaid','pending']) && $row.isFee}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="confirm"><button type="submit" class="apcloa-mini apcloa-mini--ok" onclick="return confirm('{translate|escape:"javascript" key="plugins.generic.apcLoa.dash.confirmPaid.ask"}')">✔ {translate key="plugins.generic.apcLoa.dash.markPaid"}</button></form>
						{/if}
						{if $r.status == 'pending'}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="reject"><input type="text" name="note" placeholder="{translate|escape key="plugins.generic.apcLoa.dash.rejectReason"}" size="14"><button type="submit" class="apcloa-mini apcloa-mini--warn">✖ {translate key="plugins.generic.apcLoa.dash.rejectProof"}</button></form>
						{/if}
						{if in_array($r.status, ['unpaid','pending'])}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="waive"><button type="submit" class="apcloa-mini" onclick="return confirm('{translate|escape:"javascript" key="plugins.generic.apcLoa.dash.waive.ask"}')">{translate key="plugins.generic.apcLoa.dash.waive"}</button></form>
						{/if}
						{if $r.status == 'waived'}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="unwaive"><button type="submit" class="apcloa-mini">{translate key="plugins.generic.apcLoa.dash.unwaive"}</button></form>
						{/if}
						{if $r.status != 'revoked'}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="resend"><button type="submit" class="apcloa-mini">✉ {translate key="plugins.generic.apcLoa.dash.resend"}</button></form>
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="revoke"><button type="submit" class="apcloa-mini apcloa-mini--danger" onclick="return confirm('{translate|escape:"javascript" key="plugins.generic.apcLoa.dash.revoke.ask"}')">{translate key="plugins.generic.apcLoa.dash.revoke"}</button></form>
						{else}
							<form method="post" action="{$apcDash.actionUrl|escape}">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="restore"><button type="submit" class="apcloa-mini">{translate key="plugins.generic.apcLoa.dash.restore"}</button></form>
						{/if}
						<form method="post" action="{$apcDash.actionUrl|escape}" class="apcloa-inline">{csrf}<input type="hidden" name="recordId" value="{$r.record_id|escape}"><input type="hidden" name="do" value="note"><input type="text" name="note" value="{$r.editor_note|escape}" placeholder="{translate|escape key="plugins.generic.apcLoa.dash.note"}" size="14"><button type="submit" class="apcloa-mini">💾</button></form>
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		</div>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
