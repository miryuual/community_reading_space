<?php

// Plugin dijalankan melalui plugin_container SLiMS.
if (!defined('INDEX_AUTH')) {
    http_response_code(403);
    exit('Akses harus melalui administrasi SLiMS.');
}

// Plugin menu SLiMS dimuat melalui plugin_container.php.
// Mulai sesi admin agar identitas pustakawan dapat dibaca.
if (!defined('SB')) {
    http_response_code(500);
    exit('Konfigurasi SLiMS (SB) tidak tersedia.');
}
require_once SB . 'admin/default/session.inc.php';

require_once __DIR__ . '/../includes/functions.php';

// Memeriksa login pustakawan SLiMS.
require_staff();

// Endpoint resmi menu plugin SLiMS. ID harus sama dengan md5(realpath(path))
// yang dibuat oleh Plugins::registerMenu().
$crs_plugin_id = md5(realpath(__FILE__));
$crs_action_url = AWB . 'plugin_container.php?mod=system&id=' . $crs_plugin_id;


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

    // Memisahkan perintah SQL berdasarkan titik koma di akhir baris.
    $statements = preg_split('/;\s*(?:\r\n|\r|\n|$)/', trim($sql));

    foreach ($statements as $statement) {
        $statement = trim($statement);

        if ($statement !== '') {
            crs_db()->exec($statement);
        }
    }
}

/**
 * Menyinkronkan katalog SLiMS ke tabel koleksi CRS.
 */
function crs_sync_catalog(): int
{
    $pdo = crs_db();

    // SLiMS menyimpan pengarang pada tabel relasi biblio_author dan
    // mst_author, bukan pada kolom biblio.author.
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

            $count = crs_sync_catalog();

            crs_flash(
                "Tabel CRS siap. {$count} data koleksi berhasil disinkronkan dari katalog SLiMS."
            );

        } elseif ($action === 'sync') {

            if (!crs_tables_ready()) {
                throw new RuntimeException('Tabel CRS belum tersedia.');
            }

            $count = crs_sync_catalog();

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

            crs_flash('Perubahan moderasi berhasil disimpan.');
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
            WHERE r.id_topic = t.id_topic
        ) AS reply_count
    FROM crs_topic t
    JOIN crs_collection c
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

<div class="eyebrow">PENGELOLAAN KOMUNITAS</div>
<p class="muted">
    Pantau aktivitas, kelola diskusi, dan tindak lanjuti laporan konten.
</p>

<div class="form-card">

    <b>Koneksi katalog SLiMS aktif.</b>

    <p class="muted">
        Koleksi CRS disinkronkan dari tabel bibliografi SLiMS.
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
            value="sync"
        >

        <button class="btn outline">
            Sinkronkan Katalog Sekarang
        </button>

        <a class="btn primary" href="<?= e(CRS_OPAC_URL) ?>">
            Buka Ruang Diskusi
        </a>

    </form>

</div>

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
                            <?= e($t['collection_title']) ?>
                        </small>
                    </td>

                    <td>
                        <?= e($t['status']) ?>
                    </td>

                    <td>
                        <?= (int) $t['reply_count'] ?> tanggapan
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

<div class="section-head">
    <h2>Laporan konten</h2>
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

<?php crs_admin_footer(); ?>
