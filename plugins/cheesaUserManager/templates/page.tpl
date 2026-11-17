{**
 * plugins/generic/cheesaUserManager/templates/page.tpl
 * Halaman penuh CHEESA User Manager (/index.php/<jurnal>/cheesausers).
 *}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kelola Akun — CHEESA</title>
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
	.crumb .j { color: var(--muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; }
	.nav { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
	.nav a { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid transparent; }
	.nav a:hover { background: #f1f2fb; border-color: #dfe2f2; }
	.nav .who { color: var(--muted); font-size: 12.5px; margin-left: 6px; }

	.wrap { max-width: 1240px; margin: 0 auto; padding: 28px 24px 60px; }
	.head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 18px 20px; margin: 0 0 16px; border-radius: 14px; color: #fff;
		background: radial-gradient(120% 140% at 100% 0, rgba(237,153,31,.35) 0, rgba(237,153,31,0) 45%), linear-gradient(135deg, var(--navy) 0, var(--navy-2) 100%); }
	.brand { display: flex; align-items: center; gap: 14px; }
	.logo { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--accent); font-size: 22px; box-shadow: 0 6px 16px rgba(0,0,0,.2); }
	.head h1 { margin: 0; font-size: 18px; line-height: 1.25; }
	.head p { margin: 2px 0 0; font-size: 12.5px; color: rgba(255,255,255,.8); }
	.search { display: flex; flex-wrap: wrap; gap: 8px; flex: 1 1 340px; max-width: 560px; }
	.search input { flex: 1 1 200px; }
	.search input { flex: 1; min-width: 0; padding: 9px 12px; border-radius: 9px; border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.12); color: #fff; font: inherit; }
	.search input::placeholder { color: rgba(255,255,255,.7); }
	.search input:focus { outline: none; background: #fff; color: #1d2140; }

	.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 16px; }
	.stat { padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
	.stat span { display: block; font-size: 11.5px; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
	.stat b { display: block; margin-top: 2px; font-size: 22px; line-height: 1.2; color: var(--navy); }
	.stat b small { font-size: 12px; font-weight: 600; color: var(--muted); }

	.note { display: flex; gap: 10px; margin: 0 0 14px; padding: 11px 14px; border-radius: 10px; border: 1px solid; }
	.note.ok { background: #ecf8f1; border-color: #b9e4cb; color: #14532d; }
	.note.err { background: #fdecec; border-color: #f3c0c0; color: #7f1d1d; }
	.note.info { background: #fff8ec; border-color: #f6d9a8; color: #7a4a00; }
	.note b { flex: 0 0 auto; }

	.list { display: grid; gap: 10px; margin: 0 0 16px; }
	.user { display: grid; grid-template-columns: 44px minmax(0, 1.3fr) minmax(0, 1.2fr) minmax(0, 1fr) 350px; gap: 14px; align-items: center;
		padding: 14px 16px; background: #fff; border: 1px solid var(--line); border-radius: 12px; }
	.user:hover { border-color: #cfd4e6; box-shadow: 0 6px 18px rgba(3,2,113,.07); }
	.user.is-off { background: var(--soft); }
	.ava { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: var(--navy); background: #e9ebfb; text-transform: uppercase; }
	.user.is-admin .ava { background: #fff1db; color: #a45d00; }
	.uname { font-weight: 700; font-size: 15px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; word-break: break-all; }
	.sub { color: var(--muted); font-size: 12.5px; overflow-wrap: anywhere; }
	.tag { font-size: 10.5px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; padding: 1px 7px; border-radius: 99px; border: 1px solid var(--line); background: var(--soft); color: var(--muted); }
	.tag.admin { background: #fff1db; color: #a45d00; border-color: #f6d9a8; }
	.tag.me { background: #e9ebfb; color: var(--navy); border-color: #d3d7f3; }
	.tag.lock { background: #f1f2f8; color: #5e6580; border-color: #dfe2ee; cursor: help; }
	.tag.off { background: #fdecec; color: var(--red); border-color: #f3c0c0; }
	.chips { display: flex; flex-wrap: wrap; gap: 4px; }
	.chip { font-size: 11.5px; padding: 1px 8px; border-radius: 99px; background: #f1f2fb; color: #2a2f5a; border: 1px solid #e3e6f5; }
	.chips .none { color: #8a90a6; font-style: italic; font-size: 12px; }
	.act { font-size: 12px; }
	.act .clean { color: var(--ok); font-weight: 600; }
	.act .busy { color: #7a4a00; }
	.lbl { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; margin-bottom: 2px; }

	.btns { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; position: relative; }
	.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 8px; border: 1px solid var(--navy); background: var(--navy); color: #fff;
		font-weight: 600; font-size: 13px; line-height: 1.2; font-family: inherit; cursor: pointer; text-decoration: none; white-space: nowrap; list-style: none; }
	.btn:hover { background: var(--navy-2); }
	.btn.sec { background: #fff; color: var(--navy); border-color: #c9cde3; }
	.btn.sec:hover { background: #f6f7fd; border-color: var(--navy); }
	.btn.del { background: #fff; color: var(--red); border-color: #f0c4c4; }
	.btn.del:hover { background: #fdecec; border-color: var(--red); }
	.btn[disabled] { opacity: .45; cursor: not-allowed; }
	.btn.light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.4); }

	details.pop > summary { list-style: none; }
	details.pop > summary::-webkit-details-marker { display: none; }
	details.pop[open] > summary { background: #e9ebfb; border-color: var(--navy); color: var(--navy); }
	.popbox { position: absolute; right: 0; top: calc(100% + 6px); z-index: 5; width: min(340px, 86vw); padding: 14px; background: #fff; border: 1px solid #d5d9ea;
		border-radius: 12px; box-shadow: 0 14px 34px rgba(3,2,113,.16); text-align: left; }
	.popbox label { display: block; font-weight: 600; margin: 0 0 4px; }
	.popbox input[type=text] { width: 100%; padding: 8px 10px; border: 1px solid #cdd1e1; border-radius: 8px; font: inherit; }
	.popbox input[type=text]:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--navy); }
	.popbox p { margin: 8px 0 10px; font-size: 12px; color: var(--muted); }
	.popbox .row { display: flex; justify-content: flex-end; gap: 6px; }

	.btn.login { background: #0b6b4f; border-color: #0b6b4f; }
	.btn.login:hover { background: #095a42; }
	.regbox[open] { border-color: #c4c9ee; box-shadow: 0 8px 24px rgba(3,2,113,.08); }
	.regin { padding: 4px 16px 16px; border-top: 1px solid var(--line); }
	.reghint { color: var(--muted); font-size: 13px; }
	.reglist { display: grid; gap: 8px; }
	.regrow { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
	.regrow.is-dup { background: var(--soft); opacity: .8; }
	.regname { font-weight: 700; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
	.tag.dup { background: #fdecec; color: var(--red); border-color: #f3c0c0; }
	.regact { flex: 0 0 auto; }
	@media (max-width: 640px) { .regrow { flex-direction: column; align-items: stretch; } }
	.btn.add { background: var(--accent); border-color: var(--accent); }
	.btn.add:hover { background: #d9860f; }
	.addbox { margin: 0 0 16px; }
	.addbox[open] { border-color: #f6d9a8; box-shadow: 0 8px 24px rgba(237,153,31,.12); }
	.addform { padding: 4px 16px 16px; border-top: 1px solid var(--line); }
	.grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 18px; }
	.fld { margin: 12px 0 0; }
	.fld > label { display: block; font-weight: 600; margin: 0 0 4px; }
	.fld > label i { color: var(--red); font-style: normal; }
	.fld input[type=text], .fld input[type=email] { width: 100%; padding: 8px 10px; border: 1px solid #cdd1e1; border-radius: 8px; font: inherit; }
	.fld input:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--navy); }
	.fld small { color: var(--muted); font-size: 12px; }
	.pw { display: flex; gap: 6px; }
	.pw input { flex: 1; min-width: 0; font-family: ui-monospace, Menlo, Consolas, monospace !important; }
	.chk { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 13px; font-weight: 400; cursor: pointer; }
	.roles { display: flex; flex-wrap: wrap; gap: 8px; }
	.chk.role { margin: 0; padding: 6px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
	.chk.role:has(input:checked) { background: #e9ebfb; border-color: #c4c9ee; }
	.chk.role small { color: var(--muted); }
	.chk.mail { margin: 0; align-items: flex-start; padding: 8px 12px; border: 1px solid #d3d7f3; border-radius: 8px; background: #f4f5fd; }
	.chk.mail input { margin-top: 3px; }
	.addfoot { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
	.lockwrap { display: grid; place-items: center; min-height: 60vh; }
	.lockcard { width: min(420px, 100%); padding: 28px; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 18px 40px rgba(3,2,113,.10); text-align: center; }
	.lockcard .lockico { width: 56px; height: 56px; margin: 0 auto 10px; border-radius: 16px; display: grid; place-items: center; font-size: 26px;
		background: linear-gradient(135deg, var(--navy) 0, var(--navy-2) 100%); box-shadow: 0 8px 20px rgba(3,2,113,.25); }
	.lockcard h1 { margin: 0 0 4px; font-size: 19px; color: var(--navy); }
	.lockcard p { margin: 0 0 16px; color: var(--muted); font-size: 13px; }
	.lockcard .pw input { flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid #cdd1e1; border-radius: 9px; font: inherit; font-size: 15px; }
	.lockcard .pw input:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--navy); }
	.lockcard small { display: block; margin-top: 12px; color: var(--muted); font-size: 12px; }
	.lockcard .note { text-align: left; }
	.mask { -webkit-text-security: disc; text-security: disc; }
	.pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 0 0 22px; color: var(--muted); }
	.box { border: 1px solid var(--line); border-radius: 12px; background: #fff; }
	.box > summary { cursor: pointer; padding: 12px 16px; font-weight: 700; color: var(--navy); list-style: none; }
	.box > summary::-webkit-details-marker { display: none; }
	.box > summary small { font-weight: 500; color: var(--muted); margin-left: 8px; }
	.log { width: 100%; border-collapse: collapse; font-size: 12.5px; }
	.log th, .log td { text-align: left; padding: 8px 16px; border-top: 1px solid var(--line); vertical-align: top; }
	.log th { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; }
	.empty { padding: 40px; text-align: center; color: var(--muted); background: #fff; border: 1px dashed #cfd4e6; border-radius: 12px; }

	@media (max-width: 1140px) {
		.user { grid-template-columns: 44px minmax(0, 1fr) minmax(0, 1fr); }
		.user .c-roles { grid-column: 2; }
		.user .btns { grid-column: 1 / -1; justify-content: flex-start; }
		.popbox { left: 0; right: auto; }
	}
	@media (max-width: 640px) {
		.stats { grid-template-columns: 1fr; }
		.grid2 { grid-template-columns: 1fr; }
		.user { grid-template-columns: 44px minmax(0, 1fr); }
		.user .c-roles, .user .c-act { grid-column: 2; }
	}
</style>
{/literal}
</head>
<body>
<header class="top">
	<div class="crumb"><span class="dot">C</span><span>Kelola Akun</span>{if $umJournal}<span class="j">· {$umJournal|escape}</span>{/if}</div>
	<nav class="nav">
		<a href="{$umDashUrl|escape}">← Dasbor</a>
		<a href="{$umUsersUrl|escape}">Users &amp; Roles</a>
		{if !$umDenied && !$umLocked}<a href="{$umLockUrl|escape}" title="Kunci halaman ini sekarang">🔒 Kunci</a>{/if}
		<span class="who">{$umAdminName|escape} · {if $umIsAdmin}Site Admin{else}Editor jurnal{/if}</span>
	</nav>
</header>

<main class="wrap">
{if $umDenied}
	<div class="head"><div class="brand"><div class="logo">🔒</div><div><h1>CHEESA User Manager</h1><p>Halaman ini khusus Site Administrator dan Journal Manager/Editor</p></div></div></div>
	<div class="note info"><b>i</b><span>Anda login sebagai <strong>{$umAdminName|escape}</strong>, yang tidak punya peran Manager/Editor di jurnal ini.
		Login dengan akun Journal Manager/Editor jurnal ini atau Site Administrator, lalu buka halaman ini lagi.</span></div>
{elseif $umLocked}
	<div class="lockwrap">
		<form class="lockcard" method="post" action="{$umUnlockUrl|escape}" id="umUnlock" autocomplete="off">
			{csrf}
			<input type="hidden" name="umk" value="">
			<div class="lockico">🔒</div>
			<h1>CHEESA User Manager</h1>
			<p>Halaman ini dilindungi kata sandi. Masukkan kata sandi untuk mengelola akun.</p>
			{if $umLockErr}<div class="note err"><b>!</b><span>{$umLockErr|escape}</span></div>{/if}
			<div class="pw">
				<input type="text" id="umKey" class="mask" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="Kata sandi" autofocus>
				<button class="btn sec" type="button" title="Tampilkan / sembunyikan" onclick="document.getElementById('umKey').classList.toggle('mask')">👁</button>
			</div>
			<button class="btn" type="submit" style="width:100%;justify-content:center;margin-top:12px">Buka</button>
			<small>Terkunci lagi otomatis setelah 30 menit tanpa aktivitas atau saat logout.</small>
		</form>
	</div>
{else}
	<div class="head">
		<div class="brand">
			<div class="logo">👤</div>
			<div>
				<h1>CHEESA User Manager</h1>
				<p>{if $umScoped}Ubah username, hapus akun, atau gabungkan akun ganda di jurnal {$umJournal|escape}{else}Ubah username, hapus akun, atau gabungkan akun ganda — berlaku untuk semua jurnal di server ini{/if}</p>
			</div>
		</div>
		<form class="search" method="get" action="{$umSearchUrl|escape}">
			<input type="text" name="q" value="{$umQ|escape}" placeholder="Cari username, email, nama, atau ID…" autocomplete="off">
			<button class="btn light" type="submit">Cari</button>
			{if $umQ}<a class="btn light" href="{$umSearchUrl|escape}">Reset</a>{/if}
		</form>
		<button class="btn add" type="button" onclick="var d=document.getElementById('umAdd');d.open=true;d.scrollIntoView({ldelim}behavior:'smooth',block:'start'{rdelim});setTimeout(function(){ldelim}document.getElementById('nuUser').focus(){rdelim},300);">+ Tambah akun</button>
	</div>

	<details class="box addbox" id="umAdd"{if $umOld && $umOld.open} open{/if}>
		<summary>+ Tambah akun baru <small>langsung dengan peran di jurnal {$umJournal|escape}</small></summary>
		<form class="addform" method="post" action="{$umSaveUrl|escape}" id="umAddForm" autocomplete="off">
			{csrf}
			<input type="hidden" name="verb" value="add"><input type="hidden" name="npk" value="">
			<div class="grid2">
				<div class="fld"><label for="nuUser">Username <i>*</i></label>
					<input type="text" id="nuUser" name="username" value="{if $umOld}{$umOld.username|escape}{/if}" maxlength="32" required pattern="[A-Za-z0-9]+([\-_][A-Za-z0-9]+)*" autocapitalize="off" spellcheck="false" placeholder="mis. budisantoso">
					<small>Huruf, angka, - atau _ ; tanpa spasi.</small></div>
				<div class="fld"><label for="nuEmail">Email <i>*</i></label>
					<input type="email" id="nuEmail" name="email" value="{if $umOld}{$umOld.email|escape}{/if}" required placeholder="nama@domain.ac.id"></div>
				<div class="fld"><label for="nuGiven">Nama depan <i>*</i></label>
					<input type="text" id="nuGiven" name="givenName" value="{if $umOld}{$umOld.givenName|escape}{/if}" required></div>
				<div class="fld"><label for="nuFamily">Nama belakang</label>
					<input type="text" id="nuFamily" name="familyName" value="{if $umOld}{$umOld.familyName|escape}{/if}"></div>
				<div class="fld"><label for="nuAff">Afiliasi</label>
					<input type="text" id="nuAff" name="affiliation" value="{if $umOld}{$umOld.affiliation|escape}{/if}" placeholder="mis. Universitas PGRI Madiun"></div>
				<div class="fld"><label for="nuOrcid">ORCID iD</label>
					<input type="text" id="nuOrcid" name="orcid" value="{if $umOld}{$umOld.orcid|escape}{/if}" placeholder="0000-0000-0000-0000" spellcheck="false" autocapitalize="off">
					<small>Boleh ditempel lengkap (https://orcid.org/…). Angka kontrol diperiksa.</small></div>
				<div class="fld"><label for="nuPhone">Nomor telepon</label>
					<input type="text" id="nuPhone" name="phone" value="{if $umOld}{$umOld.phone|escape}{/if}" placeholder="mis. 08123456789" spellcheck="false"></div>
				<div class="fld"><label for="nuPass">Password <i>*</i></label>
					<div class="pw"><input type="text" id="nuPass" value="" autocomplete="off" spellcheck="false" data-lpignore="true" data-form-type="other" placeholder="min. 6 karakter">
					<button class="btn sec" type="button" id="nuGen" title="Buat password acak">⟳ Acak</button>
					<button class="btn sec" type="button" id="nuCopy" title="Salin">⧉</button></div>
					<label class="chk"><input type="checkbox" name="mustChange" value="1" checked> Wajib ganti password saat login pertama</label></div>
			</div>
			<div class="fld"><label for="nuInt">Minat review <small>(reviewing interests)</small></label>
				<input type="text" id="nuInt" name="interests" value="{if $umOld}{$umOld.interests|escape}{/if}" placeholder="mis. adsorpsi, katalis heterogen, pengolahan limbah cair">
				<small>Pisahkan dengan koma. Muncul di profil pengguna dan membantu editor memilih reviewer.</small></div>
			<div class="fld"><label>Peran di jurnal ini <i>*</i></label>
				<div class="roles">
				{foreach from=$umGroups item=g}
					<label class="chk role"><input type="checkbox" name="groups[]" value="{$g.id}"{if $g.checked} checked{/if}> {$g.name|escape}{if $g.role && $g.role != $g.name} <small>{$g.role|escape}</small>{/if}</label>
				{/foreach}
				</div></div>
			<div class="addfoot">
				<label class="chk mail"><input type="checkbox" name="sendEmail" value="1"{if !$umOld || $umOld.sendEmail} checked{/if}>
					<span>✉ Kirim email selamat datang berisi username &amp; password<br><small>Memakai template OJS “USER_REGISTER” (ubah di Settings › Workflow › Emails).</small></span></label>
				<button class="btn" type="submit">Buat akun</button>
			</div>
		</form>
	</details>

	{if $umRegConfigured || $umIsAdmin}
	<details class="box regbox" id="umReg"{if $umRegShown} open{/if}>
		<summary>📥 Pendaftaran masuk{if $umRegShown && $umReg} <small>{$umRegNew} belum punya akun / {$umReg|@count} baris</small>{/if}</summary>
		<div class="regin">
			{if $umIsAdmin}
			<form class="regurls" method="post" action="{$umSaveUrl|escape}">
				{csrf}
				<input type="hidden" name="verb" value="regUrls">
				<div class="fld"><label>Link CSV pendaftaran Editor &amp; Reviewer</label>
					<input type="text" name="url_editor" value="{$umRegUrls.editor|escape}" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" spellcheck="false"></div>
				<div class="fld"><label>Link CSV pendaftaran Author</label>
					<input type="text" name="url_author" value="{$umRegUrls.author|escape}" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" spellcheck="false"></div>
				<p style="margin:10px 0 0"><button class="btn sec" type="submit">Simpan sumber</button>
					<small style="color:var(--muted)"> Pakai link File → Share → Publish to web → CSV. Hanya Site Admin.</small></p>
			</form>
			<hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
			{/if}

			{if !$umRegConfigured}
				<p class="reghint">Belum ada sumber pendaftaran. {if $umIsAdmin}Isi link CSV di atas lalu Simpan.{else}Minta Site Admin mengisi link CSV pendaftaran.{/if}</p>
			{elseif !$umRegShown}
				<p style="margin:0"><a class="btn" href="{$umRegShowUrl|escape}">Muat pendaftaran dari Google Sheet</a>
					<small style="color:var(--muted)"> Data ditarik langsung dari sheet Anda (hanya-baca) saat tombol ini ditekan.</small></p>
			{else}
				{if $umRegErr}<div class="note err"><b>!</b><span>Gagal membaca Google Sheet: {$umRegErr|escape}</span></div>{/if}
				<p style="margin:0 0 12px"><a class="btn sec" href="{$umRegShowUrl|escape}">↻ Segarkan</a> <a class="btn sec" href="{$umRegHideUrl|escape}">Tutup</a></p>
				{if !$umReg}<p class="reghint">Tidak ada baris pendaftaran di sheet.</p>{else}
				<div class="reglist">
				{foreach from=$umReg item=r}
					<div class="regrow{if $r.exists} is-dup{/if}">
						<div class="reginfo">
							<div class="regname">{$r.name|escape} <span class="tag">{$r.role|escape}</span>{if $r.exists} <span class="tag dup">sudah ada akun</span>{/if}</div>
							<div class="sub">{$r.email|escape}{if $r.affiliation} · {$r.affiliation|escape}{/if}</div>
							{if $r.expertise}<div class="sub">Minat: {$r.expertise|escape}</div>{/if}
							{if $r.orcid || $r.phone}<div class="sub">{if $r.orcid}ORCID {$r.orcid|escape}{/if}{if $r.phone} · ☎ {$r.phone|escape}{/if}</div>{/if}
						</div>
						<div class="regact">
							{if $r.exists}<button class="btn sec" type="button" disabled title="Email ini sudah punya akun OJS">sudah ada</button>
							{else}<button class="btn login reg-fill" type="button"
								data-u="{$r.username|escape}" data-e="{$r.email|escape}" data-g="{$r.given|escape}" data-f="{$r.family|escape}"
								data-aff="{$r.affiliation|escape}" data-orcid="{$r.orcid|escape}" data-int="{$r.expertise|escape}"
								data-phone="{$r.phone|escape}" data-gid="{$r.groupId}">Isi formulir →</button>{/if}
						</div>
					</div>
				{/foreach}
				</div>
				{/if}
			{/if}
		</div>
	</details>
	{/if}

	<div class="stats">
		<div class="stat"><span>{if $umScoped}Total akun di jurnal ini{else}Total akun di server{/if}</span><b>{$umAll} <small>akun</small></b></div>
		<div class="stat"><span>{if $umQ}Hasil pencarian{else}Ditampilkan{/if}</span><b>{$umTotal} <small>{if $umQ}cocok dengan “{$umQ|escape}”{else}akun, terbaru dulu{/if}</small></b></div>
		<div class="stat"><span>Halaman</span><b>{$umPage} <small>dari {$umPages}</small></b></div>
	</div>

	{if $umFlash && $umFlash.ok}<div class="note ok"><b>✓</b><span>{$umFlash.ok|escape}</span></div>{/if}
	{if $umFlash && $umFlash.err}<div class="note err"><b>!</b><span>{$umFlash.err|escape}</span></div>{/if}
	{if $umScoped}<div class="note info"><b>🔐</b><span>Anda masuk sebagai <strong>editor jurnal</strong>. Yang tampil hanya akun yang punya peran di jurnal ini.
		Karena satu akun bisa dipakai di beberapa jurnal di server yang sama, akun yang <strong>juga terdaftar di jurnal lain</strong>, akun Manager/Editor, dan akun Site Admin
		dikunci 🔒 — hanya Site Administrator yang bisa mengubahnya.</span></div>{/if}
	<div class="note info"><b>i</b><span><strong>Hapus</strong> hanya untuk akun tanpa jejak aktivitas. Akun yang pernah mengirim naskah, me-review, atau menangani naskah harus
		<strong>digabungkan</strong> ke akun lain — semua naskah, review, dan perannya dipindahkan, lalu akun lama dihapus. Kedua tindakan tidak bisa dibatalkan; setiap tindakan dicatat.</span></div>

	{if $umRows}
	<div class="list">
	{foreach from=$umRows item=u}
		<div class="user{if $u.isAdmin} is-admin{/if}{if $u.disabled} is-off{/if}">
			<div class="ava">{$u.username|truncate:2:"":true|escape}</div>
			<div>
				<div class="uname">{$u.username|escape}
					{if $u.isAdmin}<span class="tag admin">Site Admin</span>{/if}
					{if $u.isMe}<span class="tag me">Anda</span>{/if}
					{if $u.disabled}<span class="tag off">Nonaktif</span>{/if}
					{if $u.lock}<span class="tag lock" title="{$u.lock|escape}">🔒 terkunci</span>{/if}
				</div>
				<div class="sub">{if $u.name}{$u.name|escape} · {/if}{$u.email|escape}</div>
				<div class="sub">#{$u.id} · daftar {$u.registered|escape}{if $u.lastLogin} · login {$u.lastLogin|escape}{/if}</div>
			</div>
			<div class="c-roles">
				<span class="lbl">Peran</span>
				<div class="chips">{foreach from=$u.roles item=r}<span class="chip">{$r|escape}</span>{foreachelse}<span class="none">tanpa peran</span>{/foreach}</div>
			</div>
			<div class="c-act act">
				<span class="lbl">Jejak aktivitas</span>
				{if $u.activityText}<span class="busy">{$u.activityText|escape}</span>{else}<span class="clean">✓ bersih — boleh dihapus</span>{/if}
			</div>
			<div class="btns">
				{if $u.canLoginAs}
				<form method="post" action="{$umSaveUrl|escape}" data-confirm="loginas" data-username="{$u.username|escape}" style="display:inline">
					{csrf}
					<input type="hidden" name="verb" value="loginas"><input type="hidden" name="userId" value="{$u.id}">
					<input type="hidden" name="q" value="{$umQ|escape}"><input type="hidden" name="p" value="{$umPage}">
					<button class="btn login" type="submit" title="Buka OJS sebagai pengguna ini. Dicatat di log atas nama Anda.">🔑 Masuk sebagai</button>
				</form>
				{/if}
				{if $u.canEditName}
				<details class="pop">
					<summary class="btn sec">✎ Username</summary>
					<form class="popbox" method="post" action="{$umSaveUrl|escape}">
						{csrf}
						<input type="hidden" name="verb" value="username"><input type="hidden" name="userId" value="{$u.id}">
						<input type="hidden" name="q" value="{$umQ|escape}"><input type="hidden" name="p" value="{$umPage}">
						<label for="un{$u.id}">Username baru untuk #{$u.id}</label>
						<input type="text" id="un{$u.id}" name="newUsername" value="{$u.username|escape}" maxlength="32" required pattern="[A-Za-z0-9]+([\-_][A-Za-z0-9]+)*" autocomplete="off">
						<p>Huruf, angka, dan - atau _ (tanpa spasi), maks. 32 karakter. Pemilik akun login dengan username baru; password tidak berubah.</p>
						<div class="row"><button class="btn" type="submit">Simpan</button></div>
					</form>
				</details>
				{else}
				<button class="btn sec" type="button" disabled title="{$u.lock|escape}">✎ Username</button>
				{/if}
				{if $u.canMerge}
				<details class="pop">
					<summary class="btn sec">⇄ Gabungkan</summary>
					<form class="popbox" method="post" action="{$umSaveUrl|escape}" data-confirm="merge" data-username="{$u.username|escape}">
						{csrf}
						<input type="hidden" name="verb" value="merge"><input type="hidden" name="userId" value="{$u.id}"><input type="hidden" name="confirm" value="">
						<input type="hidden" name="q" value="{$umQ|escape}"><input type="hidden" name="p" value="{$umPage}">
						<label for="mg{$u.id}">Gabungkan “{$u.username|escape}” ke username{if $umScoped} (akun di jurnal ini){/if}:</label>
						<input type="text" id="mg{$u.id}" name="target" required placeholder="username akun tujuan" autocomplete="off">
						<p>Semua naskah, review, peran, dan riwayat “{$u.username|escape}” dipindahkan ke akun tujuan, lalu akun “{$u.username|escape}” dihapus.</p>
						<div class="row"><button class="btn" type="submit">Gabungkan…</button></div>
					</form>
				</details>
				{else}
				<button class="btn sec" type="button" disabled title="{if $u.lock}{$u.lock|escape}{elseif $u.isMe}Akun Anda sendiri{else}Akun Site Admin{/if}">⇄ Gabungkan</button>
				{/if}
				{if $u.canDelete}
				<form method="post" action="{$umSaveUrl|escape}" data-confirm="delete" data-username="{$u.username|escape}" style="display:inline">
					{csrf}
					<input type="hidden" name="verb" value="delete"><input type="hidden" name="userId" value="{$u.id}"><input type="hidden" name="confirm" value="">
					<input type="hidden" name="q" value="{$umQ|escape}"><input type="hidden" name="p" value="{$umPage}">
					<button class="btn del" type="submit">✕ Hapus</button>
				</form>
				{else}
				<button class="btn del" type="button" disabled title="{if $u.lock}{$u.lock|escape}{elseif $u.isMe}Akun Anda sendiri{elseif $u.isAdmin}Akun Site Admin tidak bisa dihapus{else}Punya jejak aktivitas — gunakan Gabungkan{/if}">✕ Hapus</button>
				{/if}
			</div>
		</div>
	{/foreach}
	</div>

	<div class="pager">
		<span>Menampilkan halaman {$umPage} dari {$umPages}</span>
		<span>
			{if $umPrev}<a class="btn sec" href="{$umPrev|escape}">← Sebelumnya</a>{/if}
			{if $umNext}<a class="btn sec" href="{$umNext|escape}">Berikutnya →</a>{/if}
		</span>
	</div>
	{else}
	<div class="empty">Tidak ada akun yang cocok{if $umQ} dengan “{$umQ|escape}”{/if}.</div>
	{/if}

	<details class="box">
		<summary>Catatan tindakan <small>30 terakhir — siapa mengubah apa</small></summary>
		{if $umLog}
		<table class="log">
			<tr><th>Waktu</th><th>Oleh</th><th>Tindakan</th><th>Detail</th></tr>
			{foreach from=$umLog item=l}
			<tr><td>{$l.time|escape}</td><td>{$l.by|escape}</td><td>{$l.action|escape}</td><td>{$l.detail|escape}</td></tr>
			{/foreach}
		</table>
		{else}<p style="padding:0 16px 14px;color:#5e6580">Belum ada tindakan.</p>{/if}
	</details>
{/if}
</main>

<script>
{literal}
(function () {
	// hapus & gabung wajib konfirmasi dengan mengetik username
	document.addEventListener('submit', function (ev) {
		var f = ev.target, kind = f.getAttribute('data-confirm');
		if (!kind) return;
		var u = f.getAttribute('data-username');
		if (kind === 'loginas') {
			if (!confirm('Masuk ke OJS sebagai "' + u + '"?\n\nAnda akan melihat dan memakai OJS sebagai pengguna ini. Tindakan ini dicatat di log atas nama Anda.\nUntuk kembali ke akun Anda, klik "Logout as" pada pemberitahuan OJS di bagian atas halaman.')) { ev.preventDefault(); return; }
			var lb = f.querySelector('button[type=submit]'); if (lb) { lb.disabled = true; lb.textContent = 'Masuk…'; }
			return;
		}
		var msg = kind === 'delete'
			? 'HAPUS akun "' + u + '" secara permanen?\n\nKetik username-nya untuk konfirmasi:'
			: 'GABUNGKAN akun "' + u + '" ke "' + (f.querySelector('[name=target]') || {}).value + '"?\nAkun "' + u + '" akan dihapus setelah semua datanya dipindahkan.\n\nKetik username "' + u + '" untuk konfirmasi:';
		var typed = window.prompt(msg, '');
		if (typed === null || typed.trim().toLowerCase() !== u.toLowerCase()) {
			ev.preventDefault();
			if (typed !== null) alert('Username tidak cocok. Tidak ada yang diubah.');
			return;
		}
		f.querySelector('[name=confirm]').value = typed.trim();
		var b = f.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Memproses…'; }
	});
	// buka kunci: kirim terenkode di "umk" (server membuang parameter bernama password)
	var uf = document.getElementById('umUnlock');
	if (uf) uf.addEventListener('submit', function () {
		var v = document.getElementById('umKey').value;
		uf.querySelector('[name=umk]').value = btoa(unescape(encodeURIComponent(v))).replace(/\+/g, '-').replace(/\//g, '_');
		var b = uf.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Memeriksa…';
	});
	// tambah akun: password acak, salin, dan kirim terenkode (server membuang parameter bernama password)
	var pass = document.getElementById('nuPass');
	if (pass) {
		document.getElementById('nuGen').addEventListener('click', function () {
			var c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '', a = new Uint32Array(10);
			window.crypto.getRandomValues(a);
			for (var i = 0; i < a.length; i++) out += c[a[i] % c.length];
			pass.value = out;
		});
		document.getElementById('nuCopy').addEventListener('click', function () {
			pass.select();
			try { navigator.clipboard.writeText(pass.value); } catch (e) { document.execCommand('copy'); }
		});
		document.getElementById('umAddForm').addEventListener('submit', function (ev) {
			var f = ev.target;
			if (pass.value.length < 6) { ev.preventDefault(); alert('Password minimal 6 karakter.'); pass.focus(); return; }
			if (!f.querySelector('input[name="groups[]"]:checked')) { ev.preventDefault(); alert('Pilih minimal satu peran di jurnal ini.'); return; }
			f.querySelector('[name=npk]').value = btoa(unescape(encodeURIComponent(pass.value))).replace(/\+/g, '-').replace(/\//g, '_');
			var b = f.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Membuat…';
		});
	}
	// prefill formulir Tambah akun dari baris pendaftaran Google Sheet
	document.addEventListener('click', function (ev) {
		var b = ev.target && ev.target.closest ? ev.target.closest('.reg-fill') : null;
		if (!b) return;
		var set = function (id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; };
		set('nuUser', b.getAttribute('data-u'));
		set('nuEmail', b.getAttribute('data-e'));
		set('nuGiven', b.getAttribute('data-g'));
		set('nuFamily', b.getAttribute('data-f'));
		set('nuAff', b.getAttribute('data-aff'));
		set('nuOrcid', b.getAttribute('data-orcid'));
		set('nuInt', b.getAttribute('data-int'));
		set('nuPhone', b.getAttribute('data-phone'));
		var gid = b.getAttribute('data-gid');
		var boxes = document.querySelectorAll('input[name="groups[]"]');
		for (var i = 0; i < boxes.length; i++) boxes[i].checked = (!!gid && gid !== '0' && boxes[i].value === gid);
		var d = document.getElementById('umAdd'); if (d) { d.open = true; d.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		var ps = document.getElementById('nuPass'), gen = document.getElementById('nuGen');
		if (gen && ps && !ps.value) gen.click();
	});
	// hanya satu popup terbuka; klik di luar menutupnya
	document.addEventListener('click', function (ev) {
		var pops = document.querySelectorAll('details.pop[open]');
		for (var i = 0; i < pops.length; i++) if (!pops[i].contains(ev.target)) pops[i].removeAttribute('open');
	});
})();
{/literal}
</script>
</body>
</html>
