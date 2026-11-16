{**
 * plugins/generic/cheesaControlPanel/templates/panel.tpl
 * Isi panel. Dipakai di modal "Buka Panel" dan di halaman penuh /cheesapanel.
 *}
<div id="cheesaCP" class="ccp{if $cpFullPage} ccp-full{/if}" data-action="{$cpAction|escape}" data-csrf="{csrf type="raw"}" data-fullpage="{if $cpFullPage}1{/if}">
{literal}
<style>
	.ccp { --ccp-navy: #030271; --ccp-navy-2: #1b1a8f; --ccp-accent: #ed991f; --ccp-ok: #1f8a4c; --ccp-red: #c62828;
		--ccp-line: #e4e7f0; --ccp-soft: #f5f6fb; --ccp-muted: #5e6580;
		font: 14px/1.5 "Noto Sans", Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2140; }
	.ccp *, .ccp *::before, .ccp *::after { box-sizing: border-box; }

	/* kepala */
	.ccp-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
		padding: 18px 20px; margin: 0 0 16px; border-radius: 14px; color: #fff;
		background: radial-gradient(120% 140% at 100% 0, rgba(237,153,31,.35) 0, rgba(237,153,31,0) 45%), linear-gradient(135deg, var(--ccp-navy) 0, var(--ccp-navy-2) 100%); }
	.ccp-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
	.ccp-logo { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
		background: var(--ccp-accent); color: #fff; font-weight: 800; font-size: 20px; box-shadow: 0 6px 16px rgba(0,0,0,.2); }
	.ccp-brand h2 { margin: 0; font-size: 18px; font-weight: 700; color: #fff; line-height: 1.25; }
	.ccp-brand p { margin: 2px 0 0; font-size: 12.5px; color: rgba(255,255,255,.78); }
	.ccp-head-act { display: flex; gap: 8px; flex-wrap: wrap; }
	.ccp-head .ccp-btn { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.35); color: #fff; }
	.ccp-head .ccp-btn:hover { background: rgba(255,255,255,.22); }

	/* ringkasan */
	.ccp-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 16px; }
	.ccp-stat { padding: 12px 14px; border: 1px solid var(--ccp-line); border-radius: 12px; background: #fff; }
	.ccp-stat span { display: block; font-size: 11.5px; letter-spacing: .04em; text-transform: uppercase; color: var(--ccp-muted); font-weight: 600; }
	.ccp-stat b { display: block; margin-top: 2px; font-size: 22px; line-height: 1.2; color: var(--ccp-navy); }
	.ccp-stat b small { font-size: 12px; font-weight: 600; color: var(--ccp-muted); }
	.ccp-stat.is-hot { border-color: rgba(237,153,31,.55); background: #fff8ec; }
	.ccp-stat.is-hot b { color: #b86a00; }

	/* pesan */
	.ccp-note { display: flex; gap: 10px; align-items: flex-start; margin: 0 0 14px; padding: 11px 14px; border-radius: 10px; border: 1px solid; }
	.ccp-note::before { flex: 0 0 auto; font-weight: 800; }
	.ccp-note.ok { background: #ecf8f1; border-color: #b9e4cb; color: #14532d; }
	.ccp-note.ok::before { content: "✓"; }
	.ccp-note.err { background: #fdecec; border-color: #f3c0c0; color: #7f1d1d; }
	.ccp-note.err::before { content: "!"; }
	.ccp-note.warn { background: #fff8ec; border-color: #f6d9a8; color: #7a4a00; }
	.ccp-note.warn::before { content: "i"; }

	/* daftar paket */
	.ccp-list { display: grid; gap: 10px; margin: 0 0 18px; }
	.ccp-item { display: grid; grid-template-columns: 44px minmax(0, 1fr) 120px 170px 400px; align-items: center; gap: 14px;
		padding: 14px 16px; background: #fff; border: 1px solid var(--ccp-line); border-radius: 12px; transition: box-shadow .15s, border-color .15s; }
	.ccp-item:hover { border-color: #cfd4e6; box-shadow: 0 6px 18px rgba(3,2,113,.07); }
	.ccp-item.has-update { border-left: 4px solid var(--ccp-accent); }
	.ccp-item.is-gone { background: var(--ccp-soft); }
	.ccp-ico { width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; font-weight: 800; font-size: 15px; color: var(--ccp-navy); background: #e9ebfb; }
	.ccp-ico.cat-themes { background: #fff1db; color: #a45d00; }
	.ccp-item.is-gone .ccp-ico { background: #eceef4; color: #8a90a6; }
	.ccp-name { font-weight: 700; font-size: 15px; color: #1d2140; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
	.ccp-tag { font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: 1px 7px; border-radius: 99px; background: var(--ccp-soft); color: var(--ccp-muted); border: 1px solid var(--ccp-line); }
	.ccp-path { display: block; margin-top: 2px; font: 12px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--ccp-muted); overflow-wrap: anywhere; }
	.ccp-ver .ccp-lbl { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--ccp-muted); font-weight: 600; }
	.ccp-ver b { font-size: 15px; color: #1d2140; }
	.ccp-ver small { display: block; color: var(--ccp-muted); font-size: 11.5px; }
	.ccp-ver .none { color: #8a90a6; font-weight: 500; font-style: italic; font-size: 13px; }
	.ccp-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 99px; font-size: 11.5px; font-weight: 700; vertical-align: 2px; }
	.ccp-pill.new { background: var(--ccp-accent); color: #fff; }
	.ccp-pill.ok { background: #e3f4ea; color: var(--ccp-ok); }
	.ccp-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }

	/* tombol */
	.ccp-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 8px; border: 1px solid var(--ccp-navy);
		background: var(--ccp-navy); color: #fff !important; font-weight: 600; font-size: 13px; line-height: 1.2; font-family: inherit; cursor: pointer;
		text-decoration: none !important; white-space: nowrap; transition: background .15s, transform .05s; }
	.ccp-btn:hover { background: var(--ccp-navy-2); }
	.ccp-btn:active { transform: translateY(1px); }
	.ccp-btn.sec { background: #fff; color: var(--ccp-navy) !important; border-color: #c9cde3; }
	.ccp-btn.sec:hover { border-color: var(--ccp-navy); background: #f6f7fd; }
	.ccp-btn.del { background: #fff; color: var(--ccp-red) !important; border-color: #f0c4c4; }
	.ccp-btn.del:hover { background: #fdecec; border-color: var(--ccp-red); }
	.ccp-btn.ghost { background: transparent; color: var(--ccp-muted) !important; border-color: transparent; padding-left: 8px; padding-right: 8px; }
	.ccp-btn.ghost:hover { background: #f1f2f8; border-color: #dfe2ee; color: #1d2140 !important; }
	.ccp-hid { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--ccp-line); }
	.ccp-hid:last-child { border-bottom: 0; }
	.ccp-btn.go { background: var(--ccp-accent); border-color: var(--ccp-accent); }
	.ccp-btn.go:hover { background: #d9860f; }
	.ccp-btn[disabled] { opacity: .5; cursor: wait; }

	/* pengaturan & pemulihan */
	.ccp-box { margin: 0 0 12px; border: 1px solid var(--ccp-line); border-radius: 12px; background: #fff; }
	.ccp-box > summary { cursor: pointer; padding: 12px 16px; font-weight: 700; color: var(--ccp-navy); list-style: none; display: flex; justify-content: space-between; align-items: center; }
	.ccp-box > summary::-webkit-details-marker { display: none; }
	.ccp-box > summary::after { content: "▾"; color: var(--ccp-muted); transition: transform .15s; }
	.ccp-box[open] > summary::after { transform: rotate(180deg); }
	.ccp-box > summary small { font-weight: 500; color: var(--ccp-muted); margin-left: 8px; }
	.ccp-box-in { padding: 4px 16px 16px; border-top: 1px solid var(--ccp-line); }
	.ccp-field { margin: 12px 0 0; }
	.ccp-field label { display: block; font-weight: 600; margin: 0 0 4px; }
	.ccp-field label small { font-weight: 400; color: var(--ccp-muted); }
	.ccp-field input { width: 100%; max-width: 640px; padding: 8px 10px; border: 1px solid #cdd1e1; border-radius: 8px; font-size: 13px; font-family: inherit; }
	.ccp-field input:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--ccp-navy); }
	.ccp-help { margin: 10px 0 0; font-size: 12.5px; color: var(--ccp-muted); }

	@media (max-width: 1100px) {
		.ccp-item { grid-template-columns: 44px minmax(0, 1fr) minmax(0, 1fr); }
		.ccp-item .ccp-info { grid-column: 2 / -1; }
		.ccp-item .ccp-ver.inst { grid-column: 2; }
		.ccp-item .ccp-actions { grid-column: 1 / -1; justify-content: flex-start; }
	}
	@media (max-width: 560px) { .ccp-stats { grid-template-columns: 1fr; } }
</style>
{/literal}

<div class="ccp-head">
	<div class="ccp-brand">
		<div class="ccp-logo">C</div>
		<div>
			<h2>CHEESA Control Panel</h2>
			<p>Update, kembalikan, dan uninstall plugin CHEESA langsung dari OJS</p>
		</div>
	</div>
	<div class="ccp-head-act">
		<button type="button" class="ccp-btn" data-cp-verb="check">⟳ Cek update</button>
		{if $cpPageUrl && !$cpFullPage}<a class="ccp-btn" href="{$cpPageUrl|escape}" target="_blank" rel="noopener">⤢ Halaman penuh</a>{/if}
	</div>
</div>

<div class="ccp-stats">
	<div class="ccp-stat"><span>Terpasang</span><b>{$cpCountInstalled} <small>paket</small></b></div>
	<div class="ccp-stat{if $cpCountUpdates} is-hot{/if}"><span>Update tersedia</span><b>{$cpCountUpdates} <small>{if $cpCountUpdates}siap dipasang{else}semua terbaru{/if}</small></b></div>
	<div class="ccp-stat"><span>Manifest</span><b style="font-size:15px;padding-top:5px">{if $cpGenerated}✓ terverifikasi <small>{$cpGenerated|escape}</small>{else}<small>belum terbaca</small>{/if}</b></div>
</div>

{if $cpMsg}<div class="ccp-note ok">{$cpMsg|escape}</div>{/if}
{if $cpErr}<div class="ccp-note err">{$cpErr|escape}</div>{/if}
{if $cpProblems}<div class="ccp-note warn"><div><strong>Update belum bisa dijalankan:</strong><br>{foreach from=$cpProblems item=p}{$p|escape}<br>{/foreach}</div></div>{/if}
{if !$cpAdmin}<div class="ccp-note warn">Anda bisa melihat versi, tetapi hanya Site Administrator atau Journal Manager/Editor yang dapat memasang update atau menghapus plugin.</div>{/if}

<div class="ccp-list">
{foreach from=$cpRows item=r}
	<div class="ccp-item{if $r.canUpdate} has-update{/if}{if !$r.installed} is-gone{/if}">
		<div class="ccp-ico cat-{$r.category|escape}">{$r.label|truncate:2:"":true|upper|escape}</div>
		<div class="ccp-info">
			<div class="ccp-name">{$r.label|escape} <span class="ccp-tag">{if $r.category == 'themes'}tema{else}{$r.category|escape}{/if}</span></div>
			<span class="ccp-path">plugins/{$r.category|escape}/{$r.name|escape}</span>
		</div>
		<div class="ccp-ver inst">
			<span class="ccp-lbl">Terpasang</span>
			{if $r.installed}<b>{$r.installed|escape}</b>{else}<b class="none">belum terpasang</b>{/if}
		</div>
		<div class="ccp-ver">
			<span class="ccp-lbl">Tersedia</span>
			{if $r.available}
				<b>{$r.available|escape}</b>
				{if $r.canUpdate} <span class="ccp-pill new">baru</span>{elseif $r.installed} <span class="ccp-pill ok">✓ terbaru</span>{/if}
				{if $r.released}<small>{$r.released|escape}</small>{/if}
			{else}<b class="none">tidak ada di GitHub</b>{/if}
		</div>
		<div class="ccp-actions">
			{if $cpAdmin}
				{if $r.canUpdate}<button type="button" class="ccp-btn{if !$r.installed} go{/if}" data-cp-verb="update" data-cp-package="{$r.name|escape}" data-cp-label="{$r.label|escape}">{if $r.installed}↑ Update{else}+ Pasang{/if}</button>{/if}
				{if $r.backups}<button type="button" class="ccp-btn sec" data-cp-verb="rollback" data-cp-package="{$r.name|escape}" data-cp-label="{$r.label|escape}" title="Kembalikan cadangan terakhir ({$r.backups} tersimpan)">↺ Kembalikan</button>{/if}
				{if $r.canDelete}<button type="button" class="ccp-btn del" data-cp-verb="uninstall" data-cp-package="{$r.name|escape}" data-cp-label="{$r.label|escape}" title="Copot plugin ini dari server (dicadangkan dulu, bisa dikembalikan)">⏏ Uninstall</button>{/if}
				{if $r.canHide}<button type="button" class="ccp-btn ghost" data-cp-verb="hide" data-cp-package="{$r.name|escape}" data-cp-label="{$r.label|escape}" title="Hapus dari daftar ini saja — plugin di server tidak diubah">✕ Hapus</button>{/if}
			{/if}
		</div>
	</div>
{/foreach}
</div>

{if $cpHiddenRows}
<details class="ccp-box">
	<summary><span>Disembunyikan <small>{$cpHiddenRows|@count} paket dihapus dari daftar</small></span></summary>
	<div class="ccp-box-in">
		{foreach from=$cpHiddenRows item=r}
		<div class="ccp-hid">
			<span><strong>{$r.label|escape}</strong> <span class="ccp-path" style="display:inline">plugins/{$r.category|escape}/{$r.name|escape}</span>{if $r.installed} · <small>masih terpasang {$r.installed|escape}</small>{/if}</span>
			{if $cpAdmin}<button type="button" class="ccp-btn sec" data-cp-verb="unhide" data-cp-package="{$r.name|escape}">Tampilkan lagi</button>{/if}
		</div>
		{/foreach}
	</div>
</details>
{/if}

<details class="ccp-box"{if $cpSourceOpen} open{/if}>
	<summary><span>{if $cpSourceUnlocked}🔓{else}🔒{/if} Sumber update <small>manifest.json &amp; token GitHub{if !$cpSourceUnlocked} — perlu kata sandi{/if}</small></span></summary>
	<div class="ccp-box-in">
	{if $cpSourceUnlocked}
		<div class="ccp-field">
			<label for="cpManifestUrl">Alamat update (manifest.json)</label>
			<input type="text" id="cpManifestUrl" value="{$cpUrl|escape}" placeholder="https://raw.githubusercontent.com/cheesa-ctrl/cheesa-updates/main/manifest.json">
		</div>
		<div class="ccp-field">
			<label for="cpToken">Token GitHub <small>(hanya bila repo dibuat Private)</small></label>
			<input type="password" id="cpToken" autocomplete="off" placeholder="{if $cpHasToken}token tersimpan — isi untuk mengganti, isi - untuk menghapus{else}kosongkan bila repo Public{/if}">
		</div>
		<p style="margin:12px 0 0">
			<button type="button" class="ccp-btn" data-cp-verb="saveUrl">Simpan</button>
			<button type="button" class="ccp-btn sec" data-cp-verb="lockSource">🔒 Kunci lagi</button>
		</p>
		<p class="ccp-help">Kosongkan alamat lalu Simpan untuk kembali ke alamat bawaan. Paket hanya dipasang bila tanda tangan digitalnya cocok. Versi lama dan plugin yang di-uninstall otomatis dicadangkan (3 terakhir per paket). Bagian ini terkunci lagi otomatis saat Anda logout.</p>
	{else}
		<div class="ccp-field">
			<label for="cpSourcePass">Kata sandi</label>
			<input type="password" id="cpSourcePass" autocomplete="off" placeholder="Masukkan kata sandi untuk membuka pengaturan sumber update" style="max-width:420px">
		</div>
		<p style="margin:12px 0 0"><button type="button" class="ccp-btn" data-cp-verb="unlockSource">Buka</button></p>
		<p class="ccp-help">Alamat manifest dan token GitHub disembunyikan. Pemasangan update tetap bisa dilakukan tanpa membuka bagian ini.</p>
	{/if}
	</div>
</details>

{if $cpRecovery}
<details class="ccp-box">
	<summary><span>Link pemulihan darurat <small>simpan di tempat aman</small></span></summary>
	<div class="ccp-box-in">
		<p class="ccp-help">Bila setelah update situs atau dasbor OJS tidak bisa dibuka, buka link ini untuk mengembalikan versi sebelumnya.
		Link ini tetap berfungsi walau OJS error, dan tidak memerlukan cPanel/FTP. Jangan dibagikan.</p>
		<div class="ccp-field"><input type="text" readonly value="{$cpRecovery|escape}" onclick="this.select()"></div>
	</div>
</details>
{/if}

<script>
{literal}
(function () {
	// pengikat lama (panel versi sebelumnya, berbasis jQuery) dilepas supaya aksi tidak terkirim dua kali
	if (window.jQuery) window.jQuery(document).off('click.cheesacp');
	if (window.__cheesaCPBound) return;
	window.__cheesaCPBound = true;
	// Enter di kolom kata sandi = klik "Buka"
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Enter' && ev.target && ev.target.id === 'cpSourcePass') {
			ev.preventDefault();
			var b = document.querySelector('#cheesaCP [data-cp-verb="unlockSource"]'); if (b) b.click();
		}
	});
	var BUSY = { update: 'Memasang…', rollback: 'Mengembalikan…', uninstall: 'Meng-uninstall…', hide: 'Menghapus…', unhide: 'Memproses…', check: 'Memeriksa…', saveUrl: 'Menyimpan…', unlockSource: 'Memeriksa…', lockSource: 'Mengunci…' };
	document.addEventListener('click', function (ev) {
		var b = ev.target && ev.target.closest ? ev.target.closest('#cheesaCP [data-cp-verb]') : null;
		if (!b) return;
		ev.preventDefault();
		var cp = document.getElementById('cheesaCP');
		var verb = b.getAttribute('data-cp-verb'), pkg = b.getAttribute('data-cp-package') || '', label = b.getAttribute('data-cp-label') || pkg;
		if (verb === 'update' && !confirm('Pasang update ' + label + '? Versi saat ini akan dicadangkan.')) return;
		if (verb === 'rollback' && !confirm('Kembalikan ' + label + ' ke versi cadangan terakhir?')) return;
		if (verb === 'hide' && !confirm('Hapus ' + label + ' dari daftar ini?\n\nHanya barisnya yang hilang dari panel; plugin di server tidak diubah. Bisa ditampilkan lagi lewat bagian "Disembunyikan".')) return;
		if (verb === 'uninstall' && !confirm('Uninstall ' + label + ' dari server?\n\nPlugin dinonaktifkan lalu foldernya dihapus. Cadangan disimpan, jadi bisa dikembalikan dengan tombol "Kembalikan". Pengaturan dan data di database tidak dihapus.')) return;
		var btns = cp.querySelectorAll('button');
		for (var i = 0; i < btns.length; i++) btns[i].disabled = true;
		b.textContent = BUSY[verb] || 'Memproses…';
		var url = cp.querySelector('#cpManifestUrl'), tok = cp.querySelector('#cpToken');
		var body = new URLSearchParams();
		body.append('verb', verb);
		body.append('package', pkg);
		body.append('manifestUrl', url ? url.value : '');
		body.append('githubToken', tok ? tok.value : '');
		body.append('csrfToken', cp.getAttribute('data-csrf') || '');
		body.append('fullpage', cp.getAttribute('data-fullpage') || '');
		var pw = cp.querySelector('#cpSourcePass');
		if (verb === 'unlockSource') body.append('password', pw ? pw.value : '');
		var fail = function (m) { alert(m); for (var i = 0; i < btns.length; i++) btns[i].disabled = false; };
		fetch(cp.getAttribute('data-action'), {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
			body: body.toString()
		}).then(function (r) {
			if (!r.ok) throw new Error('Permintaan gagal (' + r.status + '). Periksa log error server.');
			return r.json();
		}).then(function (r) {
			if (!r || !r.status || !r.content) return fail((r && r.content) || 'Gagal memproses permintaan.');
			var t = document.createElement('div');
			t.innerHTML = r.content;
			var n = t.querySelector('#cheesaCP');
			if (n) cp.parentNode.replaceChild(n, cp); else fail('Jawaban server tidak dikenali.');
		}).catch(function (e) { fail(e.message || 'Permintaan gagal.'); });
	});
})();
{/literal}
</script>
</div>
