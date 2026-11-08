# CHEESA Novelty Theme (OJS 3.3)

Child theme dari Default Theme dengan tata letak 3 kolom bergaya Novelty dan warna CHEESA.

## Pasang
1. **Backup dulu**: salin isi *Journal style sheet*, *Header Content*, dan *Additional content in footer*
   (Settings > Website > Appearance > Advanced) ke file teks.
2. Settings > Website > Plugins > **Upload A New Plugin** → pilih `cheesaNovelty-1.0.0.tar.gz` → Save.
3. Aktifkan **CHEESA Novelty Theme** (kategori Theme Plugins).
4. Settings > Website > Appearance > **Theme** → pilih *CHEESA Novelty Theme* → Save.
5. Muat ulang beranda (Ctrl+F5).

## Kode lama
Stylesheet dan script CHEESA lama sudah dibawa di dalam tema (`styles/cheesa-legacy.css`, `js/cheesa-legacy.js`).
- Tema langsung bekerja walau kode lama masih ada di Appearance: script yang sama di tema otomatis dilewati.
- Setelah tampilan dicek, kode lama boleh dirapikan:
  - *Journal style sheet*: hapus file stylesheet (sudah ada di tema).
  - *Header Content*: hapus semua blok `<script>`/`<style>` CHEESA.
  - *Additional content in footer*: hapus blok `<script>`/`<style>`, **tetap simpan HTML footer** (alamat, kontak, ticker, View Statistic).
- Bila kode lama ingin tetap dipakai dari Appearance, set opsi *Kode CHEESA lama* = "Jangan muat".

## Opsi tema (Appearance > Theme)
- Warna utama & aksen
- Urutan blok kolom kiri / kanan (judul blok, dipisah koma)
- Tautan tombol *Submit Your Paper* dan *Become a Reviewer*
- Media sosial (`Nama|URL` per baris)
- Logo indeksasi (`Nama|URL logo|URL tautan` per baris; kosong = dibuat dari blok *CHEESA Profiles*)
- Tab *Upcoming Issue* (terbitan belum terbit berikutnya + artikel terjadwal)
- Daftar artikel Current Issue di beranda

## Copot / kembali
Pilih kembali *Default Theme* di Appearance > Theme, lalu tempel lagi backup kode lama bila sudah dihapus.
