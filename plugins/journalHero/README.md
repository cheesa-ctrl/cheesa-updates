# Journal Hero — plugin deskripsi jurnal untuk OJS 3.3

Menampilkan banner deskripsi jurnal **tepat di bawah header/menu**, apa pun tema yang dipakai:
judul, deskripsi, tiga keunggulan, lencana peringkat akreditasi, p-ISSN/e-ISSN, carousel sampul
terbitan terbaru, serta strip fitur (OJS, Dewan Redaksi, Kualitas & Integritas) dengan garis gelombang emas.

## Pasang
1. **Website > Plugins > Upload A New Plugin**, pilih `journalHero.tar.gz`.
2. Aktifkan **Journal Hero (Deskripsi Jurnal)** di daftar Generic Plugins.
3. Klik panah di sampingnya > **Settings** untuk mengatur isi dan warna.
4. Jika belum muncul: **Administration > Clear Template Cache**.

## Pengaturan
- **Gaya**: *Carousel* (judul, keunggulan, carousel sampul) atau *Profil* (sampul terbitan terkini, subjudul, statistik, kotak info akreditasi/ISSN/DOI/lisensi/frekuensi, kartu Kirim Naskah).
- Gaya Profil: subjudul, statistik (maks. 4 baris `nilai | label`), prefix DOI (otomatis dari plugin DOI), lisensi (otomatis dari lisensi CC jurnal), frekuensi terbit.
- Tampil di beranda saja / semua halaman; posisi: paling atas, di atas menu (di dalam header), di bawah header, atau sebelum konten; opsi menyembunyikan logo/nama situs di header.
- Lebar: **Kotak** (bawaan, selebar konten situs, sudut membulat) atau **Penuh** selebar layar; ukuran **Ringkas** (bawaan) atau **Besar**.
- Judul, tulisan kecil di atas judul, deskripsi (kosong = ambil dari Journal Settings).
- Peringkat: label (mis. RANK) dan nilai (mis. Sinta 1).
- Gambar latar: URL, atau otomatis memakai Homepage Image.
- Warna utama, aksen, dan lencana peringkat (kode hex).
- Tampil/sembunyikan: keunggulan, ISSN, carousel sampul (jumlah 1–12), strip fitur.

Sampul terbitan memakai gambar sampul dari OJS; terbitan tanpa sampul otomatis diberi sampul berwarna.
Menonaktifkan plugin langsung menghilangkan banner tanpa mengubah tema.
