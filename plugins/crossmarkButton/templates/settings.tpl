{**
 * plugins/generic/crossmarkButton/templates/settings.tpl
 *}
<script>
	$(function() {ldelim}
		$('#crossmarkSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="crossmarkSettings" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	<p style="padding:10px 12px;background:#f4f7fb;border-left:4px solid #006798">
		Tombol hanya muncul pada artikel yang memiliki DOI. Pastikan jurnal sudah terdaftar layanan Crossmark di Crossref
		dan metadata Crossmark ikut dideposit; bila belum, tombol tetap tampil tetapi pop-up Crossmark tidak berisi status.
	</p>
	<div style="margin:0 0 14px"><label style="display:block;font-weight:600;margin-bottom:4px">Bentuk logo</label>
		<select name="logo">
			<option value="horizontal"{if $cmk.logo!="stacked"} selected{/if}>Horizontal (logo + "Check for updates")</option>
			<option value="stacked"{if $cmk.logo=="stacked"} selected{/if}>Ikon saja</option>
		</select></div>
	<div style="margin:0 0 14px"><label style="display:block;font-weight:600;margin-bottom:4px">Posisi</label>
		<select name="position">
			<option value="title"{if $cmk.position!="details"} selected{/if}>Dekat judul artikel</option>
			<option value="details"{if $cmk.position=="details"} selected{/if}>Di area isi artikel (tempat bawaan)</option>
		</select></div>
	<div style="margin:0 0 14px"><label style="display:block;font-weight:600;margin-bottom:4px">Hanya artikel terbit sejak (opsional, YYYY-MM-DD)</label>
		<input type="text" name="minDate" value="{$cmk.minDate|escape}" placeholder="2024-01-01" style="width:200px"></div>
	{fbvFormButtons submitText="common.save"}
</form>
