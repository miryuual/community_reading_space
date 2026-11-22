<?php

/**
 * Halaman khusus Trending Topic.
 * Peringkat dihitung dari interaksi: 2 poin per like + 1 poin per tanggapan.
 */

if (!defined('INDEX_AUTH')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

require_once __DIR__ . '/../includes/functions.php';

crs_ensure_feature_tables();

$q = trim((string) ($_GET['q'] ?? ''));

$sql = "
    SELECT
        t.*,
        cat.category_name,
        c.title AS collection_title,
        c.author,
        c.publication_year,

        (SELECT COUNT(*)
         FROM crs_topic_like l
         WHERE l.id_topic = t.id_topic
        ) AS like_count,

        (SELECT COUNT(*)
         FROM crs_reply r
         WHERE r.id_topic = t.id_topic
           AND r.is_hidden = 0
        ) AS reply_count,

        (
            (SELECT COUNT(*)
             FROM crs_topic_like l2
             WHERE l2.id_topic = t.id_topic
            ) * 2 +

            (SELECT COUNT(*)
             FROM crs_reply r2
             WHERE r2.id_topic = t.id_topic
               AND r2.is_hidden = 0
            )
        ) AS trend_score

    FROM crs_topic t

    JOIN crs_category cat
        ON cat.id_category = t.id_category

    LEFT JOIN crs_collection c
        ON c.id_collection = t.id_collection

    WHERE t.is_hidden = 0
";

$args = [];

if ($q !== '') {
    $sql .= "
        AND (
            t.title LIKE ?
            OR t.content LIKE ?
            OR cat.category_name LIKE ?
            OR c.title LIKE ?
            OR c.author LIKE ?
        )
    ";

    $term = '%' . $q . '%';

    $args = [
        $term,
        $term,
        $term,
        $term,
        $term,
    ];
}

$sql .= "
    ORDER BY
        trend_score DESC,
        t.is_pinned DESC,
        t.created_at DESC
    LIMIT 100
";

$stmt = crs_db()->prepare($sql);
$stmt->execute($args);

$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

page_header('Trending Topic');
?>

<section class="trending-hero">
    <div class="eyebrow">COMMUNITY READING SPACE</div>

    <h1>Trending Topic</h1>

</section>

<div class="section-head">
    <div>
        <h2>Yang sedang ramai di Komunitas</h2>
    </div>

    <a class="btn outline" href="<?= e(crs_url()) ?>">
        ← Kembali ke diskusi
    </a>
</div>

<form class="searchbar trending-search" method="get">
    <input
        type="hidden"
        name="p"
        value="community_reading_space"
    >

    <input
        type="hidden"
        name="crs_page"
        value="trending"
    >

    <span>⌕</span>

    <input
        type="search"
        name="q"
        value="<?= e($q) ?>"
        placeholder="Cari topik yang sedang tren..."
    >

    <button class="btn dark" type="submit">
        Cari
    </button>
</form>

<?php if (!$topics): ?>

    <div class="empty">
        <div class="empty-icon">↗</div>

        <h3>Belum ada topik trending</h3>

        <p>
            Diskusi akan muncul di sini setelah mendapatkan like atau tanggapan.
        </p>
    </div>

<?php else: ?>

    <div class="topic-list trending-list">

        <?php foreach ($topics as $rank => $t): ?>

            <article class="topic-card trending-card">
                <div class="trending-rank">
                    #<?= (int) ($rank + 1) ?>
                </div>

                <div class="trending-content">

                    <div class="topic-meta">
                        <span class="tag">
                            <?= e($t['category_name']) ?>
                        </span>

                        <span class="status <?= strtolower(e($t['status'])) ?>">
                            <?= $t['status'] === 'Open'
                                ? 'Belum Terjawab'
                                : (
                                    $t['status'] === 'Answered'
                                        ? 'Terselesaikan'
                                        : 'Ditutup'
                                )
                            ?>
                        </span>

                        <time>
                            <?= e(date('d M Y', strtotime($t['created_at']))) ?>
                        </time>
                    </div>

                    <h3>
                        <a href="<?= e(crs_url('detail_topic') . '&id=' . (int) $t['id_topic']) ?>">
                            <?= e($t['title']) ?>
                        </a>
                    </h3>

                    <p class="topic-excerpt">
                        <?= e(mb_strimwidth(strip_tags($t['content']), 0, 220, '…')) ?>
                    </p>

                    <?php if (!empty($t['collection_title'])): ?>

                        <div class="collection-line">
                            <span class="mini-book">▤</span>

                            <span>
                                <b>
                                    <?= e($t['collection_title']) ?>
                                </b>

                                <small>
                                    <?= e($t['author'] ?? '') ?><?= !empty($t['publication_year']) ? ' · ' . e($t['publication_year']) : '' ?>
                                </small>
                            </span>
                        </div>

                    <?php endif; ?>

                    <div class="topic-foot">
                        <span>
                            Oleh <?= e(actor_name($t['member_id'], $t['user_id'])) ?>
                        </span>

                        <span class="trending-score">
                            ↗ <?= (int) $t['trend_score'] ?> poin
                        </span>

                        <span>
                            ♥ <?= (int) $t['like_count'] ?> suka
                        </span>

                        <span>
                            ▤ <?= (int) $t['reply_count'] ?> tanggapan
                        </span>

                        <a href="<?= e(crs_url('detail_topic') . '&id=' . (int) $t['id_topic']) ?>">
                            Buka diskusi →
                        </a>
                    </div>

                </div>
            </article>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<?php page_footer(); ?>