# Arsip Review & Deteksi Judol — plugin OJS 3.3

Plugin generic untuk OJS 3.3.x dengan dua fungsi:

1. **Tab "Arsip Review"** di alur kerja naskah, sejajar dengan Submission, Review, Copyediting, dan Production.
2. **Deteksi judi online (judol)** pada berkas Native XML sebelum diimpor.

## Pemasangan

1. Masuk sebagai Journal Manager atau Administrator.
2. Buka **Website Settings › Plugins › Upload A New Plugin**, lalu unggah `reviewArchive.tar.gz`.
   (Bisa juga dengan mengekstrak folder `reviewArchive` ke `plugins/generic/` di server.)
3. Aktifkan **Arsip Review & Deteksi Judol** di daftar *Generic Plugins*.
4. Jika tab baru belum muncul, buka **Administration › Clear Template Cache**.
   Plugin sudah membersihkan cache sendiri saat diaktifkan atau dinonaktifkan.

## 1. Tab "Arsip Review"

Tab ini menampilkan dua daftar:

- **Berkas Review**: semua berkas yang namanya mengandung `[REVIEW]`.
  Ini cocok dengan berkas XML yang sudah disiapkan dengan awalan `[REVIEW]`
  dan stage `submission`. Berkas tersebut juga tetap tampil di tab Submission.
- **Berkas Tahap Review & Lampiran Lainnya**: berkas berstage `review_file`,
  `review_attachment`, `review_revision`, `attachment`, `query`, dan `note`.
  Setelah impor Native XML, berkas seperti ini biasanya tidak tampil di mana pun
  karena ronde review, penugasan reviewer, dan diskusi tidak ikut terimpor.
  Ini juga mencakup artikel yang sudah diimpor sebelumnya tanpa awalan `[REVIEW]`.

Setiap berkas bisa diunduh, termasuk **semua revisinya** (tombol "Terbaru",
"Revisi 1", "Revisi 2", dan seterusnya).

Tab ini hanya terlihat oleh Journal Manager, Section Editor, dan Assistant
yang punya akses ke naskah tersebut.

Catatan: plugin ini **tidak membuat ulang ronde review**. Riwayat review asli
(reviewer, rekomendasi, keputusan editor) hanya bisa dipindahkan lewat migrasi
database OJS secara penuh, bukan lewat Native XML.

## 2. Deteksi judol saat impor Native XML

Saat Anda menekan **Import** di *Tools › Import/Export › Native XML Plugin*,
plugin memindai berkas XML **sebelum** diimpor:

- metadata: judul, abstrak, kata kunci, daftar pustaka, URL, dan atribut;
- berkas yang tertanam (base64): `.doc` (termasuk teks UTF-16), `.docx`/`.odt`
  (termasuk tautan), `.pdf` (aliran teks terkompresi dan tautan `/URI`),
  `.html`, dan `.txt`;
- teks yang disamarkan, misalnya `s l o t g a c o r`, `g.a.c.o.r`, atau `g4c0r`;
- kode tersembunyi di metadata, misalnya `<script>`, `<iframe>`, `display:none`,
  atau teks berukuran 0.

Jika ada temuan, **impor dibatalkan** dan muncul laporan berisi lokasi temuan
(artikel, berkas, atau bagian metadata), kata yang ditemukan, dan kutipannya.
Setiap pemblokiran juga dicatat di log error PHP server.

Arsip `.rar` dan `.7z` tidak dapat dibuka, sehingga dicatat sebagai
"tidak dapat dipindai".

### Menghindari salah deteksi

Daftar polanya dibuat hati-hati supaya istilah ilmiah tidak ikut tertangkap.
Kata seperti *deposit*, *Olympus* (mikroskop), *roulette wheel*, *bandar udara*,
*harga cor*, *jackpot*, *habanero*, *slot waktu*, atau *RTP* hanya dianggap judol
bila muncul dalam kombinasi khas judol, misalnya *slot gacor*, *bandar togel*,
*deposit pulsa*, atau *rtp live*.

Plugin ini sudah diuji pada 28 berkas XML CHEESA tanpa satu pun salah deteksi,
dan menangkap semua contoh judol yang disisipkan secara sengaja.

Daftar pola bisa diubah di `classes/JudolScanner.inc.php` (konstanta `PATTERNS`).

### Memindai tanpa OJS (baris perintah)

```
php plugins/generic/reviewArchive/classes/JudolScanner.inc.php berkas1.xml berkas2.xml
```

Kode keluar: `0` = bersih, `1` = ada temuan, `2` = berkas tidak dapat dibaca.

### Batasan

- Hanya impor lewat antarmuka web (Native XML Plugin) yang diperiksa.
  Impor lewat CLI `tools/importExport.php` tidak diperiksa; untuk itu, jalankan
  pemindai baris perintah di atas terlebih dahulu.
- Teks di dalam gambar (misalnya spanduk judol berbentuk gambar) tidak dapat dibaca.
- PDF dengan font yang dienkode khusus (CID/Type0) mungkin tidak terbaca
  teksnya, tetapi tautan `/URI`-nya tetap diperiksa.

## Struktur

```
reviewArchive/
├── ReviewArchivePlugin.inc.php        hook: tab, handler, pemeriksaan impor
├── classes/JudolScanner.inc.php       mesin pemindai (juga bisa dijalankan via CLI)
├── controllers/tab/ReviewArchiveTabHandler.inc.php
├── templates/submissionProgressBar.tpl  salinan template inti + tab baru
├── templates/reviewArchiveTab.tpl
├── templates/judolReport.tpl
├── locale/id_ID/locale.po, locale/en_US/locale.po
├── index.php, version.xml
```

Tab baru ditambahkan dengan menimpa template inti
`lib/pkp/templates/workflow/submissionProgressBar.tpl` (OJS 3.3). Jika plugin lain
juga menimpa template yang sama, hanya salah satu yang akan berlaku.
