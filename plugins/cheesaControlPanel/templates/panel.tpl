{**
 * plugins/generic/cheesaControlPanel/templates/panel.tpl
 * Isi panel. Dipakai di modal "Buka Panel" dan di halaman penuh /cheesapanel.
 *}
<div id="cheesaCP" class="ccp{if $cpFullPage} ccp-full{/if}" data-action="{$cpAction|escape}" data-csrf="{csrf type="raw"}" data-fullpage="{if $cpFullPage}1{/if}">
{include file=$cpStyleTpl}

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
		<button type="button" class="ccp-btn" data-cp-verb="lockPanel" title="Kunci panel sekarang">🔒 Kunci</button>
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
			<button type="button" class="ccp-btn sec" data-cp-verb="lockPanel">🔒 Kunci panel</button>
		</p>
		<p class="ccp-help">Kosongkan alamat lalu Simpan untuk kembali ke alamat bawaan. Paket hanya dipasang bila tanda tangan digitalnya cocok. Versi lama dan plugin yang di-uninstall otomatis dicadangkan (3 terakhir per paket). Panel terkunci lagi otomatis setelah 30 menit tanpa aktivitas atau saat logout.</p>
	{else}
		<div class="ccp-field">
			<label for="cpSourcePass">Kata sandi</label>
			<div style="display:flex;gap:6px;max-width:460px">
				{* readonly sampai diklik + new-password: mencegah browser mengisi otomatis dengan password login OJS *}
				{* type=text + huruf disamarkan lewat CSS: browser tidak menganggapnya kolom login, jadi tidak mengisi otomatis / menawarkan simpan password *}
				<input type="text" id="cpSourcePass" class="ccp-mask" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" autocapitalize="off" autocorrect="off" spellcheck="false" value="" placeholder="Masukkan kata sandi">
				<button type="button" class="ccp-btn sec" id="cpSourceEye" title="Tampilkan / sembunyikan ketikan" onclick="document.getElementById('cpSourcePass').classList.toggle('ccp-mask');">👁</button>
			</div>
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

{include file=$cpScriptTpl}
</div>
