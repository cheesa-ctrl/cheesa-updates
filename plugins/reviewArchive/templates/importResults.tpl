{**
 * plugins/generic/reviewArchive/templates/importResults.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Result of the import tool: summary + the core Native XML results.
 *}
<div class="ra_plan">
	{if $raImported}
		<div style="padding: 1rem; border-left: 4px solid #00b28d; background: #effaf7; margin-bottom: 1.5rem;">
			<strong>{translate key="plugins.generic.reviewArchive.results.success"}</strong><br>
			{translate key="plugins.generic.reviewArchive.results.successHelp"}
		</div>
	{else}
		<div style="padding: 1rem; border-left: 4px solid #d00a6c; background: #fdeef5; margin-bottom: 1.5rem;">
			<strong>{translate key="plugins.generic.reviewArchive.results.failed"}</strong>
		</div>
	{/if}
	{$raNativeResults}
</div>
