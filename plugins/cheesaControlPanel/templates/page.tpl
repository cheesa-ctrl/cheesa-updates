{**
 * plugins/generic/cheesaControlPanel/templates/page.tpl
 * Halaman penuh CHEESA Control Panel (/index.php/<jurnal>/cheesapanel).
 * Isi panel dimuat dari aksi "manage" plugin, sama seperti modal.
 *}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>CHEESA Control Panel{if $cpJournal} — {$cpJournal|escape}{/if}</title>
{literal}
<style>
	html, body { margin: 0; }
	body { min-height: 100vh; background: #eef0f7; font: 14px/1.5 "Noto Sans", Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2140; }
	.cpp-top { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
		padding: 10px 24px; background: #fff; border-bottom: 1px solid #e1e4ef; box-shadow: 0 2px 10px rgba(3,2,113,.05); }
	.cpp-crumb { display: flex; align-items: center; gap: 10px; min-width: 0; font-weight: 600; }
	.cpp-crumb .dot { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: #030271; color: #fff; font-weight: 800; }
	.cpp-crumb .j { color: #5e6580; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 46vw; }
	.cpp-nav { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
	.cpp-nav a { padding: 6px 12px; border-radius: 8px; color: #030271; text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid transparent; }
	.cpp-nav a:hover { background: #f1f2fb; border-color: #dfe2f2; }
	.cpp-nav .who { color: #5e6580; font-size: 12.5px; margin-left: 6px; }
	.cpp-wrap { max-width: 1180px; margin: 0 auto; padding: 28px 24px 60px; }
	.cpp-loading { display: grid; place-items: center; gap: 12px; padding: 80px 0; color: #5e6580; }
	.cpp-spin { width: 34px; height: 34px; border-radius: 50%; border: 3px solid #d9dcef; border-top-color: #030271; animation: cpp-r .8s linear infinite; }
	@keyframes cpp-r { to { transform: rotate(360deg); } }
	.cpp-err { padding: 14px 16px; border-radius: 10px; background: #fdecec; border: 1px solid #f3c0c0; color: #7f1d1d; }
	.cpp-foot { margin-top: 18px; text-align: center; font-size: 12px; color: #8a90a6; }
</style>
{/literal}
</head>
<body>
<header class="cpp-top">
	<div class="cpp-crumb"><span class="dot">C</span><span>CHEESA Control Panel</span>{if $cpJournal}<span class="j">· {$cpJournal|escape}</span>{/if}</div>
	<nav class="cpp-nav">
		<a href="{$cpDashUrl|escape}">← Dasbor</a>
		<a href="{$cpPluginsUrl|escape}">Plugins</a>
		<a href="{$cpSiteUrl|escape}" target="_blank" rel="noopener">Lihat situs ↗</a>
		{if $cpUserName}<span class="who">{$cpUserName|escape}</span>{/if}
	</nav>
</header>
<main class="cpp-wrap">
	<div id="cppHost" data-src="{$cpManageUrl|escape}">
		<div class="cpp-loading"><div class="cpp-spin"></div><div>Memuat panel dan memeriksa update…</div></div>
	</div>
	<p class="cpp-foot">Halaman ini hanya bisa dibuka Site Administrator dan Journal Manager/Editor jurnal ini.</p>
</main>
<script>
{literal}
(function () {
	var host = document.getElementById('cppHost');
	var body = new URLSearchParams(); body.append('verb', 'panel'); body.append('fullpage', '1');
	fetch(host.getAttribute('data-src'), {
		method: 'POST', credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
		body: body.toString()
	}).then(function (r) {
		if (!r.ok) throw new Error('Panel tidak bisa dimuat (HTTP ' + r.status + '). Coba login ulang.');
		return r.json();
	}).then(function (r) {
		if (!r || !r.status || !r.content) throw new Error((r && r.content) || 'Panel tidak bisa dimuat.');
		host.innerHTML = r.content;
		// skrip di dalam isi panel dijalankan (innerHTML tidak menjalankannya sendiri)
		var scripts = host.querySelectorAll('script');
		for (var i = 0; i < scripts.length; i++) {
			var s = document.createElement('script'); s.text = scripts[i].text;
			scripts[i].parentNode.replaceChild(s, scripts[i]);
		}
	}).catch(function (e) {
		host.innerHTML = '';
		var d = document.createElement('div'); d.className = 'cpp-err'; d.textContent = e.message || 'Panel tidak bisa dimuat.';
		host.appendChild(d);
	});
})();
{/literal}
</script>
</body>
</html>
