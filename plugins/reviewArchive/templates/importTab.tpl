{**
 * plugins/generic/reviewArchive/templates/importTab.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * "Import XML Arsip Review" tab under Tools.
 *}
<style>
	.ra_import {ldelim} font-size: .875rem; line-height: 1.6; max-width: 60rem; {rdelim}
	.ra_import .ra_card {ldelim} background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.2); border-radius: 3px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; {rdelim}
	.ra_import h3 {ldelim} margin: 0 0 .5rem; font-size: 1rem; {rdelim}
	.ra_import ol.ra_steps {ldelim} margin: .25rem 0 0 1.25rem; padding: 0; {rdelim}
	.ra_import fieldset {ldelim} border: 0; padding: 0; margin: 1rem 0; {rdelim}
	.ra_import legend {ldelim} font-weight: 700; margin-bottom: .25rem; {rdelim}
	.ra_import label.ra_option {ldelim} display: block; margin: .35rem 0; {rdelim}
	.ra_import label.ra_option small {ldelim} display: block; margin-left: 1.5rem; color: #666; {rdelim}
	.ra_import .ra_progress {ldelim} height: .5rem; background: #eee; border-radius: 1rem; overflow: hidden; margin: .75rem 0 .25rem; {rdelim}
	.ra_import .ra_progress span {ldelim} display: block; height: 100%; width: 0; background: #006798; transition: width .2s; {rdelim}
	.ra_import .ra_status {ldelim} color: #555; min-height: 1.4em; {rdelim}
	.ra_import .ra_status.ra_error {ldelim} color: #d00a6c; font-weight: 700; {rdelim}
	.ra_import .ra_actions {ldelim} margin-top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; {rdelim}
	.ra_import button[disabled] {ldelim} opacity: .5; cursor: not-allowed; {rdelim}
	.ra-dv-group {ldelim} margin: .75rem 0; {rdelim}
	.ra-dv-gh {ldelim} margin: 0 0 .35rem; font-size: .95rem; color: #11213f; border-bottom: 1px solid #eee; padding-bottom: .25rem; {rdelim}
	.ra-dv-n {ldelim} color: #888; font-weight: 400; {rdelim}
	.ra-dv-files {ldelim} list-style: none; margin: 0; padding: 0; {rdelim}
	.ra-dv-file {ldelim} display: flex; align-items: center; gap: .6rem; padding: .35rem .25rem; border-bottom: 1px solid #f2f2f2; {rdelim}
	.ra-dv-name {ldelim} flex: 1; min-width: 0; overflow-wrap: anywhere; {rdelim}
	.ra-dv-size {ldelim} color: #888; font-size: .82rem; white-space: nowrap; {rdelim}
	.ra-dv-act {ldelim} display: flex; gap: .4rem; white-space: nowrap; {rdelim}
	.ra-dv-view, .ra-dv-dl {ldelim} font-size: .82rem; padding: 3px 11px; border-radius: 5px; border: 1px solid #cdd3e6; background: #fff; color: #11213f; cursor: pointer; text-decoration: none; {rdelim}
	.ra-dv-view:hover, .ra-dv-dl:hover {ldelim} border-color: #006798; color: #006798; {rdelim}
	.ra-dv-sum {ldelim} color: #555; margin-bottom: .5rem; {rdelim}
	.ra-modal {ldelim} position: fixed; inset: 0; background: rgba(10,16,40,.6); display: none; align-items: center; justify-content: center; z-index: 99999; {rdelim}
	.ra-modal.on {ldelim} display: flex; {rdelim}
	.ra-modal-box {ldelim} background: #fff; width: min(900px, 94vw); height: 86vh; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; {rdelim}
	.ra-modal-head {ldelim} display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-bottom: 1px solid #eee; {rdelim}
	.ra-modal-title {ldelim} flex: 1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; {rdelim}
	.ra-modal-x {ldelim} border: 0; background: #eee; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; font-size: 18px; line-height: 1; {rdelim}
	.ra-modal iframe {ldelim} flex: 1; width: 100%; border: 0; {rdelim}
</style>

<div class="ra_import" id="raImport">
	<div class="ra_card">
		<h3>{translate key="plugins.generic.reviewArchive.import.title"}</h3>
		<p>{translate key="plugins.generic.reviewArchive.import.intro"}</p>
		<ol class="ra_steps">
			<li>{translate key="plugins.generic.reviewArchive.import.step.upload"}</li>
			<li>{translate key="plugins.generic.reviewArchive.import.step.check"}</li>
			<li>{translate key="plugins.generic.reviewArchive.import.step.preview"}</li>
			<li>{translate key="plugins.generic.reviewArchive.import.step.import"}</li>
		</ol>

		<fieldset>
			<legend>{translate key="plugins.generic.reviewArchive.import.file"}</legend>
			<input type="file" id="raFile" accept=".xml,text/xml,application/xml" multiple>
			<small style="display:block;color:#666;margin-top:.35rem;">Bisa pilih beberapa berkas XML artikel sekaligus. Untuk satu berkas, pakai "Upload &amp; check" (dengan pratinjau); untuk banyak berkas, pakai "Unggah &amp; impor semua".</small>
		</fieldset>

		<fieldset>
			<legend>{translate key="plugins.generic.reviewArchive.import.options"}</legend>
			<label class="ra_option"><input type="checkbox" name="labelReview" value="1" checked> {translate key="plugins.generic.reviewArchive.import.option.label"}
				<small>{translate key="plugins.generic.reviewArchive.import.option.label.help"}</small></label>
			<label class="ra_option"><input type="checkbox" name="dedupe" value="1" checked> {translate key="plugins.generic.reviewArchive.import.option.dedupe"}
				<small>{translate key="plugins.generic.reviewArchive.import.option.dedupe.help"}</small></label>
			<label class="ra_option"><input type="checkbox" name="dropProduction" value="1"> {translate key="plugins.generic.reviewArchive.import.option.production"}
				<small>{translate key="plugins.generic.reviewArchive.import.option.production.help"}</small></label>
			<label class="ra_option"><input type="checkbox" name="dropOldRevisions" value="1"> {translate key="plugins.generic.reviewArchive.import.option.revisions"}
				<small>{translate key="plugins.generic.reviewArchive.import.option.revisions.help"}</small></label>
			<label class="ra_option"><input type="checkbox" name="deepScan" value="1"> Pindai isi berkas (PDF) lebih dalam
				<small>Default mati = cepat &amp; ringan: metadata/judul/abstrak/URL tetap dipindai penuh, hanya isi berkas yang dilewati. Aktifkan hanya jika server sedang sepi (mis. malam hari) karena jauh lebih berat.</small></label>
		</fieldset>

		<div class="ra_progress" aria-hidden="true"><span id="raBar"></span></div>
		<div class="ra_status" id="raStatus" role="status"></div>

		<div class="ra_actions">
			<button type="button" class="pkpButton" id="raAnalyze">{translate key="plugins.generic.reviewArchive.import.analyze"}</button>
			<button type="button" class="pkpButton" id="raImportButton" disabled>{translate key="plugins.generic.reviewArchive.import.run"}</button>
			<button type="button" class="pkpButton" id="raBatchButton">Unggah &amp; cek semua</button>
		</div>
	</div>

	<div id="raResult"></div>

	<div class="ra_card">
		<h3>Sumber Google Drive</h3>
		<p>Tempel tautan <b>folder</b> Google Drive (di-share "Anyone with the link"). Berkas dibaca &amp; dikelompokkan per subfolder, lalu bisa <b>Lihat</b> (pratinjau popup) dan <b>Unduh</b>.</p>
		{if !$raHasDriveKey}<div class="ra_status ra_error">Google API Key belum diisi — buka pengaturan plugin Review Archive dulu untuk mengisinya.</div>{/if}
		<fieldset>
			<input type="url" id="raDriveFolder" placeholder="https://drive.google.com/drive/folders/..." style="width:100%;box-sizing:border-box;padding:7px 9px;" {if !$raHasDriveKey}disabled{/if}>
		</fieldset>
		<div class="ra_actions">
			<button type="button" class="pkpButton" id="raDriveBtn" {if !$raHasDriveKey}disabled{/if}>Baca folder</button>
		</div>
		<div class="ra_status" id="raDriveStatus" role="status"></div>
		<div id="raDriveResult"></div>
	</div>
</div>

<div class="ra-modal" id="raModal" aria-hidden="true">
	<div class="ra-modal-box">
		<div class="ra-modal-head">
			<span class="ra-modal-title" id="raModalTitle">Pratinjau</span>
			<a class="ra-dv-dl" id="raModalDl" target="_blank" rel="noopener">Unduh</a>
			<button type="button" class="ra-modal-x" id="raModalX" aria-label="Tutup">&times;</button>
		</div>
		<iframe id="raModalFrame" title="Pratinjau berkas" src="about:blank"></iframe>
	</div>
</div>

<script>
(function($) {ldelim}
	var cfg = {ldelim}
		uploadUrl: {$raUploadUrl|json_encode},
		analyzeUrl: {$raAnalyzeUrl|json_encode},
		importUrl: {$raImportUrl|json_encode},
		csrfToken: {$raCsrfToken|json_encode},
		driveUrl: {$raDriveUrl|json_encode},
		chunkSize: {$raChunkSize},
		maxSize: {$raMaxSize},
		text: {ldelim}
			chooseFile: {translate|json_encode key="plugins.generic.reviewArchive.import.status.chooseFile"},
			tooLarge: {translate|json_encode key="plugins.generic.reviewArchive.import.error.tooLarge"},
			uploading: {translate|json_encode key="plugins.generic.reviewArchive.import.status.uploading"},
			analyzing: {translate|json_encode key="plugins.generic.reviewArchive.import.status.analyzing"},
			analyzed: {translate|json_encode key="plugins.generic.reviewArchive.import.status.analyzed"},
			blocked: {translate|json_encode key="plugins.generic.reviewArchive.import.status.blocked"},
			importing: {translate|json_encode key="plugins.generic.reviewArchive.import.status.importing"},
			done: {translate|json_encode key="plugins.generic.reviewArchive.import.status.done"},
			failed: {translate|json_encode key="plugins.generic.reviewArchive.import.status.failed"}
		{rdelim}
	{rdelim};

	var $root = $('#raImport'), $status = $('#raStatus'), $bar = $('#raBar'),
		$analyze = $('#raAnalyze'), $import = $('#raImportButton'), $batch = $('#raBatchButton'),
		$result = $('#raResult'),
		uploadId = null, uploadedFor = null;

	function status(msg, isError) {ldelim}
		$status.text(msg || '').toggleClass('ra_error', !!isError);
	{rdelim}
	function progress(fraction) {ldelim}
		$bar.css('width', Math.round(Math.max(0, Math.min(1, fraction)) * 100) + '%');
	{rdelim}
	function newUploadId() {ldelim}
		var bytes = new Uint8Array(16), hex = '';
		(window.crypto || window.msCrypto).getRandomValues(bytes);
		for (var i = 0; i < bytes.length; i++) hex += ('0' + bytes[i].toString(16)).slice(-2);
		return hex;
	{rdelim}
	function options(id) {ldelim}
		var data = {ldelim} csrfToken: cfg.csrfToken, uploadId: id || uploadId {rdelim};
		$root.find('input[type=checkbox]').each(function() {ldelim}
			data[this.name] = this.checked ? 1 : 0;
		{rdelim});
		return data;
	{rdelim}
	function fileKey(file) {ldelim}
		return file.name + '|' + file.size + '|' + file.lastModified;
	{rdelim}

	function upload(file) {ldelim}
		// File <= directLimit diunggah sekali (tanpa dipecah); file besar dipecah per chunkSize.
		var chunkSize = (file.size <= cfg.directLimit) ? Math.max(1, file.size) : cfg.chunkSize;
		var total = Math.max(1, Math.ceil(file.size / chunkSize)), id = newUploadId();
		var deferred = $.Deferred();
		function send(index, attempt) {ldelim}
			var form = new FormData();
			form.append('csrfToken', cfg.csrfToken);
			form.append('uploadId', id);
			form.append('index', index);
			form.append('total', total);
			form.append('size', file.size);
			form.append('name', file.name);
			form.append('chunk', file.slice(index * chunkSize, (index + 1) * chunkSize), 'chunk');
			$.ajax({ldelim} url: cfg.uploadUrl, type: 'POST', data: form, processData: false, contentType: false, dataType: 'json' {rdelim})
				.done(function(r) {ldelim}
					if (!r || !r.status) {ldelim} deferred.reject(r && r.content); return; {rdelim}
					progress((index + 1) / total);
					status(cfg.text.uploading.replace('{ldelim}$done{rdelim}', index + 1).replace('{ldelim}$total{rdelim}', total));
					if (index + 1 < total) send(index + 1, 0); else deferred.resolve(id);
				{rdelim})
				.fail(function() {ldelim}
					if (attempt < 2) setTimeout(function() {ldelim} send(index, attempt + 1); {rdelim}, 1500);
					else deferred.reject();
				{rdelim});
		{rdelim}
		progress(0);
		send(0, 0);
		return deferred.promise();
	{rdelim}

	function post(url, busyText) {ldelim}
		status(busyText);
		$analyze.prop('disabled', true);
		$import.prop('disabled', true);
		return $.ajax({ldelim} url: url, type: 'POST', data: options(), dataType: 'json' {rdelim})
			.always(function() {ldelim} $analyze.prop('disabled', false); {rdelim});
	{rdelim}

	$analyze.on('click', function() {ldelim}
		var file = $('#raFile')[0].files[0];
		if (!file) {ldelim} status(cfg.text.chooseFile, true); return; {rdelim}
		if (file.size > cfg.maxSize) {ldelim} status(cfg.text.tooLarge, true); return; {rdelim}
		$result.empty();
		$import.prop('disabled', true);
		var ready = (uploadId && uploadedFor === fileKey(file)) ? $.Deferred().resolve(uploadId).promise() : upload(file);
		$analyze.prop('disabled', true);
		ready.done(function(id) {ldelim}
			uploadId = id;
			uploadedFor = fileKey(file);
			post(cfg.analyzeUrl, cfg.text.analyzing).done(function(r) {ldelim}
				if (!r || !r.status) {ldelim} status((r && r.content) || cfg.text.failed, true); return; {rdelim}
				$result.html(r.content);
				if (r.blocked) {ldelim}
					uploadId = null; uploadedFor = null;
					status(cfg.text.blocked, true);
				{rdelim} else {ldelim}
					status(cfg.text.analyzed);
					$import.prop('disabled', false);
				{rdelim}
			{rdelim}).fail(function() {ldelim} status(cfg.text.failed, true); {rdelim});
		{rdelim}).fail(function(msg) {ldelim}
			uploadId = null; uploadedFor = null;
			$analyze.prop('disabled', false);
			status(msg || cfg.text.failed, true);
		{rdelim});
	{rdelim});

	// Options changed after the preview: preview again before importing.
	$root.on('change', 'input[type=checkbox]', function() {ldelim}
		if (!$import.prop('disabled')) {ldelim}
			$import.prop('disabled', true);
			$result.empty();
			status('');
		{rdelim}
	{rdelim});
	$('#raFile').on('change', function() {ldelim}
		$import.prop('disabled', true);
		$result.empty();
		status('');
		progress(0);
	{rdelim});

	$import.on('click', function() {ldelim}
		if (!uploadId) return;
		post(cfg.importUrl, cfg.text.importing).done(function(r) {ldelim}
			if (!r || !r.status) {ldelim} status((r && r.content) || cfg.text.failed, true); return; {rdelim}
			$result.html(r.content);
			status(cfg.text.done);
			uploadId = null; uploadedFor = null;
		{rdelim}).fail(function() {ldelim} status(cfg.text.failed, true); {rdelim});
	{rdelim});
{literal}
	// ---- Mode banyak berkas: dua tahap (unggah+cek lalu impor per artikel / semua) ----
	function esc(s){ return $('<span>').text(s==null?'':s).html(); }
	var batchFiles = [];
	function renderBatchShell(){
		$result.html(
			'<div class="ra_card">'
			+ '<h3>Impor banyak berkas</h3>'
			+ '<div id="raBatchBar" class="ra_status" style="margin-bottom:.5rem;"></div>'
			+ '<div id="raBatchList"></div>'
			+ '<div id="raBatchFoot" style="margin-top:1rem;display:none;">'
			+   '<button type="button" class="pkpButton" id="raImportAll">Impor semua yang lolos</button>'
			+ '</div></div>'
		);
		$('#raImportAll').on('click', importAll);
	}
	function cardEl(idx){ return $('#rafc_'+idx); }
	function setCard(idx, state, name, msg, preview){
		var color = state==='imported' ? '#107c10' : (state==='blocked' ? '#b45309' : (state==='failed' ? '#d00a6c' : '#555'));
		var label = ({ checking:'memeriksa…', checked:'siap diimpor', importing:'mengimpor…', imported:'✓ diimpor', blocked:'⚠ diblokir (judol)', failed:'✗ gagal' })[state] || state;
		var $c = cardEl(idx);
		if(!$c.length){ $c = $('<div class="ra_fcard" id="rafc_'+idx+'" style="border:1px solid #e3e3e3;border-radius:4px;padding:.6rem .85rem;margin:.5rem 0;"></div>').appendTo('#raBatchList'); }
		var btn = (state==='checked' || state==='failed') ? '<button type="button" class="pkpButton ra_fimport" data-idx="'+idx+'" style="margin-top:.5rem;">Impor artikel ini</button>' : '';
		$c.html('<div><b>'+esc(name)+'</b> — <span style="color:'+color+'">'+label+(msg?' ('+esc(msg)+')':'')+'</span></div>' + (preview ? '<div style="margin-top:.4rem;">'+preview+'</div>' : '') + btn);
		$c.find('.ra_fimport').on('click', function(){ importOne(parseInt($(this).attr('data-idx'),10)); });
	}
	function batchCheckAll(fileArr){
		batchFiles = fileArr.map(function(f){ return { file:f, id:null, state:'pending' }; });
		$analyze.prop('disabled', true); $import.prop('disabled', true); $batch.prop('disabled', true);
		renderBatchShell();
		var i=0;
		function next(){
			if(i>=batchFiles.length){ afterCheck(); return; }
			var item = batchFiles[i], file = item.file;
			progress(i/batchFiles.length);
			$('#raBatchBar').text('Memeriksa '+(i+1)+'/'+batchFiles.length+': '+file.name);
			setCard(i,'checking',file.name);
			if(file.size>cfg.maxSize){ item.state='failed'; setCard(i,'failed',file.name,'terlalu besar'); i++; next(); return; }
			upload(file).done(function(id){
				item.id=id;
				$.ajax({ url:cfg.analyzeUrl, type:'POST', data:options(id), dataType:'json' }).done(function(r){
					if(!r||!r.status){ var m=(r&&r.content)?$('<div>').html(r.content).text().replace(/\s+/g,' ').trim().slice(0,220):''; item.state='failed'; setCard(i,'failed',file.name,m||'cek gagal'); i++; next(); return; }
					if(r.blocked){ item.state='blocked'; setCard(i,'blocked',file.name,'',r.content); i++; next(); return; }
					item.state='checked'; setCard(i,'checked',file.name,'',r.content); i++; next();
				}).fail(function(){ item.state='failed'; setCard(i,'failed',file.name,'cek timeout/galat'); i++; next(); });
			}).fail(function(){ item.state='failed'; setCard(i,'failed',file.name,'unggah gagal'); i++; next(); });
		}
		next();
	}
	function afterCheck(){
		progress(1);
		var ready=0, blocked=0, failed=0;
		batchFiles.forEach(function(x){ if(x.state==='checked')ready++; else if(x.state==='blocked')blocked++; else if(x.state==='failed')failed++; });
		$('#raBatchBar').text('Cek selesai: '+ready+' siap, '+blocked+' diblokir, '+failed+' gagal. Impor per artikel, atau semua sekaligus.');
		$batch.prop('disabled', false); $analyze.prop('disabled', false);
		if(ready>0) $('#raBatchFoot').show();
	}
	function importOne(idx){
		var item = batchFiles[idx];
		if(!item || !item.id || item.state==='imported' || item.state==='importing') return;
		setCard(idx,'importing',item.file.name);
		return $.ajax({ url:cfg.importUrl, type:'POST', data:options(item.id), dataType:'json' }).done(function(ri){
			if(ri&&ri.status){ item.state='imported'; setCard(idx,'imported',item.file.name,'',ri.content); }
			else { var m=(ri&&ri.content)?$('<div>').html(ri.content).text().replace(/\s+/g,' ').trim().slice(0,220):''; item.state='failed'; setCard(idx,'failed',item.file.name,m); }
		}).fail(function(){ item.state='failed'; setCard(idx,'failed',item.file.name,'impor timeout/galat'); });
	}
	function importAll(){
		$('#raImportAll').prop('disabled', true);
		var idxs=[]; batchFiles.forEach(function(x,i){ if(x.state==='checked') idxs.push(i); });
		var k=0;
		function go(){
			if(k>=idxs.length){ $('#raImportAll').prop('disabled', false); $('#raBatchBar').text('Selesai impor semua.'); return; }
			var p = importOne(idxs[k]); k++;
			if(p && p.always) p.always(go); else go();
		}
		go();
	}
	$batch.on('click', function(){
		var files = $('#raFile')[0].files;
		if(!files || !files.length){ status(cfg.text.chooseFile, true); return; }
		batchCheckAll(Array.prototype.slice.call(files));
	});

	// ---- Sumber Google Drive: baca folder -> daftar terkelompok -> preview popup + unduh ----
	var $driveBtn = $('#raDriveBtn'), $driveRes = $('#raDriveResult'), $driveStat = $('#raDriveStatus');
	function bindDriveActions(){
		$driveRes.find('.ra-dv-view').off('click').on('click', function(){
			var fid = $(this).attr('data-fid');
			var name = $(this).closest('.ra-dv-file').find('.ra-dv-name').text();
			$('#raModalTitle').text(name || 'Pratinjau');
			$('#raModalDl').attr('href', 'https://drive.google.com/uc?export=download&id=' + encodeURIComponent(fid));
			$('#raModalFrame').attr('src', 'https://drive.google.com/file/d/' + encodeURIComponent(fid) + '/preview');
			$('#raModal').addClass('on').attr('aria-hidden', 'false');
		});
	}
	function closeDriveModal(){ $('#raModal').removeClass('on').attr('aria-hidden', 'true'); $('#raModalFrame').attr('src', 'about:blank'); }
	$('#raModalX').on('click', closeDriveModal);
	$('#raModal').on('click', function(e){ if (e.target === this) closeDriveModal(); });
	$(document).on('keydown', function(e){ if (e.key === 'Escape') closeDriveModal(); });
	$driveBtn.on('click', function(){
		var folder = ($('#raDriveFolder').val() || '').trim();
		if (!folder){ $driveStat.text('Tempel dulu tautan folder Google Drive.'); return; }
		$driveBtn.prop('disabled', true); $driveStat.text('Membaca folder…'); $driveRes.html('');
		$.ajax({ url: cfg.driveUrl, type: 'POST', data: { csrfToken: cfg.csrfToken, folder: folder }, dataType: 'json' }).done(function(res){
			$driveBtn.prop('disabled', false);
			if (!res || res.status === false){ $driveStat.html('<span style="color:#d00a6c">' + (res && res.content ? res.content : 'Gagal membaca folder.') + '</span>'); return; }
			$driveStat.text(''); $driveRes.html(res.content); bindDriveActions();
		}).fail(function(){ $driveBtn.prop('disabled', false); $driveStat.html('<span style="color:#d00a6c">Gagal menghubungi server.</span>'); });
	});
	$('#raDriveFolder').on('keydown', function(e){ if (e.key === 'Enter'){ e.preventDefault(); $driveBtn.click(); } });
{/literal}
{rdelim}(jQuery));
</script>
