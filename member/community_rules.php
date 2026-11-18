<?php
require_once __DIR__ . '/../includes/functions.php';
page_header('Community Rules');
$rules = trim(crs_setting('community_rules', "1. Saling menghormati dan gunakan bahasa yang santun.\n2. Bahas isi koleksi atau topik yang relevan.\n3. Hindari spam, ujaran kebencian, dan informasi pribadi.\n4. Hargai perbedaan pendapat dan sertakan sumber bila diperlukan."));
?>
<div class="narrow">
    <a class="back" href="<?=e(crs_url())?>">← Kembali ke diskusi</a>
    <div class="eyebrow">PANDUAN KOMUNITAS</div>
    <h1>Community Rules</h1>
    <p class="muted">Panduan berpartisipasi dalam Community Reading Space.</p>
    <article class="detail-card rules-content"><?=nl2br(e($rules))?></article>
</div>
<?php page_footer(); ?>
