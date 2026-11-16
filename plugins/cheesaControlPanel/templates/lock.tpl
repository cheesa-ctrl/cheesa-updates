{**
 * plugins/generic/cheesaControlPanel/templates/lock.tpl
 * Kartu kata sandi sebelum panel dibuka (modal & halaman penuh).
 *}
<div id="cheesaCP" class="ccp{if $cpFullPage} ccp-full{/if}" data-action="{$cpAction|escape}" data-csrf="{csrf type="raw"}" data-fullpage="{if $cpFullPage}1{/if}">
{include file=$cpStyleTpl}
<div class="ccp-lock">
	<div class="ccp-lockcard">
		<div class="ccp-lockico">🔒</div>
		<h2>CHEESA Control Panel</h2>
		<p>Panel ini dilindungi kata sandi. Masukkan kata sandi untuk mengelola update dan plugin.</p>
		{if $cpLockErr}<div class="ccp-note err">{$cpLockErr|escape}</div>{/if}
		<div class="ccp-lockrow">
			{* type=text + disamarkan lewat CSS: browser tidak mengisi otomatis / menawarkan simpan password *}
			<input type="text" id="cpPanelKey" class="ccp-mask" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="Kata sandi" autofocus>
			<button type="button" class="ccp-btn sec" title="Tampilkan / sembunyikan" onclick="document.getElementById('cpPanelKey').classList.toggle('ccp-mask')">👁</button>
		</div>
		<button type="button" class="ccp-btn" data-cp-verb="unlockPanel" style="width:100%;justify-content:center;margin-top:12px">Buka</button>
		<small>Terkunci lagi otomatis setelah 30 menit tanpa aktivitas atau saat logout.</small>
	</div>
</div>
{include file=$cpScriptTpl}
</div>
