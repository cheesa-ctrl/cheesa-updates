# LoA & Invoice APC — plugin OJS 3.3

Plugin generik untuk OJS 3.3 yang:

- **Menerbitkan LoA otomatis** saat editor mencatat keputusan **Accept** (opsional juga saat *Send to Production*), lalu mengirim email ke semua penulis atau penulis korespondensi saja. Email berisi tautan LoA dan, bila ada biaya, tautan invoice APC.
- Membuat **LoA dua bahasa** (Indonesia & Inggris), **invoice**, dan **kuitansi** sebagai halaman cetak rapi (A4 / F4 / Letter). Tombol *Cetak / Simpan PDF* menghasilkan PDF satu halaman.
- Kop surat bisa diatur: 2 logo, hingga 5 baris teks, gaya garis, warna aksen, kota, 1–2 penanda tangan, gambar tanda tangan dan stempel, teks kaki.
- **Penomoran** dengan pola sendiri: `{SEQ}` (urut 5 digit per tahun), `{PREFIX}`, `{ACRONYM}`, `{YEAR}`, `{MONTH}`, `{ROMAN}`, `{ID}`. Nomor yang sudah terbit tidak berubah.
- **Pembayaran**: transfer bank + unggah bukti (dikonfirmasi editor), **Midtrans Snap** (IDR), dan **Xendit Invoice**. Pembayaran online baru dianggap lunas bila notifikasinya asli (tanda tangan Midtrans / token callback Xendit), status dicek ulang ke API gateway, nomor order cocok, mata uang sama, dan jumlahnya cukup.
- **Halaman penulis** `…/apcloa`: unduh LoA, invoice, kuitansi; bayar online; unggah bukti transfer.
- **Dasbor editor** `…/apcloa/dashboard`: ringkasan belum bayar / menunggu konfirmasi / lunas / dibebaskan, total diterima dan belum terbayar; per naskah: tandai lunas, tolak bukti, bebaskan biaya, ubah jumlah, kirim ulang LoA, batalkan/pulihkan, catatan editor.
- **QR verifikasi** di setiap dokumen menuju `…/apcloa/verify/{kode}`, yang menampilkan keaslian dan status pembayaran. Naskah yang ditolak setelah diterima otomatis berstatus **dibatalkan**.

## Pasang

1. **Website > Plugins > Upload A New Plugin**, pilih `apcLoa.tar.gz` (perlu akun administrator situs).
2. Aktifkan **LoA & Invoice APC** di Generic Plugins. Tabel `apc_loa_records` dibuat otomatis.
3. Buka **Settings** pada plugin, lalu isi:
   - **Biaya & pengiriman**: jumlah APC (0 = tanpa APC), mata uang, jatuh tempo, penerima email.
   - **Pembayaran**: instruksi transfer bank, QRIS (opsional), dan kunci gateway.
   - **Kop surat, penomoran, tanda tangan, isi LoA & email**.
4. Klik **Pratinjau LoA** untuk melihat hasilnya sebelum LoA pertama terkirim.
5. Tambahkan menu untuk penulis: **Website > Setup > Navigation**, buat item **Remote URL** bernama "APC & LoA" yang menuju `https://…/index.php/{jurnal}/apcloa`.

## Midtrans

1. Ambil **Server Key** di Midtrans (Settings > Access Keys). Gunakan Sandbox untuk uji coba.
2. Tempel di Settings plugin, centang Midtrans; centang **Mode produksi** hanya untuk akun Production.
3. URL notifikasi dikirim otomatis per transaksi. Untuk berjaga-jaga, URL yang sama (tertera di Settings plugin) juga bisa diisi di Midtrans > Settings > Payment > Notification URL.

## Xendit

1. Buat **Secret API key** dengan izin *Money-in: Write* (Settings > API Keys).
2. Salin **Callback Verification Token** (Settings > Webhooks).
3. Daftarkan URL webhook dari Settings plugin pada **Invoices paid**.

## Catatan

- Bukti transfer disimpan di folder `files_dir` OJS (`…/apcLoa/{id jurnal}/`), tidak dapat diakses publik.
- Jika jurnal memakai *restrict site access* (hanya pengguna login), notifikasi gateway bisa terhalang; matikan pembatasan itu atau gunakan transfer bank.
- Kode QR dibuat di browser dengan "QRCode for JavaScript" karya Kazuhiko Arase (lisensi MIT), tanpa layanan luar.
