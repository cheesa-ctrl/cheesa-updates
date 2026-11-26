{**
 * plugins/generic/reviewArchive/templates/judolReport.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Shown in the Native XML import results tab instead of importing, when the
 * XML contains online-gambling ("judol") indications.
 *}
<style>
	.ra_judol {ldelim} padding: 1rem 0; font-size: .875rem; {rdelim}
	.ra_judol .ra_alert {ldelim} border-left: 4px solid #d00a6c; background: #fdeef5; padding: 1rem; margin-bottom: 1.5rem; {rdelim}
	.ra_judol .ra_alert h3 {ldelim} margin: 0 0 .5rem; font-size: 1rem; color: #d00a6c; {rdelim}
	.ra_judol .ra_alert p {ldelim} margin: .25rem 0; {rdelim}
	.ra_judol table {ldelim} width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.2); {rdelim}
	.ra_judol th {ldelim} text-align: left; font-weight: 400; color: #555; padding: .5rem 1rem; border-bottom: 1px solid #ddd; {rdelim}
	.ra_judol td {ldelim} padding: .75rem 1rem; border-bottom: 1px solid #eee; vertical-align: top; word-break: break-word; {rdelim}
	.ra_judol .ra_term {ldelim} font-weight: 700; color: #d00a6c; white-space: nowrap; {rdelim}
	.ra_judol code {ldelim} font-size: .8rem; background: #f4f4f4; padding: .1rem .25rem; {rdelim}
	.ra_judol ul {ldelim} margin: .5rem 0 0 1.25rem; padding: 0; {rdelim}
</style>

<div class="ra_judol">
	<div class="ra_alert">
		<h3>{translate key="plugins.generic.reviewArchive.judol.blocked.title"}</h3>
		<p>{translate key="plugins.generic.reviewArchive.judol.blocked.description" file=$judolFileName|escape count=$judolReport.findings|@count}</p>
		<p>{translate key="plugins.generic.reviewArchive.judol.blocked.scanned" count=$judolReport.scannedFiles}</p>
	</div>

	<table>
		<thead>
			<tr>
				<th>{translate key="plugins.generic.reviewArchive.judol.column.location"}</th>
				<th>{translate key="plugins.generic.reviewArchive.judol.column.term"}</th>
				<th>{translate key="plugins.generic.reviewArchive.judol.column.context"}</th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$judolReport.findings item=finding}
				<tr>
					<td>{$finding.location|escape}</td>
					<td class="ra_term">
						{$finding.term|escape}
						{if $finding.kind == 'kode'}<br><small>{translate key="plugins.generic.reviewArchive.judol.kind.code"}</small>{/if}
					</td>
					<td><code>{$finding.snippet|escape}</code></td>
				</tr>
			{/foreach}
		</tbody>
	</table>

	{if $judolReport.skipped}
		<p><strong>{translate key="plugins.generic.reviewArchive.judol.skipped"}</strong></p>
		<ul>
			{foreach from=$judolReport.skipped item=skipped}
				<li>{$skipped|escape}</li>
			{/foreach}
		</ul>
	{/if}

	<p>{translate key="plugins.generic.reviewArchive.judol.nextSteps"}</p>
</div>
