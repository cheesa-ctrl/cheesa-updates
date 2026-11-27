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

	/* Google Drive review files */
	.pkp_review_archive .ra_drive .ra_header p {ldelim} margin-top: .35rem; {rdelim}
	.pkp_review_archive .ra_dv_meta {ldelim} font-size: .8rem; color: #555; padding: 0 1rem; margin: .25rem 0 .75rem; {rdelim}
	.pkp_review_archive .ra_dv_badge {ldelim} display: inline-block; background: #e6f0f5; color: #006798; border-radius: 10px; padding: .02rem .5rem; font-size: .72rem; margin-left: .35rem; vertical-align: middle; {rdelim}
	.pkp_review_archive .ra_dv_rev {ldelim} background: #eaf6ea; color: #2a7a2a; {rdelim}
	.pkp_review_archive .ra_dv_group {ldelim} border: 1px solid #e3e3e3; border-radius: 4px; margin: 0 1rem 1rem; {rdelim}
	.pkp_review_archive .ra_dv_gh {ldelim} margin: 0; padding: .5rem .75rem; background: #f7f9fa; border-bottom: 1px solid #e3e3e3; font-size: .85rem; font-weight: 700; {rdelim}
	.pkp_review_archive .ra_dv_gh.is_log {ldelim} background: #fff7e6; {rdelim}
	.pkp_review_archive .ra_dv_n {ldelim} color: #999; font-weight: 400; {rdelim}
	.pkp_review_archive ul.ra_dv_files {ldelim} list-style: none; margin: 0; padding: 0; {rdelim}
	.pkp_review_archive li.ra_dv_file {ldelim} display: flex; align-items: center; gap: .5rem; padding: .45rem .75rem; border-top: 1px solid #f1f1f1; font-size: .85rem; {rdelim}
	.pkp_review_archive li.ra_dv_file:first-child {ldelim} border-top: 0; {rdelim}
	.pkp_review_archive .ra_dv_fname {ldelim} flex: 1; word-break: break-word; {rdelim}
	.pkp_review_archive .ra_dv_sz {ldelim} color: #999; font-size: .78rem; white-space: nowrap; {rdelim}
	.pkp_review_archive .ra_dv_act {ldelim} white-space: nowrap; {rdelim}
	.pkp_review_archive .ra_dv_act button, .pkp_review_archive .ra_dv_act a.dl {ldelim} font-size: .78rem; padding: .2rem .55rem; border-radius: 3px; border: 1px solid #c3d4de; background: #fff; color: #006798; cursor: pointer; text-decoration: none; margin-left: .25rem; {rdelim}
	.pkp_review_archive .ra_dv_note {ldelim} padding: .75rem 1rem; color: #555; font-size: .85rem; {rdelim}
	.pkp_review_archive .ra_dv_edit {ldelim} padding: .25rem 1rem 1rem; {rdelim}
	.pkp_review_archive .ra_dv_edit summary {ldelim} cursor: pointer; color: #006798; font-size: .82rem; {rdelim}
	.pkp_review_archive .ra_dv_edit input[type=text] {ldelim} width: 100%; box-sizing: border-box; padding: .4rem; margin: .4rem 0; font: inherit; {rdelim}
	.ra_modal_bg {ldelim} position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 99999; display: none; {rdelim}
	.ra_modal_bg.open {ldelim} display: block; {rdelim}
	.ra_modal_box {ldelim} position: absolute; top: 3%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 920px; height: 90%; background: #fff; border-radius: 5px; display: flex; flex-direction: column; overflow: hidden; {rdelim}
	.ra_modal_hd {ldelim} display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-bottom: 1px solid #ddd; {rdelim}
	.ra_modal_hd .t {ldelim} flex: 1; font-size: .85rem; font-weight: 700; word-break: break-word; {rdelim}
	.ra_modal_hd a, .ra_modal_hd button {ldelim} font-size: .8rem; cursor: pointer; {rdelim}
	.ra_modal_box iframe {ldelim} flex: 1; border: 0; width: 100%; {rdelim}
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

{literal}
<script>
	(function() {
		var $doc = $(document);

		// Preview a Drive file in a modal iframe.
		$doc.off('click.raDrive').on('click.raDrive', '.ra_dv_view', function() {
			var fid = $(this).data('fid'), name = $(this).data('name') || '';
			if (!fid) return;
			$('#raPreviewTitle').text(name);
			$('#raPreviewOpen').attr('href', 'https://drive.google.com/file/d/' + fid + '/view');
			$('#raPreviewFrame').attr('src', 'https://drive.google.com/file/d/' + fid + '/preview');
			$('#raPreviewBg').addClass('open');
		});
		function raClose() {
			$('#raPreviewBg').removeClass('open');
			$('#raPreviewFrame').attr('src', 'about:blank');
		}
		$doc.off('click.raDriveClose').on('click.raDriveClose', '#raPreviewClose, #raPreviewBg', function(e) {
			if (e.target === this) raClose();
		});
		$doc.off('keyup.raDrive').on('keyup.raDrive', function(e) { if (e.key === 'Escape') raClose(); });

		// Save the manual per-article folder link.
		$doc.off('submit.raDrive').on('submit.raDrive', 'form.ra_dv_form', function(e) {
			e.preventDefault();
			var $form = $(this), $status = $form.find('.ra_dv_status');
			$status.text($form.data('saving'));
			$.post($form.attr('action'), $form.serialize(), function(response) {
				$status.text((response && response.status) ? $form.data('saved') : ($form.data('failed')));
			}, 'json').fail(function() { $status.text($form.data('failed')); });
		});
	})();
</script>
{/literal}

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

	{if $driveEnabled || $driveCanEdit}
	<div class="ra_section ra_drive">
		<div class="ra_header">
			<h4>Berkas Review (Google Drive)</h4>
			{if $driveMatched}
				<p class="ra_muted">
					Diambil otomatis dari folder{if $driveFolderName} <strong>{$driveFolderName|escape}</strong>{/if}{if $driveReason} <span class="ra_dv_badge">cocok via {$driveReason|escape}</span>{/if}{if $driveFolderUrl} &middot; <a href="{$driveFolderUrl|escape}" target="_blank" rel="noopener">buka folder di Drive</a>{/if}
				</p>
			{/if}
		</div>

		{if $driveReviewers}
			<div class="ra_dv_meta">Reviewer terdeteksi:{foreach from=$driveReviewers item=rv}<span class="ra_dv_badge ra_dv_rev">{$rv|escape}</span>{/foreach}</div>
		{/if}

		{if $driveGroups}
			{foreach from=$driveGroups item=g}
				<div class="ra_dv_group">
					<h5 class="ra_dv_gh{if $g.isLog} is_log{/if}">
						{$g.name|escape}{if $g.isReviewer}<span class="ra_dv_badge ra_dv_rev">reviewer</span>{/if}{if $g.isLog}<span class="ra_dv_badge">activity log</span>{/if} <span class="ra_dv_n">({$g.files|@count})</span>
					</h5>
					<ul class="ra_dv_files">
						{foreach from=$g.files item=f}
							<li class="ra_dv_file">
								<span class="ra_dv_fname">{$f.name|escape}{if $f.isCert}<span class="ra_dv_badge">sertifikat</span>{/if}{if $f.isLog}<span class="ra_dv_badge">log</span>{/if}</span>
								{if $f.size}<span class="ra_dv_sz">{$f.size|escape}</span>{/if}
								<span class="ra_dv_act">
									{if $f.canPreview}<button type="button" class="ra_dv_view" data-fid="{$f.id|escape}" data-name="{$f.name|escape}">Lihat</button>{/if}
									{if $f.isGoogle}<a class="dl" href="{$f.openUrl|escape}" target="_blank" rel="noopener">Buka di Drive</a>{else}<a class="dl" href="{$f.dlUrl|escape}" target="_blank" rel="noopener">Unduh</a>{/if}
								</span>
							</li>
						{/foreach}
					</ul>
				</div>
			{/foreach}
		{/if}

		{if $driveMessage}
			<div class="ra_dv_note">{$driveMessage|escape}</div>
		{elseif !$driveEnabled}
			<div class="ra_dv_note">Fitur Google Drive belum aktif. Isi <em>Google API Key</em> dan <em>Folder kumpulan Drive</em> di pengaturan plugin Review Archive.</div>
		{/if}

		{if $driveCanEdit}
			<details class="ra_dv_edit"{if $driveMessage && !$driveMatched} open{/if}>
				<summary>Tempel tautan subfolder artikel ini (manual)</summary>
				<form class="ra_dv_form" method="post" action="{$driveSaveUrl|escape}" data-saving="Menyimpan…" data-saved="Tersimpan. Buka tab lain lalu kembali ke sini untuk memuat berkasnya." data-failed="Gagal menyimpan.">
					<input type="hidden" name="csrfToken" value="{$csrfToken|escape}">
					<input type="text" name="reviewArchiveDriveFolder" value="{$driveOverrideUrl|escape}" placeholder="https://drive.google.com/drive/folders/XXXX">
					<button type="submit" class="pkpButton">Simpan tautan</button>
					<span class="ra_dv_status ra_muted"></span>
				</form>
				<p class="ra_dv_meta">Dipakai jika pencocokan otomatis salah/tak ketemu. Kosongkan lalu simpan untuk kembali ke otomatis.</p>
			</details>
		{/if}

		<div class="ra_modal_bg" id="raPreviewBg">
			<div class="ra_modal_box">
				<div class="ra_modal_hd">
					<span class="t" id="raPreviewTitle"></span>
					<a id="raPreviewOpen" href="#" target="_blank" rel="noopener">Buka di Drive</a>
					<button type="button" id="raPreviewClose">Tutup</button>
				</div>
				<iframe id="raPreviewFrame" src="about:blank" referrerpolicy="no-referrer"></iframe>
			</div>
		</div>
	</div>
	{/if}

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
