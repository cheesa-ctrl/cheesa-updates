{* gaya bersama panel & kartu kunci *}
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
	.ccp-mask { -webkit-text-security: disc; text-security: disc; }
	.ccp-help { margin: 10px 0 0; font-size: 12.5px; color: var(--ccp-muted); }

	.ccp-lock { display: grid; place-items: center; padding: 30px 0; }
	.ccp-lockcard { width: min(420px, 100%); padding: 28px; background: #fff; border: 1px solid var(--ccp-line); border-radius: 16px; box-shadow: 0 18px 40px rgba(3,2,113,.10); text-align: center; }
	.ccp-lockico { width: 56px; height: 56px; margin: 0 auto 10px; border-radius: 16px; display: grid; place-items: center; font-size: 26px;
		background: linear-gradient(135deg, var(--ccp-navy) 0, var(--ccp-navy-2) 100%); box-shadow: 0 8px 20px rgba(3,2,113,.25); }
	.ccp-lockcard h2 { margin: 0 0 4px; font-size: 19px; color: var(--ccp-navy); }
	.ccp-lockcard p { margin: 0 0 16px; color: var(--ccp-muted); font-size: 13px; }
	.ccp-lockcard .ccp-note { text-align: left; }
	.ccp-lockrow { display: flex; gap: 6px; }
	.ccp-lockrow input { flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid #cdd1e1; border-radius: 9px; font-size: 15px; font-family: inherit; }
	.ccp-lockrow input:focus { outline: 2px solid rgba(3,2,113,.25); border-color: var(--ccp-navy); }
	.ccp-lockcard small { display: block; margin-top: 12px; color: var(--ccp-muted); font-size: 12px; }
	@media (max-width: 1100px) {
		.ccp-item { grid-template-columns: 44px minmax(0, 1fr) minmax(0, 1fr); }
		.ccp-item .ccp-info { grid-column: 2 / -1; }
		.ccp-item .ccp-ver.inst { grid-column: 2; }
		.ccp-item .ccp-actions { grid-column: 1 / -1; justify-content: flex-start; }
	}
	@media (max-width: 560px) { .ccp-stats { grid-template-columns: 1fr; } }
</style>
{/literal}
