<?php

/**
 * Community Reading Space
 * Autentikasi terintegrasi dengan SLiMS.
 */

// Mengambil pengguna yang sedang login.
function current_user(): ?array
{
    // Pustakawan SLiMS.
    if (!empty($_SESSION['uid'])) {
        return [
            'id'        => (int) $_SESSION['uid'],
            'username'  => $_SESSION['uname'] ?? '',
            'name'      => $_SESSION['realname'] ?? 'Pustakawan',
            'role'      => 'staff',
            'member_id' => null,
        ];
    }

    // Pemustaka SLiMS.
    if (!empty($_SESSION['mid'])) {
        return [
            'id'        => 0,
            'username'  => '',
            'name'      => $_SESSION['m_name'] ?? 'Pemustaka',
            'role'      => 'member',
            'member_id' => (string) $_SESSION['mid'],
        ];
    }

    return null;
}

// Memeriksa login pengguna.
function require_login(): void
{
    if (!current_user()) {
        header(
            'Location: ' .
            rtrim(SWB, '/') .
            '/index.php?p=member'
        );

        exit;
    }
}

// Memeriksa pustakawan.
function is_staff(): bool
{
    return !empty($_SESSION['uid']);
}

// Memeriksa akses panel administrasi CRS.
function require_staff(): void
{
    // Akses halaman ini harus melalui router admin SLiMS dan sesi pustakawan aktif.
    if (!defined('INDEX_AUTH') || empty($_SESSION['uid'])) {
        http_response_code(403);
        exit('Akses ditolak. Silakan login sebagai pustakawan melalui administrasi SLiMS.');
    }
}

// Login tetap dikelola oleh SLiMS.
function login_user(array $u): void
{

}

// Logout tetap dikelola oleh SLiMS.
function logout_user(): void
{
    // Tidak menggunakan logout terpisah.
}
