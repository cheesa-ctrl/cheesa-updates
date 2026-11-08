# Journal Portal Widgets (OJS 3.3)

Plugin generic yang menambahkan 12 blok sidebar. Bisa dipakai dengan tema apa pun.

| Blok | Sumber data |
|---|---|
| Widget: Journal Issue (cover) | Terbitan terbaru (cover + tautan) |
| Widget: Quick Actions | Tombol Submit Your Paper, Become a Reviewer, ikon media sosial (Settings) |
| Widget: Journal Menu | Daftar `ikon|Label|URL` (Settings) |
| Widget: Author Diversity | Kolom *Country* penulis artikel terbit (otomatis) |
| Widget: News | Pengumuman jurnal (otomatis) |
| Widget: Keywords Cloud | Kata kunci artikel terbit (otomatis) |
| Widget: Most Accessed | Statistik abstrak OJS (otomatis; sepanjang waktu / 12 bulan) |
| Widget: Editorial Board | Daftar manual (Settings) |
| Widget: Published Volumes | Terbitan per tahun (otomatis) |
| Widget: Visitors | Statistik OJS per negara (perlu Geo Location di Usage Statistics) |
| Widget: Google Scholar Citation | Angka diisi manual dari profil Google Scholar |
| Widget: Poster / Flyer | URL gambar + tautan (Settings) |

## Pasang
1. Settings > Website > Plugins > **Upload A New Plugin** → `journalWidgets-1.0.0.tar.gz`.
2. Aktifkan **Journal Portal Widgets** (kategori Generic Plugins), klik panah > **Settings**, isi, Save.
3. Settings > Website > Appearance > **Sidebar Management**: pindahkan blok berawalan **"Widget:"** ke Sidebar, atur urutan, Save.

Data statistik (Keywords, Most Accessed, Diversity, Visitors, Volumes) disimpan sementara sesuai pengaturan
"Simpan data statistik (jam)" agar tidak membebani server; menyimpan Settings akan menghitung ulang.
Isi `-` pada kolom tautan untuk menyembunyikan tombol (mis. Become a Reviewer).

## Dengan Portal Theme
Portal Theme otomatis menaruh Journal Issue, Author Diversity, News, Keywords, Most Accessed, dan Poster
di kolom kiri, sisanya di kolom kanan. Urutan bisa diubah di Appearance > Theme (opsi Blok kolom KIRI/KANAN).
Dengan tema lain, blok tampil di sidebar biasa sesuai urutan di Sidebar Management.
