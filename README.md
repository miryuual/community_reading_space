# Community Reading Space — Plugin SLiMS 9 Bulian

CRS merupakan plugin ruang diskusi koleksi yang menggunakan database aktif SLiMS. CRS tidak membuat koneksi database terpisah.

## Integrasi dan akses

- **Pemustaka / OPAC:** halaman CRS diakses melalui router OPAC SLiMS:
  `index.php?p=community_reading_space`
- **Administrasi / pustakawan:** menu **System → Community Reading Space** didaftarkan melalui API plugin SLiMS `registerMenu()`.
- File plugin utama menggunakan akhiran `.plugin.php` dan berada di dalam subfolder `plugins/community_reading_space/`.
- Kedua menu didaftarkan menggunakan `\SLiMS\Plugins::getInstance()->registerMenu()`, mengikuti dokumentasi Plugin System SLiMS.
- Data CRS menggunakan koneksi PDO bawaan SLiMS (`SLiMS\\DB::getInstance()`). Pengarang katalog dibaca melalui relasi `biblio_author` dan `mst_author` (bukan kolom `biblio.author`).
- Identitas pemustaka membaca sesi OPAC SLiMS (`mid`, `m_name`); identitas pustakawan membaca sesi administrasi (`uid`, `realname`).

## Struktur

```text
plugins/
└── community_reading_space/
    ├── community_reading_space.plugin.php
    ├── index.php
    ├── config.php
    ├── admin/
    │   └── index.php
    ├── assets/
    │   ├── css/style.css
    │   └── js/script.js
    ├── includes/
    │   ├── auth.php
    │   ├── database.php
    │   └── functions.php
    ├── member/
    └── sql/
        └── install.sql
```

## Instalasi

1. Backup folder plugin dan database SLiMS.
2. Ekstrak folder `community_reading_space` ke direktori `plugins`.
3. Pastikan file `community_reading_space.plugin.php` berada langsung di dalam folder tersebut.
4. Login sebagai pustakawan.
5. Buka **System → Plugins**, lalu aktifkan **Community Reading Space**.
6. Buka **System → Community Reading Space**.
7. Klik **Buat Tabel dan Hubungkan Katalog** satu kali untuk membuat tabel CRS dan mengisi cache katalog.
8. Buka OPAC melalui menu plugin yang tersedia atau alamat `index.php?p=community_reading_space`.

## Sinkronisasi katalog

Jika data bibliografi SLiMS bertambah atau berubah, buka **System → Community Reading Space** lalu pilih **Sinkronkan Katalog Sekarang**.

Tabel tambahan CRS menggunakan awalan `crs_`: `crs_collection`, `crs_category`, `crs_topic`, `crs_reply`, `crs_vote`, dan `crs_report`. Tabel tersebut berada pada database SLiMS yang sama dan tidak mengganti tabel inti SLiMS.

## Catatan tampilan OPAC

Halaman CRS berjalan melalui URL/router OPAC SLiMS dan stylesheet CRS sendiri. API `registerMenu()` mendukung pendaftaran submenu pada modul SLiMS; dokumentasi Plugin System yang menjadi acuan tidak merinci hook untuk menyisipkan tautan ke navigasi/header setiap tema OPAC. Karena itu, ketersediaan tautan pada navigasi OPAC bergantung pada dukungan modul/tema yang digunakan. URL OPAC CRS di atas merupakan jalur akses langsungnya.

Panel admin CRS dirender sebagai konten di dalam kerangka administrasi SLiMS, bukan membuat dokumen HTML baru di dalam halaman admin.

## Kompatibilitas dan keamanan

- Paket ini disusun untuk struktur plugin SLiMS 9 Bulian; uji terlebih dahulu pada salinan instalasi sebelum dipakai di server produksi.
- Panel administrasi memerlukan sesi pustakawan SLiMS (`$_SESSION['uid']`) dan bootstrap administrasi (`INDEX_AUTH`).
- Jangan menghapus tabel CRS saat memperbarui file plugin.
- Jika sinkronisasi katalog menampilkan pesan SQL, periksa kesesuaian kolom bibliografi pada instalasi SLiMS dan simpan pesan kesalahan lengkap untuk penyesuaian.
