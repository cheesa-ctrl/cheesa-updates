{**
 * plugins/generic/apcLoa/templates/document.tpl
 *
 * Printable LoA / invoice / receipt (stand-alone page, print to PDF from the browser).
 *}
{assign var="r" value=$doc.record}
{if $docSettings.paperSize == 'F4'}{assign var="pageSize" value="215mm 330mm"}{elseif $docSettings.paperSize == 'Letter'}{assign var="pageSize" value="8.5in 11in"}{else}{assign var="pageSize" value="210mm 297mm"}{/if}
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title>{if $doc.type == 'loa'}{$r.loa_number|escape}{elseif $doc.type == 'invoice'}{$r.invoice_number|escape}{else}{$r.receipt_number|escape}{/if} — {$doc.journal|escape}</title>
	<link rel="stylesheet" href="{$doc.cssUrl|escape}">
	<style>
		@page { size: {$pageSize}; margin: 0; }
		:root { --accent: {$doc.accent|escape}; --page-w: {if $docSettings.paperSize == 'Letter'}8.5in{elseif $docSettings.paperSize == 'F4'}215mm{else}210mm{/if}; --page-h: {if $docSettings.paperSize == 'Letter'}11in{elseif $docSettings.paperSize == 'F4'}330mm{else}297mm{/if}; }
	</style>
</head>
<body>

<div class="toolbar">
	<button type="button" onclick="window.print()">🖨 {translate key="plugins.generic.apcLoa.doc.print"}</button>
	<span>{translate key="plugins.generic.apcLoa.doc.printHint"}</span>
</div>

<main class="sheet doc-{$doc.type|escape} status-{$r.status|escape}">
	{if $r.status == 'revoked'}<div class="watermark revoked">{translate key="plugins.generic.apcLoa.status.revoked"}</div>{/if}
	{if $doc.isPreview}<div class="watermark preview">{translate key="plugins.generic.apcLoa.doc.preview"}</div>{/if}
	{if $doc.type == 'invoice' && $r.status == 'paid'}<div class="paidstamp">{translate key="plugins.generic.apcLoa.doc.paidStamp"}</div>{/if}

	{* ---------- Letterhead ---------- *}
	<header class="letterhead line-{$docSettings.headerLineStyle|escape}">
		<div class="lh-logo">{if $docSettings.logoLeftUrl}<img src="{$docSettings.logoLeftUrl|escape}" alt="">{/if}</div>
		<div class="lh-text">
			{foreach from=$doc.headerLines item=line name=lines}
				<div class="lh-line lh-line-{$smarty.foreach.lines.iteration}">{$line|escape}</div>
			{/foreach}
		</div>
		<div class="lh-logo">{if $docSettings.logoRightUrl}<img src="{$docSettings.logoRightUrl|escape}" alt="">{/if}</div>
	</header>

	{* ---------- Title ---------- *}
	<section class="doc-title">
		{if $doc.type == 'loa'}
			<h1>SURAT KETERANGAN PENERIMAAN NASKAH</h1>
			<h2>LETTER OF ACCEPTANCE</h2>
			<div class="doc-number">Nomor / <i>Number</i>: <b>{$r.loa_number|escape}</b></div>
		{elseif $doc.type == 'invoice'}
			<h1>TAGIHAN</h1>
			<h2>INVOICE</h2>
			<div class="doc-number">Nomor / <i>Number</i>: <b>{$r.invoice_number|escape}</b></div>
		{else}
			<h1>KUITANSI</h1>
			<h2>RECEIPT</h2>
			<div class="doc-number">Nomor / <i>Number</i>: <b>{$r.receipt_number|escape}</b></div>
		{/if}
	</section>

	{* ---------- LoA ---------- *}
	{if $doc.type == 'loa'}
		<p class="dateline">{if $docSettings.city}{$docSettings.city|escape}, {/if}{$doc.acceptedId|escape} / <i>{$doc.acceptedEn|escape}</i></p>

		<table class="meta">
			<tr><th>Judul Naskah<br><i>Manuscript Title</i></th><td><b>{$doc.title|escape}</b></td></tr>
			<tr><th>Penulis<br><i>Author(s)</i></th><td>{$doc.authorsText|escape}</td></tr>
			{if $doc.affiliations}<tr><th>Afiliasi<br><i>Affiliation(s)</i></th><td>{$doc.affiliationsText|escape}</td></tr>{/if}
			<tr><th>Tanggal Diterima<br><i>Date of Acceptance</i></th><td>{$doc.acceptedId|escape} / <i>{$doc.acceptedEn|escape}</i></td></tr>
		</table>

		<div class="bilingual">
			<div class="lang-id">{$doc.loaBodyId|escape|nl2br}</div>
			<div class="lang-en"><i>{$doc.loaBodyEn|escape|nl2br}</i></div>
		</div>

		{if $doc.isFee && $r.invoice_number}
			<p class="apc-note">
				Naskah ini dikenai biaya publikasi (APC) sebesar <b>{$doc.amountText|escape}</b> sesuai tagihan <b>{$r.invoice_number|escape}</b>, jatuh tempo {$doc.dueId|escape}.<br>
				<i>This manuscript is subject to an Article Processing Charge (APC) of <b>{$doc.amountText|escape}</b> as per invoice <b>{$r.invoice_number|escape}</b>, due {$doc.dueEn|escape}.</i>
			</p>
		{/if}
	{/if}

	{* ---------- Invoice ---------- *}
	{if $doc.type == 'invoice'}
		<div class="inv-grid">
			<div>
				<div class="label">Ditagihkan kepada / <i>Bill to</i></div>
				<div class="value"><b>{$doc.authorsText|escape}</b>{if $doc.affiliations}<br>{$doc.affiliationsText|escape}{/if}</div>
			</div>
			<div class="inv-dates">
				<div><span class="label">Tanggal / <i>Date</i></span> {$doc.acceptedId|escape}</div>
				<div><span class="label">Jatuh tempo / <i>Due date</i></span> <b>{$doc.dueId|escape}</b></div>
				<div><span class="label">Status</span> <b class="st st-{$r.status|escape}">{$doc.statusText|escape}</b></div>
			</div>
		</div>

		<table class="items">
			<thead><tr><th>Uraian / <i>Description</i></th><th class="num">Jumlah / <i>Amount</i></th></tr></thead>
			<tbody>
				<tr>
					<td>
						Biaya Pemrosesan Artikel (APC) / <i>Article Processing Charge</i><br>
						<small>{$doc.title|escape}</small><br>
						<small>LoA: {$r.loa_number|escape}</small>
					</td>
					<td class="num">{$doc.amountText|escape}</td>
				</tr>
			</tbody>
			<tfoot><tr><th>Total</th><th class="num">{$doc.amountText|escape}</th></tr></tfoot>
		</table>

		{if $r.status != 'paid' && $r.status != 'waived' && $r.status != 'revoked'}
			<section class="howtopay">
				<h3>Cara Pembayaran / <i>How to Pay</i></h3>
				<div class="pay-grid">
					<div>
						{if $doc.methodBank && $doc.bankHtml}<div class="bank">{$doc.bankHtml}</div>{/if}
						{if $doc.methodMidtrans || $doc.methodXendit}
							<p class="small">Pembayaran online (QRIS, e-wallet, virtual account, kartu) tersedia di halaman APC & LoA jurnal.<br><i>Online payment (QRIS, e-wallets, virtual accounts, cards) is available on the journal's APC & LoA page.</i></p>
						{/if}
						<p class="small">Cantumkan nomor tagihan <b>{$r.invoice_number|escape}</b> pada berita transfer. / <i>Please quote invoice number <b>{$r.invoice_number|escape}</b> in the transfer reference.</i></p>
					</div>
					{if $docSettings.qrisUrl}<div class="qris"><img src="{$docSettings.qrisUrl|escape}" alt="QRIS"><div class="small">QRIS</div></div>{/if}
				</div>
			</section>
		{/if}
	{/if}

	{* ---------- Receipt ---------- *}
	{if $doc.type == 'receipt'}
		<table class="meta receipt">
			<tr><th>Telah diterima dari<br><i>Received from</i></th><td><b>{$doc.authorsText|escape}</b></td></tr>
			<tr><th>Sejumlah<br><i>Amount</i></th><td><span class="big">{$doc.amountText|escape}</span></td></tr>
			<tr><th>Untuk pembayaran<br><i>In payment of</i></th><td>Biaya Pemrosesan Artikel (APC) / <i>Article Processing Charge</i> — {$doc.title|escape}<br><small>Invoice {$r.invoice_number|escape} · LoA {$r.loa_number|escape}</small></td></tr>
			<tr><th>Metode<br><i>Method</i></th><td>{$doc.methodText|escape}</td></tr>
			<tr><th>Tanggal bayar<br><i>Payment date</i></th><td>{$doc.paidId|escape} / <i>{$doc.paidEn|escape}</i></td></tr>
		</table>
		<div class="paidstamp static">{translate key="plugins.generic.apcLoa.doc.paidStamp"}</div>
	{/if}

	{* ---------- Signatures + QR ---------- *}
	<section class="sign-row">
		{if $docSettings.showQr}
			<div class="qr-block">
				<div class="qr" data-apc-qr="{$doc.verifyUrl|escape}" data-size="104"></div>
				<div class="small">Pindai untuk verifikasi<br><i>Scan to verify</i></div>
			</div>
		{else}<div></div>{/if}

		<div class="signers{if $docSettings.signer2Name} two{/if}">
			{if $doc.type != 'loa'}<div class="sign-date">{if $docSettings.city}{$docSettings.city|escape}, {/if}{if $doc.type == 'receipt'}{$doc.paidId|escape}{else}{$doc.acceptedId|escape}{/if}</div>{/if}
			<div class="signer">
				<div class="sign-title">{$docSettings.signer1Title|escape}</div>
				<div class="sign-space">
					{if $docSettings.stampUrl}<img class="stamp" src="{$docSettings.stampUrl|escape}" alt="">{/if}
					{if $docSettings.signer1Sign}<img class="sig" src="{$docSettings.signer1Sign|escape}" alt="">{/if}
				</div>
				<div class="sign-name">{if $docSettings.signer1Name}{$docSettings.signer1Name|escape}{else}&nbsp;{/if}</div>
			</div>
			{if $docSettings.signer2Name}
				<div class="signer">
					<div class="sign-title">{$docSettings.signer2Title|escape}</div>
					<div class="sign-space">{if $docSettings.signer2Sign}<img class="sig" src="{$docSettings.signer2Sign|escape}" alt="">{/if}</div>
					<div class="sign-name">{$docSettings.signer2Name|escape}</div>
				</div>
			{/if}
		</div>
	</section>

	{if $docSettings.footerText}<footer class="doc-footer">{$docSettings.footerText|escape|nl2br}</footer>{/if}
</main>

<script src="{$doc.qrJsUrl|escape}"></script>
</body>
</html>
