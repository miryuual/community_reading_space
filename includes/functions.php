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
    <title><?=e($title)?> · <?=e(crs_setting('community_name', CRS_APP_NAME))?></title>
    <link rel="stylesheet" href="<?=e(CRS_PLUGIN_URL)?>assets/css/style.css">
    <script>
    // Hilangkan judul rute mentah yang dicetak template OPAC SLiMS di atas konten plugin.
    document.addEventListener('DOMContentLoaded', function () {
        var labels = ['community_reading_space'];
        document.querySelectorAll('h1,h2,h3,.page-title,.module-title,.breadcrumb-item').forEach(function (node) {
            var value = (node.textContent || '').trim().toLowerCase();
            if (labels.indexOf(value) !== -1) {
                node.style.display = 'none';
                node.setAttribute('aria-hidden', 'true');
            }
        });
    });
    </script></head><body>
    <header class="topbar"><a class="brand" href="<?=e(crs_url())?>"><?php $logo=crs_setting('logo_path'); if($logo!==''): ?><img class="brand-logo" src="<?=e(CRS_PLUGIN_URL . $logo)?>" alt="Logo CRS"><?php else: ?><span class="brand-mark">CR</span><?php endif; ?><span><b><?=e(crs_setting('community_name', CRS_APP_NAME))?></b><small><?=e(crs_setting('community_tagline', 'Ruang diskusi koleksi perpustakaan'))?></small></span></a>
    <nav><a href="<?=e(crs_url())?>">Jelajahi</a><a href="<?=e(crs_url('trending'))?>">Trending Topic</a><a href="<?=e(crs_url())?>&crs_page=community_rules">Community Rules</a>
    <?php if($u && $u['role']==='member'): ?><a href="<?=e(crs_url('my_discussions'))?>">Diskusi Saya</a><?php endif; ?>
    <?php if(is_staff()): ?><a href="<?=e(rtrim(SWB,'/'))?>/admin/index.php?mod=system">Panel Pustakawan</a><?php endif; ?>
    <?php if($u): ?><span class="user-pill"><?=e($u['name'])?> · <?=$u['role']==='staff'?'Pustakawan':'Pemustaka'?></span>
    <a href="<?=e(rtrim(SWB,'/'))?>/index.php?p=member&logout=1">Keluar</a>
    <?php else: ?><a href="<?=e(rtrim(SWB,'/'))?>/index.php?p=member">Masuk</a><?php endif; ?></nav></header>
    <main class="crs-container" style="--crs-header-bg: <?=e(crs_setting('header_bg_color', '#e8f2fb'))?>"><?php show_flash(); ?>
    <?php
}
function page_footer(): void {
    ?></main><footer class="footer">Community Reading Space · Terhubung dengan katalog SLiMS</footer>
    <script src="<?=e(CRS_PLUGIN_URL)?>assets/js/script.js"></script></body></html><?php
}

/**
 * Menyiapkan tabel fitur tambahan dan migrasi ringan yang aman diulang.
 */
function crs_ensure_feature_tables(): void {
    $pdo = crs_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS crs_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value LONGTEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS crs_topic_like (
        id_like BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_topic INT NOT NULL,
        actor_type ENUM('member','staff') NOT NULL,
        actor_id VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_crs_topic_actor (id_topic, actor_type, actor_id),
        INDEX idx_crs_like_topic (id_topic),
        CONSTRAINT fk_crs_like_topic FOREIGN KEY (id_topic)
            REFERENCES crs_topic(id_topic) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Koleksi bersifat opsional. Ubah hanya jika kolom masih NOT NULL.
    $col = $pdo->query("SHOW COLUMNS FROM crs_topic LIKE 'id_collection'")->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper((string)$col['Null']) === 'NO') {
        $pdo->exec("ALTER TABLE crs_topic MODIFY id_collection INT NULL");
    }
}

function crs_setting(string $key, string $default = ''): string {
    if (!crs_tables_ready()) return $default;
    try {
        $s = crs_db()->prepare('SELECT setting_value FROM crs_settings WHERE setting_key = ?');
        $s->execute([$key]);
        $value = $s->fetchColumn();
        return $value === false || $value === null ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function crs_actor_identity(): ?array {
    $u = current_user();
    if (!$u) return null;
    if ($u['role'] === 'staff') return ['type' => 'staff', 'id' => (string)(int)$u['id']];
    return ['type' => 'member', 'id' => (string)$u['member_id']];
}

function crs_topic_liked(int $topicId): bool {
    $actor = crs_actor_identity();
    if (!$actor) return false;
    $s = crs_db()->prepare('SELECT 1 FROM crs_topic_like WHERE id_topic=? AND actor_type=? AND actor_id=?');
    $s->execute([$topicId, $actor['type'], $actor['id']]);
    return (bool)$s->fetchColumn();
}

function crs_topic_like_count(int $topicId): int {
    $s = crs_db()->prepare('SELECT COUNT(*) FROM crs_topic_like WHERE id_topic=?');
    $s->execute([$topicId]);
    return (int)$s->fetchColumn();
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
    $logo = crs_setting('logo_path');
    if ($logo !== '') echo '<img class="admin-crs-logo" src="' . e(CRS_PLUGIN_URL . $logo) . '" alt="Logo CRS">';
    echo '<div class="eyebrow">COMMUNITY READING SPACE</div>';
    echo '<h1>' . e($title) . '</h1>';
}

function crs_admin_footer(): void
{
    echo '</div>';
    echo '<script src="' . e(CRS_PLUGIN_URL) . 'assets/js/script.js"></script>';
}
