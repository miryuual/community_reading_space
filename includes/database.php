<?php

if (!defined('INDEX_AUTH') && !defined('ADMIN_AREA')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

/**
 * Mengambil koneksi database bawaan SLiMS.
 * Tidak membutuhkan konfigurasi database tambahan.
 */
function crs_db(): PDO {
    return \SLiMS\DB::getInstance();
}
