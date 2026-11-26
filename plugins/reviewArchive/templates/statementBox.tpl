{**
 * plugins/generic/reviewArchive/templates/statementBox.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * "Pernyataan Editor" box for accreditation assessors, with the reviewer
 * certificates. Rendered inside the "Arsip Review" tab and returned again
 * after saving.
 *}
<div class="ra_section ra_statement" id="reviewArchiveStatement-{$submission->getId()|escape}">
	<div class="ra_header">
		<h4>{translate key="plugins.generic.reviewArchive.statement.title"}</h4>
		<p>{translate key="plugins.generic.reviewArchive.statement.description"}</p>
	</div>
	<div class="ra_body">
		{if $statementDefaultHtml}
			<div class="ra_statement_text">{$statementDefaultHtml}</div>
		{/if}
		{if $statementNoteHtml}
			<div class="ra_statement_text ra_statement_note">{$statementNoteHtml}</div>
		{/if}
		{if !$statementDefaultHtml && !$statementNoteHtml}
			<p class="ra_muted">{translate key="plugins.generic.reviewArchive.statement.empty"}</p>
		{/if}

		<h5>{translate key="plugins.generic.reviewArchive.certificates.title" id=$statementSourceId|escape}</h5>
		{if $certificates}
			<ul class="ra_certificates">
				{foreach from=$certificates item=certificate}
					<li>
						<a href="{$certificate.url|escape}" target="_blank" rel="noopener noreferrer">
							{translate key="plugins.generic.reviewArchive.certificates.item" number=$certificate.number}
						</a>
						{if $certificate.name}<span class="ra_muted"> — {$certificate.name|escape}</span>{/if}
					</li>
				{/foreach}
			</ul>
		{else}
			<p class="ra_muted">{translate key="plugins.generic.reviewArchive.certificates.none"}</p>
		{/if}

		{if $statementBy}
			<p class="ra_muted ra_small">{translate key="plugins.generic.reviewArchive.statement.lastEdited" name=$statementBy|escape date=$statementAt|date_format:"%Y-%m-%d %H:%M"}</p>
		{/if}

		{if $statementCanEdit}
			<details class="ra_edit">
				<summary>{translate key="plugins.generic.reviewArchive.statement.edit"}</summary>
				<form class="ra_statement_form" method="post" action="{$statementSaveUrl|escape}" data-saving="{translate|escape key="plugins.generic.reviewArchive.statement.saving"}" data-failed="{translate|escape key="plugins.generic.reviewArchive.statement.saveFailed"}">
					<input type="hidden" name="csrfToken" value="{$csrfToken|escape}">
					<label for="reviewArchiveStatementText-{$submission->getId()|escape}">{translate key="plugins.generic.reviewArchive.statement.noteLabel"}</label>
					<textarea id="reviewArchiveStatementText-{$submission->getId()|escape}" name="reviewArchiveStatement" rows="6">{$statementNote|escape}</textarea>
					<p class="ra_muted ra_small">{translate key="plugins.generic.reviewArchive.statement.noteHelp"}</p>
					<button type="submit" class="pkpButton">{translate key="common.save"}</button>
					<span class="ra_save_status" role="status"></span>
				</form>
			</details>
		{/if}
	</div>
</div>
