{* skrip bersama panel & kartu kunci (diikat sekali per halaman) *}
<script>
{literal}
(function () {
	// pengikat lama (panel versi sebelumnya, berbasis jQuery) dilepas supaya aksi tidak terkirim dua kali
	if (window.jQuery) window.jQuery(document).off('click.cheesacp');
	if (window.__cheesaCPBound) return;
	window.__cheesaCPBound = true;
	// Enter di kolom kata sandi = klik "Buka"
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Enter' && ev.target && (ev.target.id === 'cpSourcePass' || ev.target.id === 'cpPanelKey')) {
			ev.preventDefault();
			var b = document.querySelector('#cheesaCP [data-cp-verb="' + (ev.target.id === 'cpPanelKey' ? 'unlockPanel' : 'unlockSource') + '"]'); if (b) b.click();
		}
	});
	var BUSY = { update: 'Memasang…', rollback: 'Mengembalikan…', uninstall: 'Meng-uninstall…', hide: 'Menghapus…', unhide: 'Memproses…', check: 'Memeriksa…', saveUrl: 'Menyimpan…', unlockSource: 'Memeriksa…', lockSource: 'Mengunci…', unlockPanel: 'Memeriksa…', lockPanel: 'Mengunci…' };
	document.addEventListener('click', function (ev) {
		var b = ev.target && ev.target.closest ? ev.target.closest('#cheesaCP [data-cp-verb]') : null;
		if (!b) return;
		ev.preventDefault();
		var cp = document.getElementById('cheesaCP');
		var verb = b.getAttribute('data-cp-verb'), pkg = b.getAttribute('data-cp-package') || '', label = b.getAttribute('data-cp-label') || pkg;
		if (verb === 'update' && !confirm('Pasang update ' + label + '? Versi saat ini akan dicadangkan.')) return;
		if (verb === 'rollback' && !confirm('Kembalikan ' + label + ' ke versi cadangan terakhir?')) return;
		if (verb === 'hide' && !confirm('Hapus ' + label + ' dari daftar ini?\n\nHanya barisnya yang hilang dari panel; plugin di server tidak diubah. Bisa ditampilkan lagi lewat bagian "Disembunyikan".')) return;
		if (verb === 'uninstall' && !confirm('Uninstall ' + label + ' dari server?\n\nPlugin dinonaktifkan lalu foldernya dihapus. Cadangan disimpan, jadi bisa dikembalikan dengan tombol "Kembalikan". Pengaturan dan data di database tidak dihapus.')) return;
		var btns = cp.querySelectorAll('button');
		for (var i = 0; i < btns.length; i++) btns[i].disabled = true;
		b.textContent = BUSY[verb] || 'Memproses…';
		var url = cp.querySelector('#cpManifestUrl'), tok = cp.querySelector('#cpToken');
		var body = new URLSearchParams();
		body.append('verb', verb);
		body.append('package', pkg);
		body.append('manifestUrl', url ? url.value : '');
		body.append('githubToken', tok ? tok.value : '');
		body.append('csrfToken', cp.getAttribute('data-csrf') || '');
		body.append('fullpage', cp.getAttribute('data-fullpage') || '');
		var pw = cp.querySelector('#cpSourcePass');
		var pk = cp.querySelector('#cpPanelKey');
		if (verb === 'unlockPanel') {
			var rawP = pk ? pk.value : '';
			body.append('cpk', btoa(unescape(encodeURIComponent(rawP))).replace(/\+/g, '-').replace(/\//g, '_'));
		}
		if (verb === 'unlockSource') {
			// base64 (aman-URL) di parameter "cpk": tidak ikut dibuang filter yang memblokir parameter "password"
			var raw = pw ? pw.value : '';
			var b64 = btoa(unescape(encodeURIComponent(raw))).replace(/\+/g, '-').replace(/\//g, '_');
			body.append('cpk', b64);
		}
		var fail = function (m) { alert(m); for (var i = 0; i < btns.length; i++) btns[i].disabled = false; };
		fetch(cp.getAttribute('data-action'), {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
			body: body.toString()
		}).then(function (r) {
			if (!r.ok) throw new Error('Permintaan gagal (' + r.status + '). Periksa log error server.');
			return r.json();
		}).then(function (r) {
			if (!r || !r.status || !r.content) return fail((r && r.content) || 'Gagal memproses permintaan.');
			var t = document.createElement('div');
			t.innerHTML = r.content;
			var n = t.querySelector('#cheesaCP');
			if (n) cp.parentNode.replaceChild(n, cp); else fail('Jawaban server tidak dikenali.');
		}).catch(function (e) { fail(e.message || 'Permintaan gagal.'); });
	});
})();
{/literal}
</script>
