{**
 * plugins/generic/cheesaMailer/templates/page.tpl
 * Halaman penuh CHEESA Mailer (/index.php/<jurnal>/cheesamail).
 *}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Email Massal — CHEESA</title>
{literal}
<style>
	:root { --navy:#030271; --navy-2:#1b1a8f; --accent:#ed991f; --ok:#1f8a4c; --red:#c62828; --line:#e4e7f0; --soft:#f5f6fb; --muted:#5e6580; }
	* { box-sizing: border-box; }
	html, body { margin: 0; }
	body { min-height: 100vh; background: #eef0f7; font: 14px/1.55 "Noto Sans", Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2140; }
	a { color: var(--navy); }
	.top { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 10px 24px; background: #fff; border-bottom: 1px solid #e1e4ef; box-shadow: 0 2px 10px rgba(3,2,113,.05); }
	.crumb { display: flex; align-items: center; gap: 10px; font-weight: 600; min-width: 0; }
	.crumb .dot { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--navy); color: #fff; font-weight: 800; }
	.crumb .j { color: var(--muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; }
	.nav { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
	.nav a, .nav button { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid transparent; background: none; cursor: pointer; color: var(--navy); }
	.nav a:hover, .nav button:hover { background: #f1f2fb; border-color: #dfe2f2; }
	.wrap { max-width: 1040px; margin: 0 auto; padding: 24px 20px 60px; }
	.card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px 24px; margin-bottom: 18px; box-shadow: 0 6px 18px -12px rgba(3,2,113,.25); }
	h1 { font-size: 20px; margin: 0 0 4px; color: var(--navy); }
	.sub { color: var(--muted); margin: 0 0 14px; font-size: 13px; }
	label { display: block; font-weight: 600; margin: 14px 0 6px; }
	label small { font-weight: 400; color: var(--muted); }
	input[type=text], input[type=email], textarea, select { width: 100%; padding: 10px 12px; border: 1px solid #cdd3e6; border-radius: 9px; font: inherit; color: inherit; background: #fff; }
	textarea { resize: vertical; }
	#cmBody { min-height: 230px; font-family: "SF Mono", ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
	#cmTo { min-height: 110px; font-family: "SF Mono", ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
	.row { display: flex; gap: 16px; flex-wrap: wrap; }
	.row > * { flex: 1; min-width: 240px; }
	.btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 9px; border: 1px solid var(--navy); background: var(--navy); color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
	.btn:hover { background: var(--navy-2); }
	.btn.ghost { background: #fff; color: var(--navy); }
	.btn.ghost:hover { background: #f1f2fb; }
	.btn.warn { background: var(--accent); border-color: var(--accent); }
	.btn:disabled { opacity: .5; cursor: not-allowed; }
	.chips { display: flex; gap: 8px; flex-wrap: wrap; margin: 2px 0 4px; }
	.chip { padding: 7px 14px; border-radius: 999px; border: 1px solid #cdd3e6; background: #f7f8fd; font-weight: 600; font-size: 13px; cursor: pointer; }
	.chip:hover { border-color: var(--accent); color: var(--navy); }
	.fmt { display: inline-flex; gap: 0; border: 1px solid #cdd3e6; border-radius: 9px; overflow: hidden; }
	.fmt label { margin: 0; }
	.fmt label span { display: block; padding: 8px 16px; cursor: pointer; font-weight: 600; }
	.fmt input { position: absolute; opacity: 0; pointer-events: none; }
	.fmt input:checked + span { background: var(--navy); color: #fff; }
	.hint { color: var(--muted); font-size: 12.5px; margin-top: 6px; }
	.note { padding: 10px 14px; border-radius: 9px; font-size: 13px; margin-top: 12px; }
	.note.warnbox { background: #fff6e8; border: 1px solid #f3d9a8; color: #7a5a17; }
	.note.err { background: #fdeceb; border: 1px solid #f3c3bf; color: var(--red); }
	.note.ok { background: #e9f6ee; border: 1px solid #bfe4cc; color: var(--ok); }
	.bar { height: 14px; border-radius: 999px; background: #e7eaf5; overflow: hidden; margin: 10px 0 6px; }
	.bar > div { height: 100%; width: 0; background: linear-gradient(90deg, var(--navy), var(--accent)); transition: width .3s; }
	.actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 18px; }
	.meta { color: var(--muted); font-size: 12.5px; }
	.log { font-size: 12.5px; }
	.log .li { padding: 8px 0; border-bottom: 1px solid #eef0f6; }
	.log .li b { color: var(--navy); }
	.lockwrap { max-width: 420px; margin: 8vh auto 0; }
	.lockwrap .card { text-align: center; }
	.lockwrap input { text-align: center; letter-spacing: 1px; }
</style>
{/literal}
</head>
<body>

<div class="top">
	<div class="crumb"><span class="dot">C</span> CHEESA Mailer <span class="j">· {$cmName|escape}</span></div>
	<div class="nav">
		<a href="{$cmDash|escape}">← Dasbor</a>
		<a href="{$cmPluginsUrl|escape}">Plugins</a>
		{if !$cmLocked}<a href="{$cmLockUrl|escape}">🔒 Kunci</a>{/if}
	</div>
</div>

{if $cmLocked}
	<div class="lockwrap"><div class="card">
		<h1>Halaman terkunci</h1>
		<p class="sub">Masukkan kata sandi untuk membuka Email Massal.</p>
		{if $cmLockErr}<div class="note err">{$cmLockErr|escape}</div>{/if}
		<form method="post" action="{$cmUnlockUrl|escape}" id="lockForm" style="margin-top:14px;">
			{csrf}
			<input type="password" id="pw" name="pw" placeholder="Kata sandi" autocomplete="off" autofocus>
			<input type="hidden" name="mlk" id="mlk">
			<div style="margin-top:14px;"><button type="submit" class="btn" style="width:100%;">Buka</button></div>
		</form>
	</div></div>
	{literal}<script>
	document.getElementById('lockForm').addEventListener('submit', function(){
		var pw = document.getElementById('pw');
		document.getElementById('mlk').value = btoa(unescape(encodeURIComponent(pw.value)));
		pw.removeAttribute('name'); // jangan kirim parameter mentah
	});
	</script>{/literal}
{else}

<div class="wrap">

	<div class="note warnbox">
		⚠ <b>Kirim dengan bijak.</b> Kirim hanya ke orang yang relevan. Mengirim ke daftar besar yang tidak terkait bisa membuat server email jurnal masuk daftar spam.
		Batas: maksimal <b>{$cmMax}</b> penerima per kirim, <b>{$cmDailyCap}</b> email/hari. Terkirim hari ini: <b id="cmToday">{$cmSentToday}</b>.
	</div>

	<div class="card">
		<h1>Tulis pesan</h1>
		<p class="sub">Pengirim: <b>{$cmFromName|escape} &lt;{$cmFromEmail|escape}&gt;</b> (kontak jurnal). Balasan penerima masuk ke alamat ini.</p>

		<label>Mode kirim</label>
		<div class="fmt" id="cmMode">
			<label><input type="radio" name="mode" value="blast" checked><span>Daftar email (blast)</span></label>
			<label><input type="radio" name="mode" value="personal"><span>Undangan personal</span></label>
		</div>
		<div class="hint">Blast: satu isi yang sama ke semua penerima. Personal: tiap orang menerima email yang menyebut <b>nama</b> & <b>makalah</b> mereka (pakai <code>{ldelim}name{rdelim}</code> dan <code>{ldelim}paper{rdelim}</code>).</div>

		<label>Template siap pakai</label>
		<div class="chips">
			{foreach $cmTemplates key=k item=t}<span class="chip" data-tpl="{$k|escape}">{$t.label|escape}</span>{/foreach}
			<span class="chip" data-tpl="_clear">Kosongkan</span>
		</div>

		<label for="cmSubject">Subjek <i>*</i></label>
		<input type="text" id="cmSubject" maxlength="200" placeholder="mis. Call for Papers — CHEESA">

		<div class="row">
			<div>
				<label>Format</label>
				<div class="fmt">
					<label><input type="radio" name="fmt" value="html" checked><span>HTML berheader CHEESA</span></label>
					<label><input type="radio" name="fmt" value="plain"><span>Teks biasa</span></label>
				</div>
				<div class="hint">HTML: isi di bawah ditata rapi dengan header/footer CHEESA & tautan otomatis. Teks: dikirim apa adanya.</div>
			</div>
			<div>
				<label for="cmFromName">Nama pengirim <small>(opsional)</small></label>
				<input type="text" id="cmFromName" value="{$cmFromName|escape}" placeholder="mis. Editorial Team CHEESA">
			</div>
		</div>

		<label for="cmBody">Isi email <i>*</i></label>
		<textarea id="cmBody" placeholder="Tulis isi email di sini. Gunakan {DEADLINE} dsb. lalu ganti sesuai kebutuhan."></textarea>
		<div class="hint">Footer "cara berhenti berlangganan" otomatis ditambahkan demi kepatuhan anti-spam.</div>
	</div>

	<div class="card">
		<h1>Daftar penerima</h1>
		<p class="sub" id="cmToHelp">Tempel alamat email, dipisah baris baru, koma, titik koma, atau spasi. Duplikat & yang tidak valid otomatis dibuang.</p>
		<textarea id="cmTo" placeholder="budi@example.ac.id&#10;siti@univ.ac.id, andi@inst.go.id"></textarea>
		<div class="hint" id="cmCount">0 alamat terdeteksi.</div>

		<div id="cmResult"></div>

		<div id="cmProgressWrap" style="display:none;margin-top:14px;">
			<div class="bar"><div id="cmBar"></div></div>
			<div class="meta" id="cmProgText">Menyiapkan…</div>
		</div>

		<div class="actions">
			<button type="button" class="btn warn ghost" id="cmTestBtn">✉ Uji kirim ke email saya</button>
			<button type="button" class="btn" id="cmSendBtn">🚀 Kirim ke semua</button>
			<span class="meta">Email saya: {$cmMyEmail|escape}</span>
		</div>
	</div>

	{if $cmLog}
	<div class="card">
		<h1 style="font-size:16px;">Catatan pengiriman terakhir</h1>
		<div class="log">
			{foreach $cmLog as $l}
				<div class="li"><b>{$l.time|escape}</b> · {$l.by|escape} · {$l.detail|escape}</div>
			{/foreach}
		</div>
	</div>
	{/if}

</div>

<form id="csrfHolder" style="display:none">{csrf}</form>
<script>
var CM_CFG = {
	start: "{$cmStartUrl|escape:'javascript'}",
	batch: "{$cmBatchUrl|escape:'javascript'}",
	test:  "{$cmTestUrl|escape:'javascript'}",
	tpl: {ldelim}{foreach $cmTemplates key=k item=t name=tl}"{$k|escape:'javascript'}": {ldelim}subject:"{$t.subject|escape:'javascript'}", body:"{$t.body|escape:'javascript'}"{rdelim}{if !$smarty.foreach.tl.last},{/if}{/foreach}{rdelim}
};
</script>
{literal}
<script>
(function(){
	var CSRF = document.querySelector('#csrfHolder input[name=csrfToken]').value;
	var START=CM_CFG.start, BATCH=CM_CFG.batch, TEST=CM_CFG.test, TPL=CM_CFG.tpl;

	var $ = function(id){ return document.getElementById(id); };
	var subject=$('cmSubject'), body=$('cmBody'), to=$('cmTo'), fromName=$('cmFromName');
	var sendBtn=$('cmSendBtn'), testBtn=$('cmTestBtn'), result=$('cmResult');
	var progWrap=$('cmProgressWrap'), bar=$('cmBar'), progText=$('cmProgText'), today=$('cmToday');

	function fmt(){ var r=document.querySelector('input[name=fmt]:checked'); return r?r.value:'html'; }
	function show(cls,msg){ result.innerHTML = '<div class="note '+cls+'">'+msg+'</div>'; }

	function modeVal(){ var r=document.querySelector('input[name=mode]:checked'); return r?r.value:'blast'; }
	function applyMode(){
		var personal = modeVal()==='personal';
		$('cmToHelp').innerHTML = personal
			? 'Satu penerima per baris: <b>email, Nama, Judul makalah</b>. Bisa salin langsung dari Excel (kolom Email, Nama, Judul). Yang tidak valid/duplikat dibuang.'
			: 'Tempel alamat email, dipisah baris baru, koma, titik koma, atau spasi. Duplikat & yang tidak valid otomatis dibuang.';
		to.placeholder = personal
			? 'budi@example.ac.id, Budi Santoso, Pengaruh Katalis Heterogen pada Transesterifikasi\nsiti@univ.ac.id, Siti Aminah, Adsorpsi Logam Berat dengan Karbon Aktif'
			: 'budi@example.ac.id\nsiti@univ.ac.id, andi@inst.go.id';
		countAddr();
	}
	document.querySelectorAll('input[name=mode]').forEach(function(r){ r.addEventListener('change', applyMode); });

	document.querySelectorAll('.chip[data-tpl]').forEach(function(c){
		c.addEventListener('click', function(){
			var k=c.getAttribute('data-tpl');
			if(k==='_clear'){ subject.value=''; body.value=''; return; }
			if(TPL[k]){ subject.value=TPL[k].subject; body.value=TPL[k].body;
				if(k==='invite'){ var pm=document.querySelector('input[name=mode][value=personal]'); if(pm){ pm.checked=true; applyMode(); } }
				subject.focus(); }
		});
	});

	function countAddr(){
		var personal = modeVal()==='personal';
		var re=/^[^@\s]+@[^@\s]+\.[^@\s]+$/, seen={}, ok=0, bad=0;
		if(personal){
			(to.value||'').split(/\r\n|\r|\n/).forEach(function(line){
				if(!line.trim()) return;
				var c = line.indexOf('\t')>=0 ? line.split('\t') : line.split(',');
				var e=(c[0]||'').replace(/^[\s<"']+|[\s>"']+$/g,'');
				if(re.test(e)){ var l=e.toLowerCase(); if(!seen[l]){seen[l]=1;ok++;} else bad++; } else bad++;
			});
		} else {
			(to.value||'').split(/[\s,;]+/).filter(function(x){return x.length;}).forEach(function(p){
				var e=p.replace(/^[<"']+|[>"']+$/g,''); if(re.test(e)){ var l=e.toLowerCase(); if(!seen[l]){seen[l]=1;ok++;} } else bad++;
			});
		}
		$('cmCount').textContent = ok+' alamat valid'+(bad?(' · '+bad+' tidak valid/duplikat diabaikan'):'')+'.';
	}
	to.addEventListener('input', countAddr); applyMode();

	function post(url, data){
		var fd=new FormData(); fd.append('csrfToken',CSRF);
		for(var k in data) fd.append(k,data[k]);
		return fetch(url,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();});
	}

	testBtn.addEventListener('click', function(){
		if(!subject.value.trim()||!body.value.trim()){ show('err','Isi subjek dan isi email dulu.'); return; }
		testBtn.disabled=true; show('ok','Mengirim uji…');
		post(TEST,{subject:subject.value,body:body.value,format:fmt(),fromName:fromName.value}).then(function(res){
			testBtn.disabled=false;
			if(res.error) show('err',res.error); else show('ok','✓ Uji terkirim ke '+res.email+'. Cek kotak masuk Anda, lalu kirim ke semua.');
		}).catch(function(){ testBtn.disabled=false; show('err','Gagal menghubungi server.'); });
	});

	var jobId=null, total=0;
	sendBtn.addEventListener('click', function(){
		if(!subject.value.trim()||!body.value.trim()){ show('err','Isi subjek dan isi email dulu.'); return; }
		if(!to.value.trim()){ show('err','Daftar penerima masih kosong.'); return; }
		if(!confirm('Kirim email ke seluruh daftar ini?\n\nDisarankan uji-kirim dulu. Lanjutkan?')) return;
		sendBtn.disabled=true; testBtn.disabled=true; result.innerHTML='';
		post(START,{subject:subject.value,body:body.value,format:fmt(),fromName:fromName.value,recipients:to.value,mode:modeVal()}).then(function(res){
			if(res.error){ sendBtn.disabled=false; testBtn.disabled=false; show('err',res.error); return; }
			jobId=res.jobId; total=res.total;
			var extra=[]; if(res.invalid) extra.push(res.invalid+' tidak valid'); if(res.dup) extra.push(res.dup+' duplikat');
			progWrap.style.display='block';
			progText.textContent='Mengirim 0 / '+total+(extra.length?(' ('+extra.join(', ')+' diabaikan)'):'');
			loop();
		}).catch(function(){ sendBtn.disabled=false; testBtn.disabled=false; show('err','Gagal menghubungi server.'); });
	});

	function loop(){
		post(BATCH,{jobId:jobId}).then(function(res){
			if(res.error){ show('err',res.error); sendBtn.disabled=false; testBtn.disabled=false; return; }
			var pct=Math.round(res.processed/res.total*100);
			bar.style.width=pct+'%';
			progText.textContent='Terkirim '+res.sent+' / '+res.total+(res.failed?(' · '+res.failed+' gagal'):'');
			if(res.done){
				show('ok','✓ Selesai. '+res.sent+' email terkirim'+(res.failed?(', '+res.failed+' gagal'):'')+' dari '+res.total+' penerima.');
				if(today) today.textContent = (parseInt(today.textContent,10)||0)+res.sent;
				sendBtn.disabled=false; testBtn.disabled=false;
			} else { setTimeout(loop, 500); }
		}).catch(function(){ show('err','Koneksi terputus saat mengirim. Sebagian mungkin sudah terkirim. Muat ulang untuk cek.'); sendBtn.disabled=false; testBtn.disabled=false; });
	}
})();
</script>
{/literal}
{/if}

</body>
</html>
