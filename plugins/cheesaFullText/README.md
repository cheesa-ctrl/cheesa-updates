# CHEESA Full Text (OJS 3.3)

Menampilkan naskah **JATS XML** sebagai teks lengkap HTML — tanpa library tambahan.

## Yang dilakukan
- **Halaman artikel:** bagian *Full Text* (di CHEESA Theme menjadi tab "Full Text" sesudah Abstract) berisi
  daftar isi, teks per bagian, gambar, tabel, rumus (MathML / TeX), catatan, dan referensi.
- **Tombol galley XML:** membuka halaman baca yang rapi (daftar isi menempel di samping), bukan mengunduh XML mentah.
  Tombol "Download XML" tetap tersedia di halaman baca.
- Pratinjau referensi saat kursor di atas [1], pengatur ukuran huruf (A− / A+), lompat ke gambar/tabel/rujukan.
- Akses mengikuti aturan OJS (jurnal berlangganan: teks lengkap hanya untuk yang berhak).

## Cara pakai per artikel
1. Tahap **Production** > **Galleys** > Add galley, label `XML`, unggah berkas JATS XML (`.xml`).
2. Gambar: pada galley XML yang sama, unggah gambar sebagai **dependent files**, dengan nama berkas
   persis seperti di `<graphic xlink:href="...">` (mis. `figure1.png`).
3. Terbitkan / simpan. Teks lengkap langsung tampil.

## Catatan
- Hasil konversi disimpan di cache (`files_dir/cheesaFullText/cache`) dan dibuat ulang otomatis bila berkas XML diganti.
- Rumus TeX (`<tex-math>`) ditampilkan dengan MathJax (dimuat hanya bila ada rumus TeX). MathML tampil langsung di browser.
- Keamanan: HTML dibangun ulang dari daftar elemen yang diizinkan; skrip, atribut event, gaya inline,
  dan tautan `javascript:` di dalam XML tidak pernah ikut tampil. Entitas eksternal XML tidak dimuat.
- Matikan plugin JATS Parser / Lens Galley bila terpasang, supaya tidak dobel.
