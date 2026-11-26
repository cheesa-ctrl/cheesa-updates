{**
 * templates/importPage.tpl
 * Halaman penuh untuk alat Import XML Arsip Review (dibuka dari menu sidebar).
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">{translate key="plugins.generic.reviewArchive.import.tabTitle"}</h1>
	{$raTabContent nofilter}
{/block}
