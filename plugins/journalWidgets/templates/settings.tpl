{**
 * plugins/generic/journalWidgets/templates/settings.tpl
 * Form pengaturan Journal Portal Widgets
 *}
<script>
	$(function() {ldelim}
		$('#journalWidgetsSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<style>
	#journalWidgetsSettings fieldset { margin: 0 0 18px; padding: 12px 16px 4px; border: 1px solid #ddd; border-radius: 4px; }
	#journalWidgetsSettings legend { padding: 0 6px; font-weight: 700; }
	#journalWidgetsSettings .jw-f { margin: 0 0 12px; }
	#journalWidgetsSettings .jw-f label { display: block; font-weight: 600; margin-bottom: 4px; }
	#journalWidgetsSettings .jw-f input[type=text], #journalWidgetsSettings .jw-f textarea, #journalWidgetsSettings .jw-f select { width: 100%; box-sizing: border-box; }
	#journalWidgetsSettings .jw-f textarea { min-height: 110px; font-family: monospace; }
	#journalWidgetsSettings .jw-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
	#journalWidgetsSettings .jw-help { margin: 3px 0 0; color: #666; font-size: 12px; }
	#journalWidgetsSettings .jw-intro { padding: 10px 12px; background: #f4f7fb; border-left: 4px solid #006798; margin-bottom: 16px; }
</style>

<form class="pkp_form" id="journalWidgetsSettings" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	<div class="jw-intro">
		Setelah disimpan, buka <b>Settings &gt; Website &gt; Appearance &gt; Sidebar Management</b> lalu pindahkan blok
		berawalan <b>"Widget:"</b> ke daftar Sidebar. URL boleh ditulis lengkap (https://…) atau path jurnal (misalnya <code>about/submissions</code>).
	</div>

	<fieldset><legend>Umum</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-primary">Warna utama</label><input type="text" id="jw-primary" name="primary" value="{$primary|escape}" placeholder="#030271"></div>
			<div class="jw-f"><label for="jw-accent">Warna aksen</label><input type="text" id="jw-accent" name="accent" value="{$accent|escape}" placeholder="#ed991f"></div>
			<div class="jw-f"><label for="jw-cache">Simpan data statistik (jam)</label><input type="text" id="jw-cache" name="cacheHours" value="{$cacheHours|escape}"><p class="jw-help">Keywords, Most Accessed, dan Visitors dihitung ulang setiap sekian jam (dan setiap ada artikel/terbitan diterbitkan). Published Volumes dan Author Diversity selalu realtime.</p></div>
		</div>
	</fieldset>

	<fieldset><legend>Quick Actions</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-sub">Tautan "Submit Your Paper"</label><input type="text" id="jw-sub" name="submitUrl" value="{$submitUrl|escape}"></div>
			<div class="jw-f"><label for="jw-rev">Tautan "Become a Reviewer"</label><input type="text" id="jw-rev" name="reviewerUrl" value="{$reviewerUrl|escape}"></div>
		</div>
		<div class="jw-f"><label for="jw-soc">Media sosial</label><textarea id="jw-soc" name="social">{$social|escape}</textarea>
			<p class="jw-help">Satu baris per akun: <code>Nama|URL</code>. Nama dikenali: Instagram, Whatsapp, Facebook, Twitter, X, Youtube, Linkedin, Telegram, Email, Website.</p></div>
	</fieldset>

	<fieldset><legend>Journal Menu</legend>
		<div class="jw-f"><label for="jw-menu">Daftar menu</label><textarea id="jw-menu" name="menuItems">{$menuItems|escape}</textarea>
			<p class="jw-help">Satu baris per menu: <code>ikon|Label|URL</code>. Ikon memakai nama Font Awesome 4, contoh <code>fa-bullseye</code>, <code>fa-balance-scale</code>, <code>fa-file-text</code>.</p></div>
	</fieldset>

	<fieldset><legend>Author Diversity · News · Keywords · Most Accessed · Volumes · Visitors</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-dvm">Diversity: jumlah baris</label><input type="text" id="jw-dvm" name="diversityMax" value="{$diversityMax|escape}"></div>
			<div class="jw-f"><label for="jw-nm">News: jumlah</label><input type="text" id="jw-nm" name="newsMax" value="{$newsMax|escape}"></div>
			<div class="jw-f"><label for="jw-km">Keywords: jumlah</label><input type="text" id="jw-km" name="keywordsMax" value="{$keywordsMax|escape}"></div>
			<div class="jw-f"><label for="jw-pm">Most Accessed: jumlah</label><input type="text" id="jw-pm" name="popularMax" value="{$popularMax|escape}"></div>
			<div class="jw-f"><label for="jw-pp">Most Accessed: periode</label>
				<select id="jw-pp" name="popularPeriod"><option value="all"{if $popularPeriod!="12"} selected{/if}>Sepanjang waktu</option><option value="12"{if $popularPeriod=="12"} selected{/if}>12 bulan terakhir</option></select></div>
			<div class="jw-f"><label for="jw-vy">Volumes: jumlah tahun</label><input type="text" id="jw-vy" name="volumesYears" value="{$volumesYears|escape}"></div>
			<div class="jw-f"><label for="jw-vm">Visitors: jumlah negara</label><input type="text" id="jw-vm" name="visitorsMax" value="{$visitorsMax|escape}"></div>
		</div>
		<div class="jw-f"><label for="jw-dmore">Diversity: tautan "View Details" (opsional)</label><input type="text" id="jw-dmore" name="diversityMore" value="{$diversityMore|escape}"></div>
		<p class="jw-help">Author Diversity memakai kolom <i>Country</i> penulis artikel yang sudah terbit. Visitors memakai statistik OJS per negara (perlu Geo Location aktif di plugin Usage Statistics).</p>
	</fieldset>

	<fieldset><legend>Editorial Board</legend>
		<div class="jw-f"><label for="jw-ed">Daftar dewan redaksi</label><textarea id="jw-ed" name="editorialList" style="min-height:160px">{$editorialList|escape}</textarea>
			<p class="jw-help">Baris diawali <code>#</code> = judul peran (mis. <code># Editor in Chief</code>). Baris lain: <code>Nama|Afiliasi|URL profil (opsional)</code>.</p></div>
		<div class="jw-f"><label for="jw-edm">Tautan "Read more"</label><input type="text" id="jw-edm" name="editorialMore" value="{$editorialMore|escape}"></div>
	</fieldset>

	<fieldset><legend>Google Scholar Citation (isi manual dari profil Google Scholar)</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-gsu">URL profil</label><input type="text" id="jw-gsu" name="scholarUrl" value="{$scholarUrl|escape}"></div>
			<div class="jw-f"><label for="jw-gss">Label kolom kedua</label><input type="text" id="jw-gss" name="scholarSince" value="{$scholarSince|escape}" placeholder="Since 2021"></div>
			<div class="jw-f"><label for="jw-gsd">Tanggal update</label><input type="text" id="jw-gsd" name="scholarUpdated" value="{$scholarUpdated|escape}"></div>
		</div>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-g1">Citations (All)</label><input type="text" id="jw-g1" name="scholarAll" value="{$scholarAll|escape}"></div>
			<div class="jw-f"><label for="jw-g2">Citations (Since)</label><input type="text" id="jw-g2" name="scholarAllSince" value="{$scholarAllSince|escape}"></div>
			<div class="jw-f"><label for="jw-g3">h-index (All)</label><input type="text" id="jw-g3" name="scholarH" value="{$scholarH|escape}"></div>
			<div class="jw-f"><label for="jw-g4">h-index (Since)</label><input type="text" id="jw-g4" name="scholarHSince" value="{$scholarHSince|escape}"></div>
			<div class="jw-f"><label for="jw-g5">i10-index (All)</label><input type="text" id="jw-g5" name="scholarI10" value="{$scholarI10|escape}"></div>
			<div class="jw-f"><label for="jw-g6">i10-index (Since)</label><input type="text" id="jw-g6" name="scholarI10Since" value="{$scholarI10Since|escape}"></div>
		</div>
		<div class="jw-f"><label for="jw-gy">Sitasi per tahun (grafik)</label><input type="text" id="jw-gy" name="scholarYears" value="{$scholarYears|escape}" placeholder="2021:5, 2022:15, 2023:20"></div>
	</fieldset>

	<fieldset><legend>Scopus Citation (isi manual dari halaman sumber jurnal di Scopus)</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-su">URL halaman sumber Scopus</label><input type="text" id="jw-su" name="scopusUrl" value="{$scopusUrl|escape}" placeholder="https://www.scopus.com/sourceid/..."></div>
			<div class="jw-f"><label for="jw-sd">Tanggal update</label><input type="text" id="jw-sd" name="scopusUpdated" value="{$scopusUpdated|escape}"></div>
		</div>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-s1">CiteScore</label><input type="text" id="jw-s1" name="scopusCiteScore" value="{$scopusCiteScore|escape}"></div>
			<div class="jw-f"><label for="jw-s2">Tahun CiteScore</label><input type="text" id="jw-s2" name="scopusCiteYear" value="{$scopusCiteYear|escape}" placeholder="2025"></div>
			<div class="jw-f"><label for="jw-s3">Kuartil</label><input type="text" id="jw-s3" name="scopusQuartile" value="{$scopusQuartile|escape}" placeholder="Q2"></div>
			<div class="jw-f"><label for="jw-s4">SJR</label><input type="text" id="jw-s4" name="scopusSjr" value="{$scopusSjr|escape}"></div>
			<div class="jw-f"><label for="jw-s5">SNIP</label><input type="text" id="jw-s5" name="scopusSnip" value="{$scopusSnip|escape}"></div>
			<div class="jw-f"><label for="jw-s6">Total sitasi</label><input type="text" id="jw-s6" name="scopusCitations" value="{$scopusCitations|escape}"></div>
			<div class="jw-f"><label for="jw-s7">Jumlah dokumen</label><input type="text" id="jw-s7" name="scopusDocs" value="{$scopusDocs|escape}"></div>
			<div class="jw-f"><label for="jw-s8">h-index</label><input type="text" id="jw-s8" name="scopusH" value="{$scopusH|escape}"></div>
		</div>
		<div class="jw-f"><label for="jw-sy">Sitasi per tahun (grafik)</label><input type="text" id="jw-sy" name="scopusYears" value="{$scopusYears|escape}" placeholder="2020:1, 2021:1, 2022:4, 2023:6, 2024:17"></div>
		<p class="jw-help">Isian yang dikosongkan tidak ditampilkan. Widget tidak tampil bila CiteScore, total sitasi, dan grafik kosong.</p>
	</fieldset>

	<fieldset><legend>Poster / Flyer</legend>
		<div class="jw-row">
			<div class="jw-f"><label for="jw-pt">Judul blok</label><input type="text" id="jw-pt" name="posterTitle" value="{$posterTitle|escape}" placeholder="Call for Papers"></div>
			<div class="jw-f"><label for="jw-pa">Teks alternatif gambar</label><input type="text" id="jw-pa" name="posterAlt" value="{$posterAlt|escape}"></div>
		</div>
		<div class="jw-f"><label for="jw-pi">URL gambar</label><input type="text" id="jw-pi" name="posterImg" value="{$posterImg|escape}"><p class="jw-help">Unggah gambar lewat Settings &gt; Website &gt; Setup / editor halaman, lalu salin alamatnya ke sini.</p></div>
		<div class="jw-f"><label for="jw-pu">Tautan saat gambar diklik (opsional)</label><input type="text" id="jw-pu" name="posterUrl" value="{$posterUrl|escape}"></div>
	</fieldset>

	{fbvFormButtons submitText="common.save"}
</form>
