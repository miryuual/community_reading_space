<?php
require_once __DIR__ . '/../includes/functions.php';
if (crs_tables_ready()) crs_ensure_feature_tables();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $collection = (int)($_POST['id_collection'] ?? 0);
    $category = (int)($_POST['id_category'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $catCheck = db()->prepare('SELECT COUNT(*) FROM crs_category WHERE id_category=?');
    $catCheck->execute([$category]);
    $collectionOk = true;
    if ($collection > 0) {
        $colCheck = db()->prepare('SELECT COUNT(*) FROM crs_collection WHERE id_collection=?');
        $colCheck->execute([$collection]);
        $collectionOk = (bool)$colCheck->fetchColumn();
    }

    if (!$catCheck->fetchColumn() || !$collectionOk || $title === '' || $content === '') {
        $err = 'Pilih kategori yang valid, lalu lengkapi judul dan isi diskusi.';
    } else {
        [$member, $staff] = actor_fields(current_user());
        $s = db()->prepare('INSERT INTO crs_topic(id_collection,id_category,member_id,user_id,title,content) VALUES(?,?,?,?,?,?)');
        $s->execute([$collection > 0 ? $collection : null, $category, $member, $staff, $title, $content]);
        flash('Topik diskusi berhasil dibuat.');
        header('Location: ' . crs_url('detail_topic') . '&id=' . db()->lastInsertId());
        exit;
    }
}

$collections = db()->query('SELECT * FROM crs_collection ORDER BY title')->fetchAll();
$categories = db()->query('SELECT * FROM crs_category ORDER BY category_name')->fetchAll();
page_header('Mulai Diskusi');
?>
<div class="narrow">
    <a class="back" href="<?=e(crs_url())?>">← Kembali ke diskusi</a>
    <div class="eyebrow">MULAI PERCAKAPAN</div>
    <h1>Buat topik diskusi</h1>
    <p class="muted">Pilih kategori diskusi. Menghubungkan diskusi dengan koleksi bersifat opsional.</p>
    <?php if ($err): ?><div class="alert danger"><?=e($err)?></div><?php endif; ?>
    <form class="form-card" method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <label for="id_category">Kategori diskusi</label>
        <select id="id_category" name="id_category" required>
            <option value="">Pilih kategori</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?=(int)$c['id_category']?>" <?=((int)($_POST['id_category'] ?? 0)===(int)$c['id_category'])?'selected':''?>><?=e($c['category_name'])?></option>
            <?php endforeach; ?>
        </select>

        <label for="id_collection">Koleksi yang dibahas <span class="optional-label">(Opsional)</span></label>
        <select id="id_collection" name="id_collection">
            <option value="">Tidak mengaitkan koleksi</option>
            <?php foreach ($collections as $c): ?>
                <option value="<?=(int)$c['id_collection']?>" <?=((int)($_POST['id_collection'] ?? 0)===(int)$c['id_collection'])?'selected':''?>><?=e($c['title'])?><?=!empty($c['author'])?' — '.e($c['author']):''?></option>
            <?php endforeach; ?>
        </select>

        <label for="title">Judul diskusi</label>
        <input id="title" name="title" maxlength="255" required value="<?=e($_POST['title'] ?? '')?>" placeholder="Contoh: Bagaimana menerapkan gagasan utama buku ini?">

        <label for="content">Isi diskusi</label>
        <textarea id="content" name="content" rows="7" required placeholder="Tuliskan pertanyaan, ulasan, interpretasi, atau rekomendasi..."><?=e($_POST['content'] ?? '')?></textarea>

        <div class="form-actions">
            <a class="btn outline" href="<?=e(crs_url())?>">Batal</a>
            <button class="btn primary" type="submit">Terbitkan Diskusi</button>
        </div>
    </form>
</div>
<?php page_footer(); ?>
