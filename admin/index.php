<?php

// Plugin dijalankan melalui plugin_container SLiMS.

if (!defined('INDEX_AUTH')) {

    http_response_code(403);

    exit('Akses harus melalui administrasi SLiMS.');

}

if (!defined('SB')) {

    http_response_code(500);

    exit('Konfigurasi SLiMS (SB) tidak tersedia.');

}

require_once SB . 'admin/default/session.inc.php';

require_once __DIR__ . '/../includes/functions.php';

// Memeriksa login pustakawan SLiMS.

require_staff();


$crs_plugin_id = md5(realpath(__FILE__));

$crs_action_base_url = AWB . 'plugin_container.php?mod=system&id=' . $crs_plugin_id;

$crs_action_url = $crs_action_base_url . (isset($_GET['crs_tab']) ? '&crs_tab=' . rawurlencode((string)$_GET['crs_tab']) : '');

/**

 * Membuat tabel tambahan CRS pada database SLiMS.

 */

function crs_install_tables(): void

{

    $file = __DIR__ . '/../sql/install.sql';

    if (!is_file($file)) {

        throw new RuntimeException('File install.sql tidak ditemukan.');

    }

    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {

        throw new RuntimeException('File install.sql kosong atau tidak dapat dibaca.');

    }

    // File install.sql berisi perintah SQL sederhana yang dipisahkan titik koma.
    // Regex versi sebelumnya tidak valid dan membuat proses setup gagal sebelum
    // tabel CRS dibuat.
    $statements = explode(';', $sql);
    $pdo = crs_db();

    foreach ($statements as $statement) {
        $statement = trim($statement);

        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

}

/**

 * Menyinkronkan katalog SLiMS ke tabel koleksi CRS.

 */

function crs_sync_catalog(): int

{

    $pdo = crs_db();


    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $tableSet = array_fill_keys(array_map('strtolower', $tables), true);

    $authorJoin = '';

    $authorSelect = 'NULL';

    if (isset($tableSet['biblio_author'], $tableSet['mst_author'])) {

        $authorCols = $pdo->query('SHOW COLUMNS FROM biblio_author')->fetchAll(PDO::FETCH_COLUMN);

        $mstAuthorCols = $pdo->query('SHOW COLUMNS FROM mst_author')->fetchAll(PDO::FETCH_COLUMN);

        $authorCols = array_map('strtolower', $authorCols);

        $mstAuthorCols = array_map('strtolower', $mstAuthorCols);

        if (

            in_array('biblio_id', $authorCols, true) &&

            in_array('author_id', $authorCols, true) &&

            in_array('author_id', $mstAuthorCols, true) &&

            in_array('author_name', $mstAuthorCols, true)

        ) {

            $authorOrder = in_array('level', $authorCols, true) ? 'ba.level' : 'ba.author_id';

            $authorJoin = " LEFT JOIN (

                SELECT ba.biblio_id,

                       GROUP_CONCAT(DISTINCT ma.author_name ORDER BY {$authorOrder} SEPARATOR ', ') AS author

                FROM biblio_author ba

                INNER JOIN mst_author ma ON ma.author_id = ba.author_id

                GROUP BY ba.biblio_id

            ) a ON a.biblio_id = b.biblio_id ";

            $authorSelect = 'a.author';

        }

    }

    $sql = "INSERT INTO crs_collection

        (biblio_id, title, author, publication_year, collection_type,

         cover_url, description, synced_at)

        SELECT

            b.biblio_id,

            b.title,

            {$authorSelect},

            b.publish_year,

            COALESCE(g.gmd_name, 'Bahan Pustaka'),

            b.image,

            b.notes,

            NOW()

        FROM biblio b

        LEFT JOIN mst_gmd g ON g.gmd_id = b.gmd_id

        {$authorJoin}

        ON DUPLICATE KEY UPDATE

            title = VALUES(title),

            author = VALUES(author),

            publication_year = VALUES(publication_year),

            collection_type = VALUES(collection_type),

            cover_url = VALUES(cover_url),

            description = VALUES(description),

            synced_at = NOW()";

    $pdo->exec($sql);

    return (int) $pdo->query('SELECT COUNT(*) FROM crs_collection')->fetchColumn();

}

/**

 * Memproses seluruh formulir panel pustakawan.

 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['crs_action'] ?? $_POST['action'] ?? '';

    try {

        // Membuat tabel dan menyinkronkan katalog.

        if ($action === 'setup') {

            crs_install_tables();

            crs_ensure_feature_tables();

            $count = crs_sync_catalog();

            crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('catalog_synced','1') ON DUPLICATE KEY UPDATE setting_value='1'")->execute();

            crs_flash(

                "Tabel CRS siap. {$count} data koleksi berhasil disinkronkan dari katalog SLiMS."

            );

        } elseif ($action === 'sync') {

            if (!crs_tables_ready()) {

                throw new RuntimeException('Tabel CRS belum tersedia.');

            }

            $count = crs_sync_catalog();

            crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('catalog_synced','1') ON DUPLICATE KEY UPDATE setting_value='1'")->execute();

            crs_flash(

                "Sinkronisasi selesai. Total {$count} koleksi tersedia."

            );

        } else {

            // ID topik untuk tindakan moderasi.

            $id = (int) ($_POST['id_topic'] ?? 0);

            if ($action === 'pin' && $id > 0) {

                crs_db()->prepare(

                    'UPDATE crs_topic SET is_pinned = 1 - is_pinned WHERE id_topic = ?'

                )->execute([$id]);

            } elseif ($action === 'close' && $id > 0) {

                crs_db()->prepare(

                    "UPDATE crs_topic SET status = IF(status = 'Closed', 'Open', 'Closed') WHERE id_topic = ?"

                )->execute([$id]);

            } elseif ($action === 'hide' && $id > 0) {

                crs_db()->prepare(

                    'UPDATE crs_topic SET is_hidden = 1 - is_hidden WHERE id_topic = ?'

                )->execute([$id]);

            } elseif ($action === 'delete_topic' && $id > 0) {

                $pdo = crs_db();

                $pdo->beginTransaction();

                $replyIds = $pdo->prepare('SELECT id_reply FROM crs_reply WHERE id_topic = ?');

                $replyIds->execute([$id]);

                $ids = array_map('intval', $replyIds->fetchAll(PDO::FETCH_COLUMN));

                if ($ids) {

                    $marks = implode(',', array_fill(0, count($ids), '?'));

                    $pdo->prepare("DELETE FROM crs_report WHERE id_reply IN ($marks)")->execute($ids);

                    $pdo->prepare("DELETE FROM crs_vote WHERE id_reply IN ($marks)")->execute($ids);

                }

                $pdo->prepare('DELETE FROM crs_report WHERE id_topic = ?')->execute([$id]);

                $pdo->prepare('DELETE FROM crs_reply WHERE id_topic = ?')->execute([$id]);

                $pdo->prepare('DELETE FROM crs_topic_like WHERE id_topic = ?')->execute([$id]);

                $pdo->prepare('DELETE FROM crs_topic WHERE id_topic = ?')->execute([$id]);

                $pdo->commit();

            } elseif ($action === 'save_general_settings') {

                $communityName = trim((string)($_POST['community_name'] ?? ''));

                $communityTagline = trim((string)($_POST['community_tagline'] ?? ''));

                $headerTitle = trim((string)($_POST['header_title'] ?? ''));

                $headerDescription = trim((string)($_POST['header_description'] ?? ''));

                $headerColor = '#e8f2fb';

                $navEnabled = isset($_POST['opac_nav_enabled']) ? '1' : '0';

                if ($communityName === '') throw new RuntimeException('Nama komunitas tidak boleh kosong.');

                $settings = [

                    'community_name' => $communityName,

                    'community_tagline' => $communityTagline,

                    'header_title' => $headerTitle,

                    'header_description' => $headerDescription,

                    'opac_nav_enabled' => $navEnabled

                ];

                $save = crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

                foreach ($settings as $key => $value) $save->execute([$key, $value]);

            } elseif ($action === 'save_rules') {

                $rules = trim((string)($_POST['community_rules'] ?? ''));

                $save = crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('community_rules',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

                $save->execute([$rules]);

            } elseif ($action === 'upload_logo') {

                if (empty($_FILES['crs_logo']) || $_FILES['crs_logo']['error'] !== UPLOAD_ERR_OK) {

                    throw new RuntimeException('Pilih file logo yang valid untuk diunggah.');

                }

                if ($_FILES['crs_logo']['size'] > 2 * 1024 * 1024) {

                    throw new RuntimeException('Ukuran logo maksimal 2 MB.');

                }

                $imageInfo = @getimagesize($_FILES['crs_logo']['tmp_name']);

                $mime = $imageInfo['mime'] ?? '';

                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

                if (!isset($allowed[$mime])) {

                    throw new RuntimeException('Format logo harus PNG, JPG, atau WebP.');

                }

                $uploadDir = __DIR__ . '/../assets/uploads';

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {

                    throw new RuntimeException('Folder upload logo tidak dapat dibuat.');

                }

                $filename = 'crs-logo.' . $allowed[$mime];

                if (!move_uploaded_file($_FILES['crs_logo']['tmp_name'], $uploadDir . '/' . $filename)) {

                    throw new RuntimeException('Logo gagal disimpan.');

                }

                $relative = 'assets/uploads/' . $filename;

                $save = crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('logo_path',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

                $save->execute([$relative]);

            } elseif ($action === 'remove_logo') {

                $oldLogo = crs_setting('logo_path');

                if ($oldLogo && strpos($oldLogo, 'assets/uploads/crs-logo.') === 0) {

                    $oldPath = realpath(__DIR__ . '/../' . $oldLogo);

                    $basePath = realpath(__DIR__ . '/../assets/uploads');

                    if ($oldPath && $basePath && strpos($oldPath, $basePath . DIRECTORY_SEPARATOR) === 0) @unlink($oldPath);

                }

                crs_db()->prepare("DELETE FROM crs_settings WHERE setting_key='logo_path'")->execute();

            } elseif ($action === 'upload_header') {

                if (empty($_FILES['crs_header_image']) || $_FILES['crs_header_image']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Pilih gambar header yang valid.');

                if ($_FILES['crs_header_image']['size'] > 5 * 1024 * 1024) throw new RuntimeException('Ukuran gambar header maksimal 5 MB.');

                $imageInfo = @getimagesize($_FILES['crs_header_image']['tmp_name']);

                $mime = $imageInfo['mime'] ?? '';

                $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];

                if (!isset($allowed[$mime])) throw new RuntimeException('Format header harus PNG, JPG, atau WebP.');

                $uploadDir = __DIR__ . '/../assets/uploads';

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Folder upload tidak dapat dibuat.');

                $filename = 'crs-header.' . $allowed[$mime];

                if (!move_uploaded_file($_FILES['crs_header_image']['tmp_name'], $uploadDir . '/' . $filename)) throw new RuntimeException('Gambar header gagal disimpan.');

                $relative = 'assets/uploads/' . $filename;

                crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('header_image_path',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$relative]);

            } elseif ($action === 'remove_header') {

                $old = crs_setting('header_image_path');

                if ($old && strpos($old, 'assets/uploads/crs-header.') === 0) { $path = realpath(__DIR__ . '/../' . $old); $dir = realpath(__DIR__ . '/../assets/uploads'); if ($path && $dir && strpos($path, $dir . DIRECTORY_SEPARATOR) === 0) @unlink($path); }

                crs_db()->prepare("DELETE FROM crs_settings WHERE setting_key='header_image_path'")->execute();

            } elseif ($action === 'report_status') {

                $reportId = (int) ($_POST['id_report'] ?? 0);

                $status = $_POST['status'] ?? '';

                $allowedStatuses = ['Pending', 'Reviewed', 'Rejected'];

                if (

                    $reportId <= 0 ||

                    !in_array($status, $allowedStatuses, true)

                ) {

                    throw new RuntimeException('Data laporan tidak valid.');

                }

                crs_db()->prepare(

                    'UPDATE crs_report SET status = ? WHERE id_report = ?'

                )->execute([$status, $reportId]);

            } else {

                throw new RuntimeException('Tindakan tidak dikenal atau ID tidak valid.');

            }

            crs_flash($action === 'save_general_settings'

                ? 'Pengaturan komunitas dan navigasi berhasil disimpan.'

                : ($action === 'save_rules'

                    ? 'Community Rules berhasil diperbarui.'

                    : 'Perubahan berhasil disimpan.'));

        }

    } catch (Throwable $e) {

        crs_flash(

            'Proses gagal: ' . $e->getMessage(),

            'danger'

        );

    }

    header('Location: ' . (($_SERVER['REQUEST_URI'] ?? '') ?: (rtrim(SWB, '/') . '/admin/index.php?mod=system')));

    exit;

}

// Memeriksa kesiapan tabel CRS.

if (!crs_tables_ready()) {

    crs_admin_header('Penyiapan Community Reading Space');

    ?>

    <div class="eyebrow">INTEGRASI SLiMS</div>

    <p class="muted">

        Plugin menggunakan database aktif SLiMS.

        Tidak perlu membuat database baru atau mengisi host,

        username, dan password database secara manual.

    </p>

    <div class="form-card">

        <p>

            Tekan tombol berikut untuk membuat tabel CRS

            di database SLiMS dan mengambil data bibliografi

            dari katalog.

        </p>

        <form method="post" action="<?= e($crs_action_url) ?>">

            <input

                type="hidden"

                name="csrf"

                value="<?= e(csrf_token()) ?>"

            >

            <input

                type="hidden"

                name="crs_action"

                value="setup"

            >

            <button class="btn primary">

                Buat Tabel dan Hubungkan Katalog

            </button>

        </form>

    </div>

    <?php

    crs_admin_footer();

    return;

}

crs_ensure_feature_tables();

// Migrasi status sinkronisasi untuk instalasi CRS versi sebelumnya.

if (crs_setting('catalog_synced', '0') !== '1') {

    $existingCollections = (int)crs_db()->query('SELECT COUNT(*) FROM crs_collection')->fetchColumn();

    if ($existingCollections > 0) {

        crs_db()->prepare("INSERT INTO crs_settings(setting_key,setting_value) VALUES('catalog_synced','1') ON DUPLICATE KEY UPDATE setting_value='1'")->execute();

    }

}

// Mengambil statistik CRS.

$stats = [

    'topics' => crs_db()

        ->query('SELECT COUNT(*) FROM crs_topic')

        ->fetchColumn(),

    'replies' => crs_db()

        ->query('SELECT COUNT(*) FROM crs_reply')

        ->fetchColumn(),

    'members' => crs_db()

        ->query('SELECT COUNT(*) FROM member')

        ->fetchColumn(),

    'reports' => crs_db()

        ->query("SELECT COUNT(*) FROM crs_report WHERE status = 'Pending'")

        ->fetchColumn()

];

// Mengambil daftar diskusi.

$topics = crs_db()->query(

    "SELECT

        t.*,

        c.title AS collection_title,

        (

            SELECT COUNT(*)

            FROM crs_reply r

            WHERE r.id_topic = t.id_topic AND r.is_hidden=0

        ) AS reply_count,

        (SELECT COUNT(*) FROM crs_topic_like l WHERE l.id_topic=t.id_topic) AS like_count

    FROM crs_topic t

    LEFT JOIN crs_collection c

        ON c.id_collection = t.id_collection

    ORDER BY t.created_at DESC"

)->fetchAll(PDO::FETCH_ASSOC);

// Mengambil daftar laporan.

$reports = crs_db()->query(

    "SELECT

        rp.*,

        COALESCE(t.title, tr.title) AS topic_title

    FROM crs_report rp

    LEFT JOIN crs_topic t ON t.id_topic = rp.id_topic

    LEFT JOIN crs_reply rr ON rr.id_reply = rp.id_reply

    LEFT JOIN crs_topic tr ON tr.id_topic = rr.id_topic

    ORDER BY rp.created_at DESC

    LIMIT 20"

)->fetchAll(PDO::FETCH_ASSOC);

// Menampilkan panel pustakawan.

crs_admin_header('Panel Pustakawan');

?>

<div class="crs-admin-topline"><div class="crs-management-title">Community management</div><a class="btn outline crs-home-link" href="<?=e(CRS_OPAC_URL)?>" target="_blank" rel="noopener">Beranda CRS</a></div>

<?php

$catalogSynced = crs_setting('catalog_synced', '0') === '1';

$activeTab = $_GET['crs_tab'] ?? 'discussions';

if (!in_array($activeTab, ['settings', 'discussions', 'reports'], true)) $activeTab = 'discussions';

$tabUrl = $crs_action_base_url . '&crs_tab=';

?>

<nav class="crs-admin-tabs" aria-label="Navigasi pengelolaan CRS">

    <a class="<?= $activeTab === 'discussions' ? 'active' : '' ?>" href="<?=e($tabUrl . 'discussions')?>">Kelola Diskusi</a>

    <a class="<?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?=e($tabUrl . 'reports')?>">Laporan Diskusi <span><?= (int)$stats['reports'] ?></span></a>

    <a class="<?= $activeTab === 'settings' ? 'active' : '' ?>" href="<?=e($tabUrl . 'settings')?>">Pengaturan Komunitas</a>

</nav>

<?php if ($activeTab === 'discussions'): ?>

<div class="stats-grid">

    <div class="stat">

        <small>Total topik</small>

        <b><?= (int) $stats['topics'] ?></b>

    </div>

    <div class="stat">

        <small>Total tanggapan</small>

        <b><?= (int) $stats['replies'] ?></b>

    </div>

    <div class="stat">

        <small>Anggota</small>

        <b><?= (int) $stats['members'] ?></b>

    </div>

    <div class="stat">

        <small>Laporan pending</small>

        <b><?= (int) $stats['reports'] ?></b>

    </div>

</div>

<div class="section-head">

    <h2>Kelola diskusi</h2>

</div>

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>Topik</th>

                <th>Status</th>

                <th>Aktivitas</th>

                <th>Moderasi</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($topics as $t): ?>

                <tr>

                    <td>

                        <b>

                            <a href="<?= e(CRS_OPAC_URL) ?>&crs_page=detail_topic&id=<?= (int) $t['id_topic'] ?>">

                                <?= e($t['title']) ?>

                            </a>

                        </b>

                        <small>

                            <?= e($t['collection_title'] ?: 'Tidak dikaitkan dengan koleksi') ?>

                        </small>

                    </td>

                    <td>

                        <?= e($t['status']) ?>

                    </td>

                    <td>

                        ♥ <?= (int) $t['like_count'] ?> suka · <?= (int) $t['reply_count'] ?> tanggapan

                    </td>

                    <td>

                        <div class="admin-actions">

                            <?php foreach (

                                [

                                    'pin' => 'Sematkan',

                                    'close' => 'Tutup/Buka',

                                    'hide' => 'Sembunyikan'

                                ] as $a => $label

                            ): ?>

                                <form method="post" action="<?= e($crs_action_url) ?>">

                                    <input

                                        type="hidden"

                                        name="csrf"

                                        value="<?= e(csrf_token()) ?>"

                                    >

                                    <input

                                        type="hidden"

                                        name="action"

                                        value="<?= e($a) ?>"

                                    >

                                    <input

                                        type="hidden"

                                        name="id_topic"

                                        value="<?= (int) $t['id_topic'] ?>"

                                    >

                                    <button class="text-button">

                                        <?= e($label) ?>

                                    </button>

                                </form>

                            <?php endforeach; ?>

                            <form method="post" action="<?= e($crs_action_url) ?>" onsubmit="return confirm('Hapus diskusi ini beserta seluruh tanggapan dan laporannya? Tindakan tidak dapat dibatalkan.');">

                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                                <input type="hidden" name="action" value="delete_topic">

                                <input type="hidden" name="id_topic" value="<?= (int)$t['id_topic'] ?>">

                                <button class="text-button danger-text" type="submit">Hapus Diskusi</button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$topics): ?>

                <tr>

                    <td colspan="4">Belum ada diskusi.</td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php endif; ?>

<?php if ($activeTab === 'reports'): ?>

<div class="section-head">

    <h2>Laporan diskusi</h2>

</div>

<div class="table-wrap">

    <table>

        <thead>

            <tr>

                <th>Konten</th>

                <th>Alasan</th>

                <th>Status</th>

                <th>Tindakan</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($reports as $rp): ?>

                <tr>

                    <td>

                        <?= e($rp['topic_title'] ?? 'Tanggapan') ?>

                    </td>

                    <td>

                        <?= e($rp['reason']) ?>

                    </td>

                    <td>

                        <?= e($rp['status']) ?>

                    </td>

                    <td>

                        <form method="post" action="<?= e($crs_action_url) ?>" class="inline-form">

                            <input

                                type="hidden"

                                name="csrf"

                                value="<?= e(csrf_token()) ?>"

                            >

                            <input

                                type="hidden"

                                name="action"

                                value="report_status"

                            >

                            <input

                                type="hidden"

                                name="id_report"

                                value="<?= (int) $rp['id_report'] ?>"

                            >

                            <select name="status">

                                <option value="Pending" <?= $rp['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>

                                <option value="Reviewed" <?= $rp['status'] === 'Reviewed' ? 'selected' : '' ?>>Reviewed</option>

                                <option value="Rejected" <?= $rp['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>

                            </select>

                            <button class="btn small dark">

                                Simpan

                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$reports): ?>

                <tr>

                    <td colspan="4">Belum ada laporan.</td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php endif; ?>

<?php if ($activeTab === 'settings'): ?>

<div class="section-head"><h2>Pengaturan komunitas</h2></div>

<section class="form-card settings-full catalog-settings-card">

    <h3>Katalog Bibliografi</h3>

    <p class="muted"><?= $catalogSynced ? 'Katalog sudah tersinkronisasi.' : 'Katalog belum tersinkronisasi.' ?></p>

    <?php if ($catalogSynced): ?><span class="status answered">Sinkronisasi selesai</span><?php endif; ?>

    <?php if (!$catalogSynced): ?>

    <form method="post" action="<?=e($crs_action_url)?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="crs_action" value="sync"><button class="btn outline" type="submit">Sinkronkan Katalog</button></form>

    <?php endif; ?>

</section>

<div class="settings-grid">

    <section class="form-card">

        <h3>Logo Community Reading Space</h3>

        <?php $currentLogo = crs_setting('logo_path'); ?>

        <?php if ($currentLogo): ?><img class="admin-logo-preview" src="<?=e(CRS_PLUGIN_URL . $currentLogo)?>" alt="Logo CRS saat ini"><?php else: ?><p class="muted">Logo belum diatur. Logo teks CRS digunakan sebagai bawaan.</p><?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="<?=e($crs_action_url)?>">

            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

            <input type="hidden" name="action" value="upload_logo">

            <label for="crs_logo">Unggah logo (PNG, JPG, WebP; maksimal 2 MB)</label>

            <input id="crs_logo" type="file" name="crs_logo" accept="image/png,image/jpeg,image/webp" required>

            <button class="btn primary" type="submit">Simpan Logo</button>

        </form>

        <?php if ($currentLogo): ?>

        <form method="post" action="<?=e($crs_action_url)?>" class="settings-secondary">

            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

            <input type="hidden" name="action" value="remove_logo">

            <button class="btn outline" type="submit">Gunakan Logo Bawaan</button>

        </form>

        <?php endif; ?>

    </section>

    <section class="form-card">

        <h3>Community Rules</h3>

        <form method="post" action="<?=e($crs_action_url)?>">

            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

            <input type="hidden" name="action" value="save_rules">

            <label for="community_rules">Isi aturan komunitas</label>

            <textarea id="community_rules" name="community_rules" rows="9"><?=e(crs_setting('community_rules', "1. Saling menghormati dan gunakan bahasa yang santun.\n2. Bahas isi koleksi atau topik yang relevan.\n3. Hindari spam, ujaran kebencian, dan informasi pribadi.\n4. Hargai perbedaan pendapat dan sertakan sumber bila diperlukan."))?></textarea>

            <button class="btn primary" type="submit">Simpan Community Rules</button>

        </form>

    </section>

</div>

<section class="form-card settings-full">

    <h3>Identitas dan header komunitas</h3>

    <?php $headerImage = crs_setting('header_image_path'); ?>

    <?php if ($headerImage): ?><img class="admin-header-preview" src="<?=e(CRS_PLUGIN_URL . $headerImage)?>" alt="Preview header komunitas"><?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?=e($crs_action_url)?>" class="header-upload-form">

        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload_header">

        <label for="crs_header_image">Gambar header (PNG, JPG, WebP; maksimal 5 MB)</label><input id="crs_header_image" name="crs_header_image" type="file" accept="image/png,image/jpeg,image/webp" required><img id="crs-header-live-preview" class="admin-header-preview" alt="Preview gambar yang dipilih" hidden>

        <button class="btn outline" type="submit">Unggah Header</button>

    </form>

    <?php if ($headerImage): ?><form method="post" action="<?=e($crs_action_url)?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remove_header"><button class="btn outline" type="submit">Kembalikan Header Default</button></form><?php endif; ?>

    <form method="post" action="<?=e($crs_action_url)?>" class="crs-settings-form">

        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <input type="hidden" name="action" value="save_general_settings">

        <label for="community_name">Nama komunitas</label>

        <input id="community_name" name="community_name" value="<?=e(crs_setting('community_name', CRS_APP_NAME))?>" required>

        <label for="community_tagline">Tagline / keterangan navigasi</label>

        <input id="community_tagline" name="community_tagline" value="<?=e(crs_setting('community_tagline', 'Ruang diskusi koleksi perpustakaan'))?>">

        <label for="header_title">Judul header OPAC</label>

        <textarea id="header_title" name="header_title" rows="2"><?=e(crs_setting('header_title', "Setiap bacaan punya cerita.\nBagikan dan diskusikan."))?></textarea>

        <label for="header_description">Deskripsi header OPAC</label>

        <textarea id="header_description" name="header_description" rows="3"><?=e(crs_setting('header_description', 'Temukan percakapan seputar koleksi, bertukar pemahaman, dan belajar bersama pemustaka maupun pustakawan.'))?></textarea>

        <div class="nav-setting-row">

            <div>

                <b>Navigasi CRS pada OPAC SLiMS</b>

                <p class="muted">Aktifkan untuk menampilkan tautan CRS pada navigasi utama di seluruh halaman OPAC, sebelum menu pilihan bahasa.</p>

            </div>

            <label class="crs-switch">

                <input type="checkbox" name="opac_nav_enabled" value="1" <?=crs_setting('opac_nav_enabled', '1') === '1' ? 'checked' : ''?>>

                <span class="crs-switch-ui"></span>

                <span class="crs-switch-label">Tampilkan menu CRS</span>

            </label>

        </div>

        <button class="btn primary" type="submit">Simpan Pengaturan</button>

    </form>

</section>

<?php endif; ?>

<?php crs_admin_footer(); ?>
