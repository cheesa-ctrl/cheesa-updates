{**
 * plugins/generic/cheesaControlPanel/templates/panel.tpl
 *}
<div id="cheesaCP" class="cheesa-cp" data-action="{$cpAction|escape}" data-csrf="{csrf type="raw"}">
<style>
	.cheesa-cp {ldelim} font-size: 14px; {rdelim}
	.cheesa-cp .cp-msg {ldelim} margin: 0 0 12px; padding: 10px 12px; border-left: 4px solid #2e7d32; background: #eef7ee; {rdelim}
	.cheesa-cp .cp-err {ldelim} margin: 0 0 12px; padding: 10px 12px; border-left: 4px solid #c62828; background: #fdecec; {rdelim}
	.cheesa-cp .cp-warn {ldelim} margin: 0 0 12px; padding: 10px 12px; border-left: 4px solid #ed991f; background: #fff8ec; {rdelim}
	.cheesa-cp table {ldelim} width: 100%; border-collapse: collapse; margin: 6px 0 16px; {rdelim}
	.cheesa-cp th, .cheesa-cp td {ldelim} padding: 9px 10px; border-bottom: 1px solid #e3e6ef; text-align: left; vertical-align: middle; {rdelim}
	.cheesa-cp th {ldelim} background: #030271; color: #fff; font-weight: 600; {rdelim}
	.cheesa-cp .cp-new {ldelim} display: inline-block; padding: 2px 8px; border-radius: 10px; background: #ed991f; color: #fff; font-size: 12px; font-weight: 700; {rdelim}
	.cheesa-cp .cp-ok {ldelim} color: #2e7d32; font-weight: 600; {rdelim}
	.cheesa-cp button {ldelim} padding: 6px 12px; border-radius: 4px; border: 1px solid #030271; background: #030271; color: #fff; cursor: pointer; font-weight: 600; {rdelim}
	.cheesa-cp button.cp-sec {ldelim} background: #fff; color: #030271; {rdelim}
	.cheesa-cp button[disabled] {ldelim} opacity: .55; cursor: wait; {rdelim}
	.cheesa-cp input[type=text] {ldelim} width: 100%; max-width: 560px; padding: 7px 9px; {rdelim}
	.cheesa-cp small {ldelim} color: #5b6178; {rdelim}
</style>

{if $cpMsg}<div class="cp-msg">{$cpMsg|escape}</div>{/if}
{if $cpErr}<div class="cp-err">{$cpErr|escape}</div>{/if}
{if $cpProblems}<div class="cp-warn"><strong>Update belum bisa dijalankan:</strong><br>{foreach from=$cpProblems item=p}{$p|escape}<br>{/foreach}</div>{/if}
{if !$cpAdmin}<div class="cp-warn">Anda bisa melihat versi, tetapi hanya Site Administrator yang dapat memasang update.</div>{/if}

<table>
	<thead><tr><th>Paket</th><th>Terpasang</th><th>Tersedia</th><th></th></tr></thead>
	<tbody>
	{foreach from=$cpRows item=r}
		<tr>
			<td><strong>{$r.label|escape}</strong><br><small>plugins/{$r.category|escape}/{$r.name|escape}</small></td>
			<td>{if $r.installed}{$r.installed|escape}{else}<small>belum terpasang</small>{/if}</td>
			<td>{if $r.available}{$r.available|escape}{if $r.canUpdate} <span class="cp-new">baru</span>{/if}{if $r.released}<br><small>{$r.released|escape}</small>{/if}{else}<small>—</small>{/if}</td>
			<td style="white-space:nowrap">
				{if $r.canUpdate && $cpAdmin}<button type="button" data-cp-verb="update" data-cp-package="{$r.name|escape}">{if $r.installed}Update{else}Pasang{/if}</button>{elseif $r.installed && $r.available}<span class="cp-ok">✓ terbaru</span>{/if}
				{if $r.backups && $cpAdmin} <button type="button" class="cp-sec" data-cp-verb="rollback" data-cp-package="{$r.name|escape}" title="Kembalikan versi sebelumnya">Kembalikan</button>{/if}
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>

<p>
	<label for="cpManifestUrl"><strong>Alamat update (manifest.json)</strong></label><br>
	<input type="text" id="cpManifestUrl" value="{$cpUrl|escape}" placeholder="https://raw.githubusercontent.com/cheesa-ctrl/cheesa-updates/main/manifest.json">
	<br><label for="cpToken"><strong>Token GitHub</strong> <small>(hanya bila repo dibuat Private)</small></label><br>
	<input type="password" id="cpToken" autocomplete="off" placeholder="{if $cpHasToken}token tersimpan — isi untuk mengganti, isi - untuk menghapus{else}kosongkan bila repo Public{/if}">
	<br><button type="button" class="cp-sec" data-cp-verb="saveUrl">Simpan</button>
	<button type="button" class="cp-sec" data-cp-verb="check">Cek update</button><br>
	<small>Kosongkan lalu Simpan untuk kembali ke alamat bawaan. {if $cpGenerated}Manifest terverifikasi, dibuat {$cpGenerated|escape}. {/if}Paket hanya dipasang bila tanda tangan digitalnya cocok. Versi lama otomatis dicadangkan (3 terakhir).</small>
</p>

{if $cpRecovery}
<details style="margin:4px 0 14px">
	<summary><strong>Link pemulihan darurat</strong> — simpan di tempat aman</summary>
	<p><small>Bila setelah update situs atau dasbor OJS tidak bisa dibuka, buka link ini untuk mengembalikan versi sebelumnya.
	Link ini tetap berfungsi walau OJS error, dan tidak memerlukan cPanel/FTP. Jangan dibagikan.</small></p>
	<input type="text" readonly value="{$cpRecovery|escape}" onclick="this.select()">
</details>
{/if}

<script>
{literal}
(function ($) {
	$(document).off('click.cheesacp').on('click.cheesacp', '#cheesaCP [data-cp-verb]', function () {
		var $b = $(this), $cp = $('#cheesaCP'), verb = $b.data('cp-verb');
		if (verb === 'update' && !confirm('Pasang update ' + $b.data('cp-package') + '? Versi saat ini akan dicadangkan.')) return;
		if (verb === 'rollback' && !confirm('Kembalikan ' + $b.data('cp-package') + ' ke versi cadangan terakhir?')) return;
		$cp.find('button').prop('disabled', true);
		$b.text(verb === 'update' ? 'Memasang…' : verb === 'rollback' ? 'Mengembalikan…' : 'Memproses…');
		$.post($cp.data('action'), {
			verb: verb, package: $b.data('cp-package') || '', manifestUrl: $('#cpManifestUrl').val(), githubToken: $('#cpToken').val() || '', csrfToken: $cp.data('csrf')
		}, null, 'json').done(function (r) {
			if (r && r.status && r.content) $cp.replaceWith(r.content);
			else { alert((r && r.content) || 'Gagal memproses permintaan.'); $cp.find('button').prop('disabled', false); }
		}).fail(function (x) {
			alert('Permintaan gagal (' + x.status + '). Periksa log error server.');
			$cp.find('button').prop('disabled', false);
		});
	});
})(jQuery);
{/literal}
</script>
</div>
