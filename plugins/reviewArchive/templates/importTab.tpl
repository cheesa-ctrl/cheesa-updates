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
			<input type="file" id="raFile" accept=".xml,text/xml,application/xml">
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
		</fieldset>

		<div class="ra_progress" aria-hidden="true"><span id="raBar"></span></div>
		<div class="ra_status" id="raStatus" role="status"></div>

		<div class="ra_actions">
			<button type="button" class="pkpButton" id="raAnalyze">{translate key="plugins.generic.reviewArchive.import.analyze"}</button>
			<button type="button" class="pkpButton" id="raImportButton" disabled>{translate key="plugins.generic.reviewArchive.import.run"}</button>
		</div>
	</div>

	<div id="raResult"></div>
</div>

<script>
(function($) {ldelim}
	var cfg = {ldelim}
		uploadUrl: {$raUploadUrl|json_encode},
		analyzeUrl: {$raAnalyzeUrl|json_encode},
		importUrl: {$raImportUrl|json_encode},
		csrfToken: {$raCsrfToken|json_encode},
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
		$analyze = $('#raAnalyze'), $import = $('#raImportButton'), $result = $('#raResult'),
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
	function options() {ldelim}
		var data = {ldelim} csrfToken: cfg.csrfToken, uploadId: uploadId {rdelim};
		$root.find('input[type=checkbox]').each(function() {ldelim}
			data[this.name] = this.checked ? 1 : 0;
		{rdelim});
		return data;
	{rdelim}
	function fileKey(file) {ldelim}
		return file.name + '|' + file.size + '|' + file.lastModified;
	{rdelim}

	function upload(file) {ldelim}
		var total = Math.max(1, Math.ceil(file.size / cfg.chunkSize)), id = newUploadId();
		var deferred = $.Deferred();
		function send(index, attempt) {ldelim}
			var form = new FormData();
			form.append('csrfToken', cfg.csrfToken);
			form.append('uploadId', id);
			form.append('index', index);
			form.append('total', total);
			form.append('size', file.size);
			form.append('name', file.name);
			form.append('chunk', file.slice(index * cfg.chunkSize, (index + 1) * cfg.chunkSize), 'chunk');
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
{rdelim}(jQuery));
</script>
