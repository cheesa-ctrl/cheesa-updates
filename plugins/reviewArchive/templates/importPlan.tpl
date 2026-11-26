{**
 * plugins/generic/reviewArchive/templates/importPlan.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Preview of what the import tool will change.
 *}
<style>
	.ra_plan {ldelim} font-size: .875rem; {rdelim}
	.ra_plan .ra_card {ldelim} background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.2); border-radius: 3px; margin-bottom: 1.5rem; {rdelim}
	.ra_plan .ra_card > header {ldelim} padding: .75rem 1rem; border-bottom: 1px solid #ddd; {rdelim}
	.ra_plan .ra_card > header h4 {ldelim} margin: 0; font-size: .93rem; {rdelim}
	.ra_plan .ra_card > header p {ldelim} margin: .25rem 0 0; color: #555; {rdelim}
	.ra_plan .ra_summary {ldelim} padding: 1rem; border-left: 4px solid #00b28d; background: #effaf7; margin-bottom: 1.5rem; {rdelim}
	.ra_plan table {ldelim} width: 100%; border-collapse: collapse; {rdelim}
	.ra_plan th {ldelim} text-align: left; font-weight: 400; color: #555; padding: .5rem 1rem; border-bottom: 1px solid #ddd; {rdelim}
	.ra_plan td {ldelim} padding: .5rem 1rem; border-bottom: 1px solid #eee; vertical-align: top; word-break: break-word; {rdelim}
	.ra_plan .ra_tag {ldelim} display: inline-block; padding: 0 .4rem; border-radius: 3px; font-size: .75rem; font-weight: 700; white-space: nowrap; {rdelim}
	.ra_plan .ra_tag_label {ldelim} background: #e3f1f8; color: #006798; {rdelim}
	.ra_plan .ra_tag_remove {ldelim} background: #fdeef5; color: #d00a6c; {rdelim}
	.ra_plan .ra_tag_keep {ldelim} background: #f0f0f0; color: #555; {rdelim}
	.ra_plan .ra_tag_galley {ldelim} background: #effaf7; color: #00826a; {rdelim}
	.ra_plan del {ldelim} color: #999; {rdelim}
	.ra_plan .ra_new {ldelim} color: #006798; {rdelim}
	.ra_plan .ra_muted {ldelim} color: #666; {rdelim}
</style>

<div class="ra_plan">
	<div class="ra_summary">
		<strong>{translate key="plugins.generic.reviewArchive.plan.clean" count=$raScanned}</strong><br>
		{translate key="plugins.generic.reviewArchive.plan.size" before=$raPlan.sizeBeforeMb after=$raPlan.sizeAfterMb}
		{if $raSkipped}
			<br><span class="ra_muted">{translate key="plugins.generic.reviewArchive.judol.skipped"} {foreach from=$raSkipped item=skipped name=sk}{$skipped|escape}{if !$smarty.foreach.sk.last}; {/if}{/foreach}</span>
		{/if}
	</div>

	{foreach from=$raPlan.articles item=article}
		<section class="ra_card">
			<header>
				<h4>{$article.title|escape}</h4>
				<p>
					{if $article.doi}DOI {$article.doi|escape} · {/if}
					{translate key="plugins.generic.reviewArchive.plan.sourceId" id=$article.sourceId|escape}
				</p>
			</header>
			<table>
				<thead>
					<tr>
						<th>{translate key="plugins.generic.reviewArchive.plan.column.action"}</th>
						<th>{translate key="plugins.generic.reviewArchive.column.file"}</th>
						<th>{translate key="plugins.generic.reviewArchive.plan.column.stage"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$article.files item=file}
						<tr>
							<td>
								{if $file.removed}
									<span class="ra_tag ra_tag_remove">{translate key="plugins.generic.reviewArchive.plan.action.remove"}</span><br>
									<small class="ra_muted">{translate key="plugins.generic.reviewArchive.plan.reason."|cat:$file.removeReason}</small>
								{elseif $file.isGalley}
									<span class="ra_tag ra_tag_galley">{translate key="plugins.generic.reviewArchive.plan.action.galley"}</span>
								{elseif $file.newName}
									<span class="ra_tag ra_tag_label">{translate key="plugins.generic.reviewArchive.plan.action.label"}</span>
								{else}
									<span class="ra_tag ra_tag_keep">{translate key="plugins.generic.reviewArchive.plan.action.keep"}</span>
								{/if}
								{if !$file.removed && $file.revisionsRemoved}
									<br><small class="ra_muted">{translate key="plugins.generic.reviewArchive.plan.revisionsRemoved" count=$file.revisionsRemoved total=$file.revisions}</small>
								{/if}
							</td>
							<td>
								{if $file.removed}
									<del>{$file.name|escape}</del>
								{elseif $file.newName}
									<span class="ra_new">{$file.newName|escape}</span>
								{else}
									{$file.name|escape}
								{/if}
							</td>
							<td>{$file.stage|escape}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</section>
	{/foreach}
</div>
