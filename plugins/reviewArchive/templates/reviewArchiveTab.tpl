{**
 * plugins/generic/reviewArchive/templates/reviewArchiveTab.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Content of the "Arsip Review" workflow tab.
 *}
<style>
	.pkp_review_archive {ldelim} padding: 1rem 0; {rdelim}
	.pkp_review_archive .ra_section {ldelim} margin-bottom: 2rem; background: #fff; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.2); {rdelim}
	.pkp_review_archive .ra_header {ldelim} padding: 1rem; border-bottom: 1px solid #ddd; {rdelim}
	.pkp_review_archive .ra_header h4 {ldelim} margin: 0; font-size: .93rem; font-weight: 700; {rdelim}
	.pkp_review_archive .ra_header p {ldelim} margin: .5rem 0 0; font-size: .875rem; color: #555; {rdelim}
	.pkp_review_archive table {ldelim} width: 100%; border-collapse: collapse; font-size: .875rem; {rdelim}
	.pkp_review_archive th {ldelim} text-align: left; font-weight: 400; color: #555; padding: .5rem 1rem; border-bottom: 1px solid #ddd; {rdelim}
	.pkp_review_archive td {ldelim} padding: .75rem 1rem; border-bottom: 1px solid #eee; vertical-align: top; {rdelim}
	.pkp_review_archive td.ra_name {ldelim} word-break: break-word; {rdelim}
	.pkp_review_archive td.ra_empty {ldelim} text-align: center; font-style: italic; color: #777; {rdelim}
	.pkp_review_archive .ra_downloads a {ldelim} display: inline-block; margin: 0 .25rem .25rem 0; {rdelim}
	.pkp_review_archive .ra_body {ldelim} padding: 1rem; font-size: .875rem; line-height: 1.6; {rdelim}
	.pkp_review_archive .ra_body h5 {ldelim} margin: 1.25rem 0 .5rem; font-size: .875rem; {rdelim}
	.pkp_review_archive .ra_statement {ldelim} border-left: 4px solid #006798; {rdelim}
	.pkp_review_archive .ra_statement_note {ldelim} margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #ddd; {rdelim}
	.pkp_review_archive .ra_statement_text a {ldelim} word-break: break-all; {rdelim}
	.pkp_review_archive .ra_certificates {ldelim} margin: 0; padding-left: 1.25rem; {rdelim}
	.pkp_review_archive .ra_muted {ldelim} color: #666; {rdelim}
	.pkp_review_archive .ra_small {ldelim} font-size: .8rem; margin-top: 1rem; {rdelim}
	.pkp_review_archive .ra_edit {ldelim} margin-top: 1rem; {rdelim}
	.pkp_review_archive .ra_edit summary {ldelim} cursor: pointer; color: #006798; font-weight: 700; {rdelim}
	.pkp_review_archive .ra_statement_form {ldelim} margin-top: .75rem; {rdelim}
	.pkp_review_archive .ra_statement_form label {ldelim} display: block; font-weight: 700; margin-bottom: .25rem; {rdelim}
	.pkp_review_archive .ra_statement_form textarea {ldelim} width: 100%; box-sizing: border-box; font: inherit; padding: .5rem; {rdelim}
	.pkp_review_archive .ra_save_status {ldelim} margin-left: .5rem; color: #666; {rdelim}
</style>

<script>
	// Save the editor statement without leaving the tab.
	$(document).off('submit.reviewArchive').on('submit.reviewArchive', 'form.ra_statement_form', function(e) {ldelim}
		e.preventDefault();
		var $form = $(this), $status = $form.find('.ra_save_status'), $box = $form.closest('.ra_statement');
		$status.text($form.data('saving'));
		$.post($form.attr('action'), $form.serialize(), function(response) {ldelim}
			if (response && response.status && response.content) {ldelim}
				$box.replaceWith(response.content);
			{rdelim} else {ldelim}
				$status.text((response && response.content) || $form.data('failed'));
			{rdelim}
		{rdelim}, 'json').fail(function() {ldelim}
			$status.text($form.data('failed'));
		{rdelim});
	{rdelim});
</script>

{function name=reviewArchiveRows files=[] emptyKey="" dateFormat="%Y-%m-%d"}
	{foreach from=$files item=file}
		<tr>
			<td class="ra_name">
				<span class="pkp_screen_reader">#{$file.id}</span>
				{$file.name|escape}
			</td>
			<td>{$file.stage|escape}</td>
			<td>{if $file.date}{$file.date|date_format:$dateFormat}{/if}</td>
			<td class="ra_downloads">
				{foreach from=$file.revisions item=revision}
					<a href="{$revision.url|escape}" class="pkpButton">
						{if $revision.latest}
							{translate key="plugins.generic.reviewArchive.download.latest"}
						{else}
							{translate key="plugins.generic.reviewArchive.download.revision" number=$revision.number}
						{/if}
					</a>
				{/foreach}
			</td>
		</tr>
	{foreachelse}
		<tr><td class="ra_empty" colspan="4">{translate key=$emptyKey}</td></tr>
	{/foreach}
{/function}

<div class="pkp_review_archive">

	{include file=$statementTemplate}

	<div class="ra_section">
		<div class="ra_header">
			<h4>{translate key="plugins.generic.reviewArchive.marked.title"}</h4>
			<p>{translate key="plugins.generic.reviewArchive.marked.description" marker=$reviewMarker|escape}</p>
		</div>
		<table>
			<thead>
				<tr>
					<th>{translate key="plugins.generic.reviewArchive.column.file"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.stage"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.date"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.download"}</th>
				</tr>
			</thead>
			<tbody>
				{call name=reviewArchiveRows files=$markedFiles dateFormat=$dateFormatShort emptyKey="plugins.generic.reviewArchive.marked.empty"}
			</tbody>
		</table>
	</div>

	<div class="ra_section">
		<div class="ra_header">
			<h4>{translate key="plugins.generic.reviewArchive.hidden.title"}</h4>
			<p>{translate key="plugins.generic.reviewArchive.hidden.description"}</p>
		</div>
		<table>
			<thead>
				<tr>
					<th>{translate key="plugins.generic.reviewArchive.column.file"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.stage"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.date"}</th>
					<th>{translate key="plugins.generic.reviewArchive.column.download"}</th>
				</tr>
			</thead>
			<tbody>
				{call name=reviewArchiveRows files=$hiddenFiles dateFormat=$dateFormatShort emptyKey="plugins.generic.reviewArchive.hidden.empty"}
			</tbody>
		</table>
	</div>

</div>
