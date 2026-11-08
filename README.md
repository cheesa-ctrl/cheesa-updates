# CHEESA Updates

Sumber paket update untuk **CHEESA Control Panel** (OJS jurnal CHEESA).
Semua yang ada di sini dibuat dan ditandatangani otomatis oleh GitHub Actions. Jangan edit `manifest.json` atau `*.tar.gz` dengan tangan.

## Menambah atau memperbarui plugin

Pilih salah satu:

- **Arsip plugin** (`.tar.gz` / `.tgz` / `.zip`, misalnya dari PKP Plugin Gallery atau halaman rilis pengembang):
  buka folder `incoming/`, klik **Add file › Upload files**, seret arsipnya, lalu klik **Commit changes**.
- **Folder plugin**: buka folder `plugins/`, klik **Add file › Upload files**, seret folder pluginnya, lalu klik **Commit changes**.
  Untuk update, naikkan dulu nomor `<release>` di `version.xml`.

Sekitar 1–2 menit kemudian robot selesai (lihat tab **Actions**: tanda centang hijau). Setelah itu, di OJS buka **CHEESA Control Panel**, klik **Cek update**, lalu klik **Pasang/Update**.

Syarat plugin: berisi `version.xml` dengan `<application>` dan `<type>plugins.…</type>`.
Hanya masukkan plugin dari sumber tepercaya. Semua yang masuk ke repo ini akan bisa dipasang ke situs jurnal.

## Isi repo

| Folder/berkas | Isi |
|---|---|
| `plugins/` | Sumber plugin (satu folder per plugin) |
| `incoming/` | Tempat menaruh arsip plugin; dibongkar otomatis ke `plugins/` |
| `tools/` | Skrip rilis dan lisensi CHEESA (`cheesa.lic`) |
| `manifest.json`, `manifest.json.sig`, `*.tar.gz` | Hasil robot, dibaca oleh CHEESA Control Panel |
