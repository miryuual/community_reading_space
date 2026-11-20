# Community Reading Space (CRS)

Plugin diskusi komunitas yang terintegrasi dengan OPAC dan administrasi SLiMS.

## Fitur
- Menu OPAC dan menu administrasi didaftarkan melalui `SLiMS\Plugins::registerMenu()`.
- Trending Topic menggunakan skor: jumlah like dikalikan 2 ditambah jumlah tanggapan.
- Like diskusi per akun (satu like aktif per akun per topik; klik ulang untuk unlike).
- Koleksi yang dibahas bersifat opsional; kategori diskusi tetap wajib.
- Community Rules dapat diubah dari dashboard pustakawan.
- Logo CRS dapat diunggah dari dashboard (PNG, JPG, WebP, maksimal 2 MB).
- Tampilan CRS menyesuaikan desktop, tablet, dan ponsel.

## Instalasi / pembaruan
1. Backup folder plugin dan database SLiMS.
2. Salin folder `community_reading_space` ke direktori `plugins` SLiMS.
3. Aktifkan plugin melalui pengelolaan plugin SLiMS.
4. Buka menu CRS di administrasi dan jalankan penyiapan tabel jika instalasi baru.
5. Untuk instalasi CRS lama, buka dashboard pustakawan. Migrasi tambahan `crs_settings`, `crs_topic_like`, dan perubahan kolom koleksi opsional dijalankan otomatis ketika dashboard/OPAC CRS dibuka.
6. Jalankan sinkronisasi katalog.

## Navigasi
Menu OPAC dan menu administrasi didaftarkan oleh `community_reading_space.plugin.php` menggunakan API resmi `registerMenu()`. Pastikan plugin aktif dan muat ulang halaman SLiMS. Tidak perlu menyunting template tema OPAC.

## Catatan
- Jangan menghapus tabel `crs_` saat memperbarui plugin.
- Jangan menjalankan ulang `install.sql` pada database produksi yang sudah berisi data.
- Backup database sebelum pembaruan.
- Upload logo disimpan pada `assets/uploads/`.
- Uji pada salinan/staging SLiMS terlebih dahulu. Paket ini telah diperiksa sintaks PHP-nya, tetapi tetap perlu diuji pada versi SLiMS dan database lokal yang digunakan.


## Pembaruan 1.3.2
- Menambahkan tautan Community Reading Space pada navigasi utama OPAC melalui hook `after_content_load`.
- Menambahkan halaman Trending Topic tersendiri melalui `?p=community_reading_space&crs_page=trending`.
- Mengurutkan Trending Topic berdasarkan skor interaksi: 2 poin per like dan 1 poin per tanggapan.
- Menyatukan filter urutan dan status dalam satu baris dengan scroll horizontal pada layar kecil.


## Pembaruan 1.3.2
- Menambahkan opsi aktif/nonaktif navigasi CRS pada dashboard pustakawan.
- Menempatkan tautan CRS pada navigasi global OPAC sebelum pilihan bahasa jika elemen bahasa dikenali.
- Menambahkan pengaturan nama komunitas, tagline, judul/deskripsi header, dan warna header.
- Menghilangkan tombol sinkronisasi katalog setelah sinkronisasi pertama selesai; status lama dimigrasikan dari jumlah koleksi yang sudah ada.
- Menyegarkan tampilan dashboard pustakawan agar lebih terstruktur dan responsif.
