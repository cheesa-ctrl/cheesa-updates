{**
 * Halaman kelola CHEESA Editorial Pick (/index.php/<jurnal>/editorialpick).
 *}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Editorial Pick — {$epJournal|escape}</title>
{literal}
<style>
	:root { --navy: #030271; --navy-2: #1b1a8f; --accent: #ed991f; --ok: #1f8a4c; --red: #c62828; --line: #e4e7f0; --soft: #f5f6fb; --muted: #5e6580; }
	* { box-sizing: border-box; }
	html, body { margin: 0; }
	body { min-height: 100vh; background: #eef0f7; font: 14px/1.5 "Noto Sans", Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2140; }
	a { color: var(--navy); }
	.top { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
		padding: 10px 24px; background: #fff; border-bottom: 1px solid #e1e4ef; box-shadow: 0 2px 10px rgba(3,2,113,.05); }
	.crumb { display: flex; align-items: center; gap: 10px; font-weight: 600; min-width: 0; }
	.crumb .dot { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--navy); color: #fff; font-weight: 800; }
	.crumb .j { color: var(--muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 42vw; }
	.nav { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
	.nav a { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid transparent; }
	.nav a:hover { background: #f1f2fb; border-color: #dfe2f2; }
	.nav .who { color: var(--muted); font-size: 12.5px; margin-left: 6px; }
	.wrap { max-width: 1120px; margin: 0 auto; padding: 24px 24px 60px; }
	.head { display: flex; align-items: center; gap: 14px; padding: 18px 20px; margin: 0 0 16px; border-radius: 14px; color: #fff;
		background: radial-gradient(120% 140% at 100% 0, rgba(237,153,31,.35) 0, rgba(237,153,31,0) 45%), linear-gradient(135deg, var(--navy) 0, var(--navy-2) 100%); }
	.head .logo { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--accent); font-size: 22px; box-shadow: 0 6px 16px rgba(0,0,0,.2); }
	.head h1 { margin: 0; font-size: 18px; line-height: 1.25; }
	.head p { margin: 2px 0 0; font-size: 12.5px; color: rgba(255,255,255,.8); }
	.note { display: flex; gap: 10px; margin: 0 0 14px; padding: 11px 14px; border-radius: 10px; border: 1px solid; }
	.note.ok { background: #ecf8f1; border-color: #b9e4cb; color: #14532d; }
	.note.err { background: #fdecec; border-color: #f3c0c0; color: #7f1d1d; }
	.note.info { background: #fff8ec; border-color: #f6d9a8; color: #7a4a00; }
	.note b { flex: 0 0 auto; }
	.card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin: 0 0 16px; }
	.card > h2 { margin: 0 0 12px; font-size: 15px; color: var(--navy); display: flex; align-items: center; gap: 8px; }
	.card > h2 small { font-weight: 400; color: var(--muted); }
	.toggle { display: flex; align-items: center; gap: 10px; }
	.switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; }
	.rowlist { display: grid; gap: 8px; }
	.erow { display: flex; align-items: center; gap: 12px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
	.erow .cov { flex: 0 0 auto; width: 48px; height: 64px; border-radius: 6px; object-fit: cover; background: linear-gradient(135deg, var(--navy), #01014d); display: grid; place-items: center; color: #fff; font-size: 10px; font-weight: 700; overflow: hidden; }
	.erow .cov img { width: 100%; height: 100%; object-fit: cover; display: block; }
	.erow .info { min-width: 0; flex: 1; }
	.erow .etitle { font-weight: 700; color: var(--navy); font-size: 14px; line-height: 1.3; }
	.erow .emeta { font-size: 12px; color: var(--muted); margin-top: 2px; }
	.erow .eact { flex: 0 0 auto; display: flex; gap: 4px; align-items: center; }
	.btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 11px; border-radius: 8px; border: 1px solid var(--navy); background: var(--navy); color: #fff;
		font-weight: 600; font-size: 13px; font-family: inherit; cursor: pointer; text-decoration: none; white-space: nowrap; }
	.btn:hover { background: var(--navy-2); }
	.btn.sec { background: #fff; color: var(--navy); border-color: #c9cde3; }
	.btn.sec:hover { background: #f6f7fd; }
	.btn.add { background: var(--accent); border-color: var(--accent); }
	.btn.add:hover { background: #d9860f; }
	.btn.del { background: #fff; color: var(--red); border-color: #f0c4c4; }
	.btn.del:hover { background: #fdecec; }
	.btn.icon { padding: 6px 9px; }
	.btn[disabled] { opacity: .4; cursor: default; }
	.search { display: flex; gap: 8px; margin: 0 0 12px; flex-wrap: wrap; }
	.search input { flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #cdd1e1; border-radius: 9px; font: inherit; }
	.search input:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--navy); }
	.empty { padding: 26px; text-align: center; color: var(--muted); background: var(--soft); border: 1px dashed #cfd4e6; border-radius: 10px; }
	form.inline { display: inline; margin: 0; }
	@media (max-width: 600px) { .erow { flex-wrap: wrap; } .erow .eact { width: 100%; justify-content: flex-end; } }
</style>
{/literal}
</head>
<body>
<header class="top">
	<div class="crumb"><span class="dot">C</span><span>Editorial Pick</span>{if $epJournal}<span class="j">· {$epJournal|escape}</span>{/if}</div>
	<nav class="nav">
		<a href="{$epDashUrl|escape}">← Dasbor</a>
		<a href="{$epHomeUrl|escape}" target="_blank" rel="noopener">Lihat beranda ↗</a>
		{if $epUserName}<span class="who">{$epUserName|escape}</span>{/if}
	</nav>
</header>

<main class="wrap">
{if $epDenied}
	<div class="head"><div class="logo">🔒</div><div><h1>Editorial Pick</h1><p>Khusus Journal Manager/Editor</p></div></div>
	<div class="note info"><b>i</b><span>Halaman ini hanya untuk Site Administrator atau Journal Manager/Editor jurnal ini. Login dengan akun yang sesuai lalu buka lagi.</span></div>
{else}
	<div class="head">
		<div class="logo">★</div>
		<div><h1>Editorial Pick</h1><p>Pilih artikel yang ditonjolkan di {$epJournal|escape}</p></div>
	</div>

	{if $epFlash && $epFlash.ok}<div class="note ok"><b>✓</b><span>{$epFlash.ok|escape}</span></div>{/if}
	{if $epFlash && $epFlash.err}<div class="note err"><b>!</b><span>{$epFlash.err|escape}</span></div>{/if}

	<div class="card">
		<div class="toggle">
			<form class="inline" method="post" action="{$epSaveUrl|escape}">
				{csrf}
				<input type="hidden" name="verb" value="cards">
				<input type="hidden" name="on" value="{if $epShowCards}0{else}1{/if}">
				<label class="switch">
					<input type="checkbox" {if $epShowCards}checked{/if} onchange="this.form.submit()">
					Tampilkan sebagai baris kartu di beranda
				</label>
			</form>
		</div>
		<p style="margin:8px 0 0;color:var(--muted);font-size:12.5px">Bila dimatikan, Editorial Pick hanya muncul sebagai blok sidebar (bila bloknya dipasang di sidebar).</p>
	</div>

	<div class="card">
		<h2>Pilihan sekarang <small>{$epPicked|@count} artikel — urutan ini yang tampil</small>
			{if $epPicked}
			<form class="inline" method="post" action="{$epSaveUrl|escape}" style="margin-left:auto" onsubmit="return confirm('Kosongkan semua pilihan?')">
				{csrf}<input type="hidden" name="verb" value="clear">
				<button class="btn del" type="submit">Kosongkan semua</button>
			</form>
			{/if}
		</h2>
		{if !$epPicked}
			<div class="empty">Belum ada artikel dipilih. Tambahkan dari daftar di bawah.</div>
		{else}
			<div class="rowlist">
			{foreach from=$epPicked item=a name=picks}
				<div class="erow">
					<span class="cov">{if $a.cover}<img src="{$a.cover|escape}" alt="">{else}CHEESA{/if}</span>
					<div class="info">
						<div class="etitle">{$a.title|escape}</div>
						<div class="emeta">{if $a.authors}{$a.authors|escape} · {/if}{if $a.date}{$a.date|escape} · {/if}{$a.issue|escape} · #{$a.id}</div>
					</div>
					<div class="eact">
						<form class="inline" method="post" action="{$epSaveUrl|escape}">{csrf}<input type="hidden" name="verb" value="up"><input type="hidden" name="id" value="{$a.id}"><input type="hidden" name="q" value="{$epQ|escape}">
							<button class="btn sec icon" type="submit" title="Naikkan"{if $smarty.foreach.picks.first} disabled{/if}>↑</button></form>
						<form class="inline" method="post" action="{$epSaveUrl|escape}">{csrf}<input type="hidden" name="verb" value="down"><input type="hidden" name="id" value="{$a.id}"><input type="hidden" name="q" value="{$epQ|escape}">
							<button class="btn sec icon" type="submit" title="Turunkan"{if $smarty.foreach.picks.last} disabled{/if}>↓</button></form>
						<form class="inline" method="post" action="{$epSaveUrl|escape}">{csrf}<input type="hidden" name="verb" value="remove"><input type="hidden" name="id" value="{$a.id}"><input type="hidden" name="q" value="{$epQ|escape}">
							<button class="btn del icon" type="submit" title="Hapus dari pilihan">✕</button></form>
					</div>
				</div>
			{/foreach}
			</div>
		{/if}
	</div>

	<div class="card">
		<h2>Tambah artikel <small>artikel terbit di jurnal ini</small></h2>
		<form class="search" method="get" action="{$epSearchUrl|escape}">
			<input type="text" name="q" value="{$epQ|escape}" placeholder="Cari judul artikel…" autocomplete="off">
			<button class="btn sec" type="submit">Cari</button>
			{if $epQ}<a class="btn sec" href="{$epSearchUrl|escape}">Reset</a>{/if}
		</form>
		{if !$epAvailable}
			<div class="empty">{if $epQ}Tidak ada artikel terbit yang cocok dengan “{$epQ|escape}”.{else}Tidak ada artikel terbit lain untuk ditambahkan.{/if}</div>
		{else}
			<div class="rowlist">
			{foreach from=$epAvailable item=a}
				<div class="erow">
					<span class="cov">{if $a.cover}<img src="{$a.cover|escape}" alt="">{else}CHEESA{/if}</span>
					<div class="info">
						<div class="etitle">{$a.title|escape}</div>
						<div class="emeta">{if $a.authors}{$a.authors|escape} · {/if}{if $a.date}{$a.date|escape} · {/if}{$a.issue|escape} · #{$a.id}</div>
					</div>
					<div class="eact">
						<form class="inline" method="post" action="{$epSaveUrl|escape}">{csrf}<input type="hidden" name="verb" value="add"><input type="hidden" name="id" value="{$a.id}"><input type="hidden" name="q" value="{$epQ|escape}">
							<button class="btn add" type="submit">+ Tambah</button></form>
					</div>
				</div>
			{/foreach}
			</div>
			<p style="margin:12px 0 0;color:var(--muted);font-size:12px">Menampilkan maksimal {$epAvailableLimit} artikel terbaru. Pakai pencarian untuk menemukan artikel lama.</p>
		{/if}
	</div>
{/if}
</main>
</body>
</html>
