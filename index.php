
<?php

/**
 * Community Reading Space
 * Halaman utama dan penjelajahan diskusi.
 */

// Pastikan halaman dijalankan melalui SLiMS.
if (!defined('INDEX_AUTH')) {
    http_response_code(403);
    exit('Akses harus melalui website/OPAC SLiMS.');
}
// Memuat fungsi CRS.
require_once __DIR__ . '/includes/functions.php';

// Mengambil halaman internal CRS.
$crsPage = $_GET['crs_page'] ?? '';

$allowedPages = [
    'create_topic',
    'detail_topic',
    'my_discussions',
    'community_rules',
    'trending'
];

// Pastikan skema tersedia sebelum membuka halaman internal.
if (!crs_tables_ready()) {

    page_header('Community Reading Space');

    echo '
    <section class="empty">
        <h2>Community Reading Space belum disiapkan</h2>
        <p>
            Silakan hubungi pustakawan untuk menyiapkan tabel
            dan melakukan sinkronisasi katalog SLiMS.
        </p>
    </section>
    ';

    page_footer();

    return;
}

// Pastikan tabel fitur (like dan pengaturan) tersedia untuk seluruh halaman CRS.
crs_ensure_feature_tables();

// Halaman khusus anggota.
if (in_array($crsPage, $allowedPages, true)) {
    require __DIR__ . '/member/' . $crsPage . '.php';
    return;
}

// Like dari kartu diskusi.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'like_topic') {
    verify_csrf();
    $identity = crs_actor_identity();
    $topicId = (int)($_POST['id_topic'] ?? 0);
    if (!$identity) {
        header('Location: ' . rtrim(SWB, '/') . '/index.php?p=member');
        exit;
    }
    $check = crs_db()->prepare('SELECT COUNT(*) FROM crs_topic WHERE id_topic=? AND is_hidden=0');
    $check->execute([$topicId]);
    if ($topicId > 0 && $check->fetchColumn()) {
        $exists = crs_db()->prepare('SELECT id_like FROM crs_topic_like WHERE id_topic=? AND actor_type=? AND actor_id=?');
        $exists->execute([$topicId, $identity['type'], $identity['id']]);
        if ($exists->fetchColumn()) {
            crs_db()->prepare('DELETE FROM crs_topic_like WHERE id_topic=? AND actor_type=? AND actor_id=?')
                ->execute([$topicId, $identity['type'], $identity['id']]);
        } else {
            crs_db()->prepare('INSERT IGNORE INTO crs_topic_like(id_topic,actor_type,actor_id) VALUES(?,?,?)')
                ->execute([$topicId, $identity['type'], $identity['id']]);
        }
    }
    $returnTo = $_SERVER['REQUEST_URI'] ?? crs_url();
    header('Location: ' . $returnTo);
    exit;
}

// Mengambil parameter pencarian.
$q = trim($_GET['q'] ?? '');

$status = $_GET['status'] ?? 'all';

// Menentukan kondisi pencarian.
$where = [
    't.is_hidden = 0'
];

$args = [];

// Filter status diskusi.
if ($status === 'open') {

    $where[] = "t.status = 'Open'";

} elseif ($status === 'answered') {

    $where[] = "t.status = 'Answered'";

} elseif ($status === 'closed') {

    $where[] = "t.status = 'Closed'";
}

// Pencarian berdasarkan judul, isi, dan koleksi.
if ($q !== '') {

    $where[] = '
        (
            t.title LIKE ?
            OR t.content LIKE ?
            OR c.title LIKE ?
            OR c.author LIKE ?
        )
    ';

    $like = '%' . $q . '%';

    array_push(
        $args,
        $like,
        $like,
        $like,
        $like
    );
}

// Query daftar diskusi.
$sql = "
    SELECT
        t.*,

        c.title AS collection_title,
        c.author,
        c.publication_year,

        cat.category_name,

        (
            SELECT COUNT(*)
            FROM crs_reply r
            WHERE r.id_topic = t.id_topic
            AND r.is_hidden = 0
        ) AS reply_count,

        (
            SELECT COUNT(*)
            FROM crs_topic_like v
            WHERE v.id_topic = t.id_topic
        ) AS vote_count

    FROM crs_topic t

    LEFT JOIN crs_collection c
        ON c.id_collection = t.id_collection

    JOIN crs_category cat
        ON cat.id_category = t.id_category

    WHERE " . implode(' AND ', $where) . "

    ORDER BY
        t.is_pinned DESC,
        CASE WHEN ? = 'trending' THEN (
            (SELECT COUNT(*) FROM crs_topic_like tl WHERE tl.id_topic=t.id_topic) * 2 +
            (SELECT COUNT(*) FROM crs_reply tr WHERE tr.id_topic=t.id_topic AND tr.is_hidden=0)
        ) ELSE 0 END DESC,
        t.created_at DESC
";

// Menjalankan query.
$s = crs_db()->prepare($sql);

$sort = ($_GET['sort'] ?? '') === 'trending' ? 'trending' : 'recent';
$args[] = $sort;
$s->execute($args);

$topics = $s->fetchAll(PDO::FETCH_ASSOC);

// Menampilkan halaman.
page_header('Jelajahi Diskusi');

?>

<!-- HERO SECTION -->

<?php $crsHeaderImage = crs_setting('header_image_path'); ?>
<section id="crs-community" class="hero" style="<?= $crsHeaderImage ? 'background-image:linear-gradient(90deg,rgba(255,255,255,.94),rgba(255,255,255,.78)),url(' . e(CRS_PLUGIN_URL . $crsHeaderImage) . ');background-size:cover;background-position:center;' : 'background:#f4f6f8;' ?>">

    <div>

        <div class="eyebrow">
            <?=e(crs_setting('community_name', CRS_APP_NAME))?>
        </div>

        <h1 class="crs-custom-header-title">
            <?=nl2br(e(crs_setting('header_title', 'Setiap bacaan punya cerita.\nBagikan dan diskusikan.')))?>
        </h1>

        <p>
            <?=e(crs_setting('header_description', 'Temukan percakapan seputar koleksi, bertukar pemahaman, dan belajar bersama pemustaka maupun pustakawan.'))?>
        </p>

    </div>

    <div class="hero-art">

        <span class="book book-one"></span>

        <span class="book book-two"></span>

        <span class="book book-three"></span>

        <div class="hero-note">
            READ · SHARE · DISCUSS
        </div>

    </div>

</section>


<!-- JUDUL DAN TOMBOL DISKUSI -->

<div class="section-head">

    <div>

        <div class="eyebrow">
            RUANG KOMUNITAS
        </div>

        <h2>
            Jelajahi diskusi
        </h2>

    </div>

    <a
        class="btn primary"
        href="<?= e(crs_url('create_topic')) ?>"
    >
        ＋ Mulai Diskusi
    </a>

</div>


<!-- FORM PENCARIAN -->

<form
    class="searchbar"
    method="get"
>

    <input
        type="hidden"
        name="p"
        value="community_reading_space"
    >

    <span>⌕</span>

    <input
        type="text"
        name="q"
        value="<?= e($q) ?>"
        placeholder="Cari judul diskusi, koleksi, pengarang, atau kata kunci..."
    >

    <?php if ($status !== 'all'): ?>

        <input
            type="hidden"
            name="status"
            value="<?= e($status) ?>"
        >

    <?php endif; ?>

    <?php if ($sort === 'trending'): ?>
        <input type="hidden" name="sort" value="trending">
    <?php endif; ?>

    <button
        type="submit"
        class="btn dark"
    >
        Cari
    </button>

</form>


<div class="filter-toolbar">
<div class="tabs sort-tabs">
    <a class="<?= $sort !== 'trending' ? 'active' : '' ?>" href="<?=e(crs_url())?><?= $q !== '' ? '&q='.rawurlencode($q) : '' ?>&status=<?=e(rawurlencode($status))?>">Terbaru</a>
    <a class="<?= $sort === 'trending' ? 'active' : '' ?>" href="<?=e(crs_url())?><?= $q !== '' ? '&q='.rawurlencode($q) : '' ?>&status=<?=e(rawurlencode($status))?>&sort=trending">Trending Topic</a>
</div>

<!-- FILTER STATUS DISKUSI -->

<div class="tabs status-tabs">

    <?php

    $filters = [
        'all'      => 'Semua',
        'open'     => 'Belum Terjawab',
        'answered' => 'Terselesaikan',
        'closed'   => 'Ditutup'
    ];

    foreach ($filters as $key => $label):

        $active = ($status === $key);

        $filterUrl = crs_url();

        if ($q !== '') {
            $filterUrl .= '&q=' . rawurlencode($q);
        }

        $filterUrl .= '&status=' . rawurlencode($key);
        if ($sort === 'trending') $filterUrl .= '&sort=trending';

    ?>

        <a
            class="<?= $active ? 'active' : '' ?>"
            href="<?= e($filterUrl) ?>"
        >
            <?= e($label) ?>
        </a>

    <?php endforeach; ?>

</div>
</div>


<!-- DAFTAR DISKUSI -->

<?php if (empty($topics)): ?>

    <div class="empty">

        <div class="empty-icon">
            ☷
        </div>

        <h3>
            Belum ada diskusi ditemukan
        </h3>

        <p>
            Coba kata kunci lain atau mulai diskusi baru.
        </p>

    </div>

<?php else: ?>

    <div class="topic-list">

        <?php foreach ($topics as $t): ?>

            <article class="topic-card">

                <!-- INFORMASI STATUS -->

                <div class="topic-meta">

                    <span class="tag">
                        <?= e($t['category_name']) ?>
                    </span>

                    <?php if (!empty($t['is_pinned'])): ?>

                        <span class="tag pinned">
                            Disematkan
                        </span>

                    <?php endif; ?>

                    <span class="status <?= strtolower(e($t['status'])) ?>">

                        <?php

                        if ($t['status'] === 'Open') {

                            echo 'Belum Terjawab';

                        } elseif ($t['status'] === 'Answered') {

                            echo 'Terselesaikan';

                        } else {

                            echo 'Ditutup';

                        }

                        ?>

                    </span>

                    <time>
                        <?= e(date(
                            'd M Y',
                            strtotime($t['created_at'])
                        )) ?>
                    </time>

                </div>


                <!-- JUDUL DISKUSI -->

                <h3>

                    <a href="<?= e(
                        crs_url('detail_topic') .
                        '&id=' . (int)$t['id_topic']
                    ) ?>">

                        <?= e($t['title']) ?>

                    </a>

                </h3>


                <!-- RINGKASAN DISKUSI -->

                <p class="topic-excerpt">

                    <?= e(
                        mb_strimwidth(
                            strip_tags($t['content']),
                            0,
                            190,
                            '…'
                        )
                    ) ?>

                </p>


                <!-- INFORMASI KOLEKSI -->

                <?php if (!empty($t['collection_title'])): ?>
                    <div class="collection-line">
                        <span class="mini-book">▤</span>
                        <span>
                            <b><?= e($t['collection_title']) ?></b>
                            <small><?= e($t['author'] ?? '') ?><?= !empty($t['publication_year']) ? ' · ' . e($t['publication_year']) : '' ?></small>
                        </span>
                    </div>
                <?php endif; ?>


                <!-- INFORMASI TAMBAHAN -->

                <div class="topic-foot">

                    <span>

                        Oleh

                        <?= e(
                            actor_name(
                                $t['member_id'],
                                $t['user_id']
                            )
                        ) ?>

                    </span>

                    <?php if (current_user()): ?>
                        <form method="post" class="card-like-form">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="like_topic">
                            <input type="hidden" name="id_topic" value="<?=(int)$t['id_topic']?>">
                            <button class="text-button" type="submit"><?=crs_topic_liked((int)$t['id_topic'])?'♥ Disukai':'♡ Suka'?> (<?= (int)$t['vote_count'] ?>)</button>
                        </form>
                    <?php else: ?>
                        <span>♥ <?= (int)$t['vote_count'] ?> suka</span>
                    <?php endif; ?>

                    <span>
                        ▤ <?= (int)$t['reply_count'] ?> tanggapan
                    </span>

                    <a href="<?= e(
                        crs_url('detail_topic') .
                        '&id=' . (int)$t['id_topic']
                    ) ?>">

                        Buka diskusi →

                    </a>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<?php

// Menutup halaman.
page_footer();

?>
