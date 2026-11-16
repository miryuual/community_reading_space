<?php

// Mengizinkan akses melalui OPAC maupun administrasi SLiMS.
if (!defined('INDEX_AUTH')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Alias kompatibilitas untuk halaman anggota pada rilis awal CRS.
if (!function_exists('db')) {
    function db(): PDO { return crs_db(); }
}
if (!function_exists('flash')) {
    function flash(string $msg, string $type='success'): void { crs_flash($msg, $type); }
}
function crs_url(string $page = ''): string {
    $url = CRS_OPAC_URL;
    return $page !== '' ? $url . '&crs_page=' . rawurlencode($page) : $url;
}
function csrf_token(): string {
    if (empty($_SESSION['crs_csrf'])) $_SESSION['crs_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['crs_csrf'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['crs_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Sesi formulir kedaluwarsa. Muat ulang halaman.');
    }
}
function crs_flash(string $msg, string $type='success'): void { $_SESSION['crs_flash']=['message'=>$msg,'type'=>$type]; }
function show_flash(): void {
    if (!empty($_SESSION['crs_flash'])) {
        $f=$_SESSION['crs_flash'];
        echo '<div class="alert '.e($f['type']).'">'.e($f['message']).'</div>';
        unset($_SESSION['crs_flash']);
    }
}
function actor_fields(?array $u): array {
    if (!$u) return [null, null];
    return $u['role']==='staff' ? [null,(int)$u['id']] : [$u['member_id'],null];
}
function actor_name(?string $memberId, ?int $userId): string {
    if ($userId) {
        $s=crs_db()->prepare('SELECT realname FROM `user` WHERE user_id=?');
        $s->execute([$userId]); return $s->fetchColumn() ?: 'Pustakawan';
    }
    if ($memberId) {
        $s=crs_db()->prepare('SELECT member_name FROM `member` WHERE member_id=?');
        $s->execute([$memberId]); return $s->fetchColumn() ?: 'Pemustaka';
    }
    return 'Pengunjung';
}
function topic_author_matches(array $topic, array $u): bool {
    return $u['role']==='staff'
        ? (int)($topic['user_id'] ?? 0)===(int)$u['id']
        : (string)($topic['member_id'] ?? '')===(string)($u['member_id'] ?? '');
}
function page_header(string $title): void {
    $u=current_user();
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=e($title)?> · <?=e(CRS_APP_NAME)?></title>
    <link rel="stylesheet" href="<?=e(CRS_PLUGIN_URL)?>assets/css/style.css"></head><body>
    <header class="topbar"><a class="brand" href="<?=e(crs_url())?>"><span class="brand-mark">CR</span><span><b>Community Reading Space</b><small>Ruang diskusi koleksi perpustakaan</small></span></a>
    <nav><a href="<?=e(crs_url())?>">Jelajahi</a>
    <?php if($u && $u['role']==='member'): ?><a href="<?=e(crs_url('my_discussions'))?>">Diskusi Saya</a><?php endif; ?>
    <?php if(is_staff()): ?><a href="<?=e(rtrim(SWB,'/'))?>/admin/index.php?mod=system">Panel Pustakawan</a><?php endif; ?>
    <?php if($u): ?><span class="user-pill"><?=e($u['name'])?> · <?=$u['role']==='staff'?'Pustakawan':'Pemustaka'?></span>
    <a href="<?=e(rtrim(SWB,'/'))?>/index.php?p=member&logout=1">Keluar</a>
    <?php else: ?><a href="<?=e(rtrim(SWB,'/'))?>/index.php?p=member">Masuk</a><?php endif; ?></nav></header>
    <main class="container"><?php show_flash(); ?>
    <?php
}
function page_footer(): void {
    ?></main><footer class="footer">Community Reading Space · Terhubung dengan katalog SLiMS</footer>
    <script src="<?=e(CRS_PLUGIN_URL)?>assets/js/script.js"></script></body></html><?php
}
function crs_tables_ready(): bool {
    try { return (bool)crs_db()->query("SHOW TABLES LIKE 'crs_topic'")->fetchColumn(); }
    catch (Throwable $e) { return false; }
}


/**
 * Header/footer khusus halaman administrasi.
 * Konten plugin dimuat di dalam kerangka admin SLiMS, jadi jangan
 * membuat ulang tag html/head/body seperti halaman OPAC mandiri.
 */
function crs_admin_header(string $title): void
{
    echo '<link rel="stylesheet" href="' . e(CRS_PLUGIN_URL) . 'assets/css/style.css">';
    echo '<div class="crs-admin-wrap">';
    show_flash();
    echo '<div class="eyebrow">COMMUNITY READING SPACE</div>';
    echo '<h1>' . e($title) . '</h1>';
}

function crs_admin_footer(): void
{
    echo '</div>';
    echo '<script src="' . e(CRS_PLUGIN_URL) . 'assets/js/script.js"></script>';
}
