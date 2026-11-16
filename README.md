# Instalasi Community Reading Space (CRS)

Community Reading Space (CRS) merupakan plugin SLiMS yang menyediakan ruang diskusi koleksi untuk mempertemukan pemustaka dan pustakawan. Plugin ini terintegrasi dengan katalog SLiMS sehingga pengguna dapat mendiskusikan koleksi yang tersedia melalui halaman OPAC.

## Persyaratan Sistem

Sebelum melakukan instalasi, pastikan beberapa persyaratan berikut telah terpenuhi:

- SLiMS Bulian 9.8.0 telah terpasang dan dapat diakses.
- Pengguna memiliki akses ke folder instalasi SLiMS.
- Database SLiMS telah dibuat dan dikonfigurasi.
- Pengguna dapat masuk ke halaman administrasi SLiMS sebagai pustakawan.

## Langkah-Langkah Instalasi

### 1. Unduh Plugin

Unduh repository Community Reading Space melalui GitHub dengan memilih **FOLDER → A-FULL-PACK_DOWNLOAD HERE**.

Ekstrak file ZIP yang telah diunduh.

### 2. Pindahkan Folder Plugin

Salin folder `community_reading_space` ke dalam direktori `plugins` pada instalasi SLiMS.

Struktur folder setelah instalasi:

```text
slims/
├── admin/
├── lib/
├── plugins/
│   └── community_reading_space/
│       ├── admin/
│       ├── assets/
│       ├── includes/
│       ├── member/
│       ├── sql/
│       ├── community_reading_space.plugin.php
│       ├── config.php
│       ├── index.php
│       └── README.md
├── sysconfig.inc.php
└── index.php
```

Pastikan file `community_reading_space.plugin.php` berada langsung di dalam folder `community_reading_space`, bukan di dalam folder tambahan hasil ekstraksi ZIP.

### 3. Aktifkan Plugin

1. Buka website SLiMS melalui browser.
2. Masuk ke halaman administrasi menggunakan akun pustakawan.
3. Buka menu **System → Plugins**.
4. Temukan Community Reading Space.
5. Aktifkan plugin melalui pengaturan plugin SLiMS.

### 4. Konfigurasi Awal

Setelah plugin aktif, buka menu administrasi **System → Community Reading Space**.

Lakukan konfigurasi awal sesuai fitur yang tersedia, termasuk pembuatan tabel tambahan CRS dan sinkronisasi data katalog SLiMS jika diperlukan.

Pastikan proses konfigurasi berhasil sebelum menggunakan fitur diskusi.

### 5. Akses Community Reading Space

Setelah plugin berhasil diaktifkan, pemustaka dapat mengakses Community Reading Space melalui halaman OPAC SLiMS.

Alamat akses:

```text
http://localhost/nama_folder_slims/index.php?p=community_reading_space
```

Ganti `nama_folder_slims` dengan nama folder instalasi SLiMS yang digunakan.

Pemustaka dapat menggunakan halaman CRS untuk menjelajahi diskusi koleksi dan berinteraksi dengan pengguna lain sesuai fitur yang tersedia.

## Catatan

- Lakukan pencadangan database dan folder SLiMS sebelum memasang plugin.
- Jangan mengubah nama folder utama plugin karena dapat memengaruhi pemanggilan file.
- Pastikan plugin telah diaktifkan melalui administrasi SLiMS sebelum mengakses halaman CRS.
- Jika halaman CRS tidak dapat dibuka, periksa kembali struktur folder, konfigurasi plugin, dan pesan kesalahan PHP.
- Menu administrasi CRS digunakan untuk pengelolaan oleh pustakawan, sedangkan halaman OPAC digunakan sebagai antarmuka pemustaka.
