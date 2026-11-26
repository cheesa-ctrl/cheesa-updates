{**
 * plugins/generic/cheesaGuard/templates/page.tpl
 * Halaman penuh CHEESA Security Scan (/index.php/<jurnal>/cheesaguard).
 *}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>CHEESA Security Scan</title>
{literal}
<style>
	:root { --navy:#030271; --navy-2:#1b1a8f; --accent:#ed991f; --red:#c62828; --amber:#b45309; --ok:#1f8a4c; --line:#e4e7f0; --muted:#5e6580; }
	* { box-sizing: border-box; } html, body { margin: 0; }
	body { min-height: 100vh; background: #eef0f7; font: 14px/1.55 "Noto Sans", Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2140; }
	.top { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 10px 24px; background: #fff; border-bottom: 1px solid #e1e4ef; box-shadow: 0 2px 10px rgba(3,2,113,.05); }
	.crumb { display: flex; align-items: center; gap: 10px; font-weight: 600; }
	.crumb .dot { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--navy); color: #fff; font-weight: 800; }
	.crumb .j { color: var(--muted); font-weight: 500; }
	.nav a { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; color: var(--navy); }
	.wrap { max-width: 1040px; margin: 0 auto; padding: 24px 20px 60px; }
	.card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px 24px; margin-bottom: 18px; box-shadow: 0 6px 18px -12px rgba(3,2,113,.25); }
	h1 { font-size: 20px; margin: 0 0 4px; color: var(--navy); }
	.sub { color: var(--muted); margin: 0 0 14px; font-size: 13px; }
	.btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 9px; border: 1px solid var(--navy); background: var(--navy); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; }
	.btn:hover { background: var(--navy-2); } .btn:disabled { opacity: .5; cursor: not-allowed; }
	.note { padding: 10px 14px; border-radius: 9px; font-size: 13px; margin-top: 12px; }
	.note.warnbox { background: #fff6e8; border: 1px solid #f3d9a8; color: #7a5a17; }
	.note.err { background: #fdeceb; border: 1px solid #f3c3bf; color: var(--red); }
	.note.ok { background: #e9f6ee; border: 1px solid #bfe4cc; color: var(--ok); }
	.summary { display: flex; gap: 12px; flex-wrap: wrap; margin: 4px 0 16px; }
	.stat { background: #f6f8fc; border: 1px solid var(--line); border-radius: 10px; padding: 10px 16px; min-width: 110px; }
	.stat b { display: block; font-size: 24px; color: var(--navy); font-family: "Space Grotesk", sans-serif; }
	.stat.red b { color: var(--red); } .stat.amber b { color: var(--amber); }
	.stat span { font-size: 12px; color: var(--muted); }
	.find { border: 1px solid var(--line); border-left-width: 5px; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; background: #fff; }
	.find.high { border-left-color: var(--red); } .find.medium { border-left-color: var(--amber); }
	.find .row1 { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
	.badge { font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 999px; text-transform: uppercase; letter-spacing: .5px; }
	.badge.high { background: #fbe3e1; color: var(--red); } .badge.medium { background: #fdeccb; color: var(--amber); }
	.badge.new { background: var(--navy); color: #fff; }
	.find .area { font-weight: 700; color: var(--navy); } .find .label { color: var(--muted); font-size: 12.5px; }
	.find .signals { margin: 6px 0 4px; font-size: 12.5px; color: #444; }
	.find .snippet { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; background: #f6f7fb; border-radius: 7px; padding: 8px 10px; color: #333; word-break: break-word; }
	.find .facts { margin-top: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
	.mini { padding: 5px 13px; border-radius: 7px; border: 1px solid #cdd3e6; background: #fff; font-weight: 600; font-size: 12.5px; cursor: pointer; }
	.mini:disabled { opacity: .5; cursor: default; }
	.mini.clean:hover { border-color: #15803d; color: #15803d; }
	.mini.restore:hover { border-color: #1d3b7a; color: #1d3b7a; }
	.done { color: #15803d; font-size: 12px; font-weight: 600; }
	.amsg { font-size: 12px; }
	.amsg .bad { color: #c62828; }
	.amsg .warn { color: #b45309; }
	.log { font-size: 12.5px; } .log .li { padding: 7px 0; border-bottom: 1px solid #eef0f6; } .log .li b { color: var(--navy); }
	.spin { display:inline-block; width:14px; height:14px; border:2px solid #fff; border-top-color: transparent; border-radius:50%; animation: sp .7s linear infinite; }
	@keyframes sp { to { transform: rotate(360deg); } }
</style>
{/literal}
</head>
<body>

<div class="top">
	<div class="crumb"><span class="dot">C</span> CHEESA Security Scan <span class="j">· {$cgName|escape}</span></div>
	<div class="nav"><a href="{$cgDash|escape}">← Dashboard</a></div>
</div>

<div class="wrap">
	<div class="card">
		<h1>Deteksi injeksi judi online / peretasan</h1>
		<p class="sub">Memindai konten langsung situs — pengaturan jurnal, pengumuman, menu & halaman statis, metadata artikel terbit, dan berkas galley HTML — untuk tanda spam judi online (judol) dan penanda injeksi/peretasan. <b>Hanya membaca</b>, tidak mengubah apa pun.</p>
		<div class="note warnbox">Temuan di bawah adalah <b>dugaan</b> untuk Anda periksa. Konten yang memang Anda/editor buat bisa ikut tertandai (mis. artikel yang membahas perjudian). Yang bertanda <b>BARU</b> — muncul sejak pindai terakhir dan tidak Anda tambahkan — paling patut dicurigai sebagai injeksi.</div>
		<div style="margin-top:16px;"><button type="button" class="btn" id="cgScan">🛡 Scan now</button> <span class="sub" id="cgStatus" style="margin-left:8px;"></span></div>
		<div id="cgSummary"></div>
		<div id="cgResult"></div>
	</div>

	{if $cgLog}
	<div class="card">
		<h1 style="font-size:16px;">Riwayat pindai</h1>
		<div class="log">{foreach $cgLog as $l}<div class="li"><b>{$l.time|escape}</b> · {$l.by|escape} · {$l.detail|escape}</div>{/foreach}</div>
	</div>
	{/if}
</div>

<form id="csrfHolder" style="display:none">{csrf}</form>
<script>
var CG = {ldelim} scan: "{$cgScanUrl|escape:'javascript'}", fix: "{$cgFixUrl|escape:'javascript'}", restore: "{$cgRestoreUrl|escape:'javascript'}" {rdelim};
</script>
{literal}
<script>
(function(){
	var CSRF = document.querySelector('#csrfHolder input[name=csrfToken]').value;
	var btn = document.getElementById('cgScan'), status = document.getElementById('cgStatus'),
		summary = document.getElementById('cgSummary'), result = document.getElementById('cgResult');

	function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

	btn.addEventListener('click', function(){
		btn.disabled = true;
		status.innerHTML = '<span class="spin"></span> Memindai…';
		summary.innerHTML = ''; result.innerHTML = '';
		var fd = new FormData(); fd.append('csrfToken', CSRF);
		fetch(CG.scan, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
			btn.disabled = false; status.textContent = '';
			if (res.error){ result.innerHTML = '<div class="note err">'+esc(res.error)+'</div>'; return; }
			summary.innerHTML = '<div class="summary">'
				+ '<div class="stat"><b>'+res.total+'</b><span>temuan</span></div>'
				+ '<div class="stat red"><b>'+res.high+'</b><span>risiko tinggi</span></div>'
				+ '<div class="stat amber"><b>'+res.new+'</b><span>baru sejak pindai lalu</span></div>'
				+ '</div>';
			if (!res.findings.length){ result.innerHTML = '<div class="note ok">✓ Bersih. Tidak ditemukan tanda judol/injeksi pada konten yang dipindai. (Pindai: '+esc(res.time)+')</div>'; return; }
			var html = '';
			res.findings.forEach(function(f, i){
				html += '<div class="find '+esc(f.severity)+'" data-fp="'+esc(f.fp)+'">'
					+ '<div class="row1">'
					+ (f.isNew ? '<span class="badge new">Baru</span>' : '')
					+ '<span class="badge '+esc(f.severity)+'">'+(f.severity==='high'?'Tinggi':'Sedang')+'</span>'
					+ '<span class="area">'+esc(f.area)+'</span> <span class="label">'+esc(f.label)+'</span>'
					+ '</div>'
					+ '<div class="signals">⚑ '+esc((f.signals||[]).join(' · '))+'</div>'
					+ '<div class="snippet">'+esc(f.snippet)+'</div>'
					+ '<div class="facts">'
					+ (f.fixed
						? '<button type="button" class="mini restore" data-fp="'+esc(f.fp)+'">↩ Pulihkan</button> <span class="done">✓ sudah dibersihkan</span>'
						: '<button type="button" class="mini clean" data-fp="'+esc(f.fp)+'">🧹 Bersihkan</button>')
					+ ' <span class="amsg"></span>'
					+ '</div>'
					+ '</div>';
			});
			result.innerHTML = html;
			bindActions();
		}).catch(function(){ btn.disabled = false; status.textContent=''; result.innerHTML = '<div class="note err">Gagal menghubungi server.</div>'; });
	});

	function act(url, fp, btn, confirmMsg){
		if (!confirm(confirmMsg)) return;
		var card = btn.closest('.find'), msg = card.querySelector('.amsg');
		btn.disabled = true; msg.textContent = 'Memproses…';
		var fd = new FormData(); fd.append('csrfToken', CSRF); fd.append('fp', fp);
		fetch(url, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
			var facts = card.querySelector('.facts');
			if (res.error){ btn.disabled = false; msg.innerHTML = '<span class="bad">'+esc(res.error)+'</span>'; return; }
			if (res.nochange){ btn.disabled = false; msg.innerHTML = '<span class="warn">'+esc(res.message)+'</span>'; return; }
			if (url === CG.fix){
				facts.innerHTML = '<button type="button" class="mini restore" data-fp="'+esc(fp)+'">↩ Pulihkan</button> <span class="done">✓ dibersihkan</span> <span class="amsg"></span>';
			} else {
				facts.innerHTML = '<button type="button" class="mini clean" data-fp="'+esc(fp)+'">🧹 Bersihkan</button> <span class="amsg"></span>';
			}
			bindActions();
		}).catch(function(){ btn.disabled = false; msg.innerHTML = '<span class="bad">Gagal menghubungi server.</span>'; });
	}
	function bindActions(){
		result.querySelectorAll('button.clean').forEach(function(b){ b.onclick = function(){ act(CG.fix, b.getAttribute('data-fp'), b, 'Bersihkan bagian berbahaya dari konten ini?\n\nTeks asli dicadangkan dan bisa dipulihkan kapan saja.'); }; });
		result.querySelectorAll('button.restore').forEach(function(b){ b.onclick = function(){ act(CG.restore, b.getAttribute('data-fp'), b, 'Pulihkan konten ke versi asli (sebelum dibersihkan)?'); }; });
	}
})();
</script>
{/literal}
</body>
</html>
