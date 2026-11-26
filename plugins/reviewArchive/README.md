# Arsip Review & Deteksi Judol — plugin OJS 3.3

Plugin generic untuk OJS 3.3.x, dibuat untuk migrasi artikel CHEESA lewat Native XML.

| Fitur | Di mana |
|---|---|
| Tab **Arsip Review**: Pernyataan Editor untuk asesor, tautan sertifikat reviewer, dan berkas proses review | Alur kerja artikel, di samping Submission / Review / Copyediting / Production |
| **Import XML Arsip Review**: unggah bertahap, cek judol, label review otomatis, buang duplikat | Tools › Import XML Arsip Review |
| Cek judol pada **Native XML Plugin** bawaan | Tools › Import/Export › Native XML Plugin |

## Pemasangan

1. Masuk sebagai Journal Manager atau Administrator.
2. Buka **Website Settings › Plugins › Upload A New Plugin**, lalu unggah `reviewArchive.tar.gz`.
3. Aktifkan **Arsip Review & Deteksi Judol** di daftar *Generic Plugins*.
4. Klik panah di samping nama plugin, lalu **Settings**. Periksa Pernyataan Editor dan daftar sertifikat (keduanya sudah terisi untuk CHEESA).
5. Jika tab baru belum muncul, buka **Administration › Clear Template Cache**.

## 1. Tab "Arsip Review"

Tab ini terlihat oleh Journal Manager, Section Editor, dan Assistant yang punya akses ke artikel, termasuk akun asesor yang diberi peran editorial. **Penulis tidak bisa melihatnya.**

Isi tab, dari atas ke bawah:

1. **Pernyataan Editor**
   - **Pernyataan umum** diisi sekali di Settings. Pernyataan ini hanya tampil pada artikel yang **tidak punya ronde review** di OJS ini, yaitu artikel hasil migrasi. Penanda `{id}`, `{doi}`, `{judul}`, dan `{jurnal}` diganti otomatis, dan tautan bisa diklik.
   - **Catatan per artikel** opsional. Editor bisa menambah atau mengubahnya langsung di tab ini. Nama pengubah terakhir dan waktunya ikut dicatat.
   - **Sertifikat reviewer**: tautan ke sertifikat di Google Drive, dicocokkan lewat ID artikel di OJS lama. ID ini diambil dari DOI, misalnya `10.25273/cheesa.v3i2.7688.88-98` menjadi `7688`. Jadi pencocokan tetap jalan meski artikel mendapat ID baru setelah import.
2. **Berkas Review**: berkas yang namanya mengandung `[REVIEW]`, di stage mana pun.
3. **Berkas Tahap Review & Lampiran Lainnya**: berkas berstage review, `attachment`, `query`, atau `note`. Setelah import Native XML, berkas seperti ini biasanya tidak tampil di mana pun.

Setiap berkas bisa diunduh, termasuk semua revisinya.

### Daftar sertifikat

Daftar ini ada di Settings dalam format CSV: `id_artikel,nomor,url,nama_file`. Plugin sudah berisi 220 sertifikat untuk 102 artikel dari folder Drive "Sertifikat Reviewer". Untuk menambah sertifikat baru, tempel baris baru di Settings.

Tautan Drive harus bisa dibuka asesor. Folder "Sertifikat Reviewer" saat ini disetel "siapa saja yang memiliki link".

## 2. Import XML Arsip Review (Tools)

Alurnya:

1. **Unggah bertahap.** Berkas dipotong per 2 MB di browser, jadi batas unggah server (`upload_max_filesize`, misalnya 8 MB) tidak berlaku. Ukuran maksimal 512 MB.
2. **Cek judol.** Seluruh XML dan berkas di dalamnya diperiksa. Jika ditemukan indikasi judol, impor diblokir dan laporan ditampilkan.
3. **Pratinjau.** Tabel per artikel menunjukkan berkas yang diberi label, dibuang, atau tetap, beserta ukuran sebelum dan sesudah.
4. **Impor.** Dijalankan dengan mesin Native XML bawaan OJS, dengan hasil dan pesan galat yang sama seperti Native XML Plugin. Jika ada galat, semua yang sempat diimpor dibatalkan.

Pilihan pengolahan:

| Pilihan | Bawaan | Keterangan |
|---|---|---|
| Label `[REVIEW]` | aktif | Untuk berkas berstage review, atau yang namanya mengandung review/reviewer/sertifikat/RV. **Stage tidak diubah**, jadi tetap aman untuk double-blind. Sertifikat diberi nama reviewer dan tanggal dari isinya, misalnya `[REVIEW] Reviewer: Salfauqi Nurman (26 Okt 2020) - …` |
| Buang duplikat | aktif | Hanya salinan yang identik byte per byte. Salinan yang paling berguna yang disimpan. |
| Buang dokumen kerja produksi | nonaktif | GF.doc, draf copyediting, inpress, dan sejenisnya yang tidak dipakai galley. |
| Buang revisi lama non-review | nonaktif | Revisi lama berkas review tetap disimpan. |

Galley, berkas dependen, dan naskah awal **tidak pernah dibuang**.

Berkas unggahan sementara disimpan di `files_dir/reviewArchive/u{userId}/`. Berkas ini dihapus setelah impor atau setelah satu hari.

## 3. Deteksi judol

Yang diperiksa:

- **Metadata:** judul, abstrak, kata kunci, daftar pustaka, URL, dan atribut.
- **Berkas tertanam:** `.doc` (termasuk UTF-16), `.docx`/`.odt` (termasuk tautan), `.pdf` (aliran teks terkompresi dan tautan `/URI`), `.html`, dan `.txt`.
- **Teks yang disamarkan:** misalnya `s l o t g a c o r`, `g.a.c.o.r`, atau `g4c0r`.
- **Kode tersembunyi di metadata:** `<script>`, `<iframe>`, `display:none`, atau teks berukuran 0.

Daftar polanya dibuat hati-hati supaya istilah ilmiah tidak ikut tertangkap. Kata seperti deposit, Olympus, roulette wheel, bandar udara, harga cor, jackpot, slot waktu, dan RTP hanya dianggap judol bila muncul dalam kombinasi khas judol.

Pemindai sudah diuji pada 28 XML CHEESA tanpa salah deteksi. Daftar pola ada di `classes/JudolScanner.inc.php` (konstanta `PATTERNS`).

Pemindaian lewat baris perintah, tanpa OJS:

```
php plugins/generic/reviewArchive/classes/JudolScanner.inc.php berkas.xml
php plugins/generic/reviewArchive/classes/ReviewArchiveXmlProcessor.inc.php masuk.xml keluar.xml [--drop-production] [--drop-old-revisions]
```

## Batasan

- Plugin **tidak membuat ulang ronde review atau penugasan reviewer**. Native XML tidak membawa data itu. Riwayat review asli hanya bisa dipindahkan lewat migrasi database secara penuh.
- Impor lewat CLI `tools/importExport.php` tidak diperiksa judolnya. Jalankan pemindai baris perintah terlebih dahulu.
- Isi `.rar`/`.7z` dan teks di dalam gambar tidak dapat dipindai.
- Tab baru ditambahkan dengan menimpa template inti `lib/pkp/templates/workflow/submissionProgressBar.tpl`. Jika plugin lain juga menimpa template yang sama, hanya salah satu yang berlaku.

## Struktur

```
reviewArchive/
├── ReviewArchivePlugin.inc.php            hook, pengaturan, cek judol Native XML
├── ReviewArchiveSettingsForm.inc.php      Settings: pernyataan umum + daftar sertifikat
├── classes/TextExtractor.inc.php          baca teks dari pdf/doc/docx tanpa alat eksternal
├── classes/JudolScanner.inc.php           pemindai judol (juga CLI)
├── classes/ReviewArchiveXmlProcessor.inc.php  label review, nama reviewer, buang duplikat (juga CLI)
├── controllers/tab/ReviewArchiveTabHandler.inc.php   tab Arsip Review + simpan catatan
├── pages/ReviewArchiveImportHandler.inc.php           halaman import (unggah bertahap, analisis, impor)
├── templates/                             tampilan
├── data/certificates.csv                  220 sertifikat reviewer (dari Google Drive)
├── data/defaultStatement.txt              pernyataan umum bawaan
├── locale/id_ID, locale/en_US
└── index.php, version.xml
```
