{**
 * plugins/generic/cheesaFullText/templates/galley.tpl
 * Halaman baca teks lengkap (galley JATS XML).
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$cftPublication->getLocalizedFullTitle()|escape}

<div class="cft-page">
	<nav class="cft-crumbs" aria-label="Breadcrumb">
		<a href="{$cftBackUrl|escape}">&larr; Back to article page</a>
	</nav>

	<header class="cft-page-head">
		{if $cftIssue}<p class="cft-page-kicker">{$cftIssue->getIssueIdentification()|escape}</p>{/if}
		<h1>{$cftPublication->getLocalizedFullTitle()|escape}</h1>
		<p class="cft-page-authors">{$cftAuthors|escape}</p>
		<div class="cft-page-actions">
			{if $cftPdfUrl}<a class="cft-btn" href="{$cftPdfUrl|escape}">PDF</a>{/if}
			<a class="cft-btn cft-btn-o" href="{$cftDownloadUrl|escape}">Download XML</a>
		</div>
	</header>

	{$cftHtml}
</div>

{include file="frontend/components/footer.tpl"}
