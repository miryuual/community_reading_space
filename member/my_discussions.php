<?php
require_once __DIR__.'/../includes/functions.php'; require_login();
[$member,$staff]=actor_fields(current_user());
$sql='SELECT t.*,c.title collection_title,(SELECT COUNT(*) FROM crs_reply r WHERE r.id_topic=t.id_topic) reply_count FROM crs_topic t LEFT JOIN crs_collection c ON c.id_collection=t.id_collection WHERE '.($staff?'t.user_id=?':'t.member_id=?').' ORDER BY t.created_at DESC';
$s=db()->prepare($sql); $s->execute([$staff ?? $member]); $rows=$s->fetchAll();
page_header('Diskusi Saya'); ?>
<div class="section-head"><div><div class="eyebrow">AKTIVITAS SAYA</div><h1>Diskusi Saya</h1><p class="muted">Topik yang pernah kamu buat.</p></div><a class="btn primary" href="<?=e(crs_url('create_topic'))?>">＋ Mulai Diskusi</a></div>
<div class="topic-list"><?php foreach($rows as $t):?><article class="topic-card"><span class="status <?=strtolower($t['status'])?>"><?=e($t['status'])?></span><h3><a href="<?=e(crs_url('detail_topic'))?>&id=<?=$t['id_topic']?>"><?=e($t['title'])?></a></h3><p><?=e($t['collection_title'] ?: 'Tanpa koleksi terkait')?> · <?=$t['reply_count']?> tanggapan</p></article><?php endforeach;?><?php if(!$rows):?><div class="empty">Kamu belum membuat diskusi.</div><?php endif;?></div>
<?php page_footer(); ?>
