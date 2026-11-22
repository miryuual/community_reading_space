<?php
require_once __DIR__ . '/../includes/functions.php';
if (crs_tables_ready()) crs_ensure_feature_tables();

$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT t.*, c.title collection_title, c.author, c.publication_year, c.collection_type, cat.category_name
    FROM crs_topic t
    LEFT JOIN crs_collection c ON c.id_collection=t.id_collection
    INNER JOIN crs_category cat ON cat.id_category=t.id_category
    WHERE t.id_topic=?');
$s->execute([$id]);
$topic = $s->fetch(PDO::FETCH_ASSOC);

if (!$topic || ($topic['is_hidden'] && !is_staff())) {
    header('Location: ' . crs_url());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $actor = current_user();
    [$member, $staff] = actor_fields($actor);

    if (in_array($action, ['like_topic', 'vote_reply', 'solution', 'report_topic', 'report_reply'], true) && !$actor) {
        http_response_code(403);
        exit('Masuk melalui SLiMS untuk menggunakan fitur ini.');
    }

    if ($action === 'reply' && $topic['status'] !== 'Closed') {
        $content = trim($_POST['content'] ?? '');
        $parent = (int)($_POST['parent_reply_id'] ?? 0) ?: null;
        if ($content !== '') {
            $s = db()->prepare('INSERT INTO crs_reply(id_topic,member_id,user_id,parent_reply_id,content) VALUES(?,?,?,?,?)');
            $s->execute([$id, $member, $staff, $parent, $content]);
            if ($topic['status'] === 'Open' && is_staff()) {
                db()->prepare("UPDATE crs_topic SET status='Answered' WHERE id_topic=?")->execute([$id]);
            }
            flash('Tanggapan berhasil dikirim.');
        }
    } elseif ($action === 'like_topic') {
        $identity = crs_actor_identity();
        if ($identity) {
            $exists = db()->prepare('SELECT id_like FROM crs_topic_like WHERE id_topic=? AND actor_type=? AND actor_id=?');
            $exists->execute([$id, $identity['type'], $identity['id']]);
            if ($exists->fetchColumn()) {
                $del = db()->prepare('DELETE FROM crs_topic_like WHERE id_topic=? AND actor_type=? AND actor_id=?');
                $del->execute([$id, $identity['type'], $identity['id']]);
            } else {
                $ins = db()->prepare('INSERT IGNORE INTO crs_topic_like(id_topic,actor_type,actor_id) VALUES(?,?,?)');
                $ins->execute([$id, $identity['type'], $identity['id']]);
            }
        }
    } elseif ($action === 'vote_reply') {
        $rid = (int)($_POST['reply_id'] ?? 0);
        $valid = db()->prepare('SELECT COUNT(*) FROM crs_reply WHERE id_reply=? AND id_topic=?');
        $valid->execute([$rid, $id]);
        if ($rid > 0 && $valid->fetchColumn()) {
            $s = db()->prepare('SELECT id_vote FROM crs_vote WHERE id_reply=? AND ((member_id IS NOT NULL AND member_id=?) OR (user_id IS NOT NULL AND user_id=?))');
            $s->execute([$rid, $member, $staff]);
            $old = $s->fetchColumn();
            if ($old) db()->prepare('DELETE FROM crs_vote WHERE id_vote=?')->execute([$old]);
            else db()->prepare('INSERT INTO crs_vote(id_reply,member_id,user_id) VALUES(?,?,?)')->execute([$rid, $member, $staff]);
        }
    } elseif ($action === 'solution') {
        $rid = (int)($_POST['reply_id'] ?? 0);
        if ($actor && topic_author_matches($topic, $actor)) {
            $valid = db()->prepare('SELECT COUNT(*) FROM crs_reply WHERE id_reply=? AND id_topic=?');
            $valid->execute([$rid, $id]);
            if ($valid->fetchColumn()) {
                db()->beginTransaction();
                db()->prepare('UPDATE crs_reply SET is_solution=0 WHERE id_topic=?')->execute([$id]);
                db()->prepare('UPDATE crs_reply SET is_solution=1 WHERE id_reply=? AND id_topic=?')->execute([$rid, $id]);
                db()->prepare("UPDATE crs_topic SET status='Answered' WHERE id_topic=?")->execute([$id]);
                db()->commit();
                flash('Tanggapan ditandai sebagai jawaban terbaik.');
            }
        }
    } elseif ($action === 'report_topic' || $action === 'report_reply') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason !== '') {
            $rid = $action === 'report_reply' ? (int)($_POST['reply_id'] ?? 0) : null;
            $s = db()->prepare('INSERT INTO crs_report(id_topic,id_reply,member_id,user_id,reason) VALUES(?,?,?,?,?)');
            $s->execute([$action === 'report_topic' ? $id : null, $rid, $member, $staff, $reason]);
            flash('Laporan dikirim kepada pustakawan.');
        }
    }

    header('Location: ' . crs_url('detail_topic') . '&id=' . $id);
    exit;
}

$re = db()->prepare('SELECT r.*, (SELECT COUNT(*) FROM crs_vote v WHERE v.id_reply=r.id_reply) vote_count
    FROM crs_reply r WHERE r.id_topic=? AND (r.is_hidden=0 OR ?) ORDER BY r.created_at');
$re->execute([$id, is_staff() ? 1 : 0]);
$replies = $re->fetchAll(PDO::FETCH_ASSOC);
$likes = crs_topic_like_count($id);
$liked = crs_topic_liked($id);

page_header('Detail Diskusi');
?>
<a class="back" href="<?=e(crs_url())?>">← Semua diskusi</a>
<article class="detail-card">
    <div class="topic-meta">
        <span class="tag"><?=e($topic['category_name'])?></span>
        <span class="status <?=e(strtolower($topic['status']))?>"><?= $topic['status']==='Open'?'Belum Terjawab':($topic['status']==='Answered'?'Terselesaikan':'Ditutup') ?></span>
        <?php if ($topic['is_pinned']): ?><span class="tag pinned">Disematkan</span><?php endif; ?>
    </div>
    <h1><?=e($topic['title'])?></h1>
    <div class="byline">Oleh <b><?=e(actor_name($topic['member_id'], $topic['user_id']))?></b> · <?=e(date('d M Y, H:i', strtotime($topic['created_at'])))?></div>

    <?php if (!empty($topic['collection_title'])): ?>
    <div class="collection-panel">
        <div class="collection-cover">▤</div>
        <div>
            <small>KOLEKSI YANG DIBAHAS</small>
            <b><?=e($topic['collection_title'])?></b>
            <span><?=e($topic['author'] ?? '')?><?=!empty($topic['collection_type'])?' · '.e($topic['collection_type']):''?><?=!empty($topic['publication_year'])?' · '.e($topic['publication_year']):''?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="prose"><?=nl2br(e($topic['content']))?></div>
    <div class="action-row">
        <?php if (current_user()): ?>
            <form method="post" class="inline-action">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="like_topic">
                <button class="btn <?= $liked ? 'primary' : 'outline' ?>" type="submit" aria-pressed="<?=$liked?'true':'false'?>"><?=$liked?'♥ Disukai':'♡ Suka'?> (<?=$likes?>)</button>
            </form>
            <details class="report"><summary>Laporkan</summary>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="report_topic">
                    <textarea name="reason" required placeholder="Alasan laporan"></textarea>
                    <button class="btn small dark">Kirim laporan</button>
                </form>
            </details>
        <?php else: ?>
            <span class="like-count">♥ <?=$likes?> suka</span>
            <a href="<?=e(rtrim(SWB, '/') . '/index.php?p=member')?>">Masuk untuk menyukai atau melaporkan diskusi</a>
        <?php endif; ?>
    </div>
</article>

<section class="replies">
    <div class="section-head"><div><div class="eyebrow">DISKUSI KOMUNITAS</div><h2>Tanggapan (<?=count($replies)?>)</h2></div></div>
    <?php foreach ($replies as $r): ?>
        <article class="reply-card <?=$r['is_solution']?'solution':''?>">
            <div class="reply-head">
                <b><?=e(actor_name($r['member_id'], $r['user_id']))?></b>
                <?php if ($r['user_id']): ?><span class="staff-label">PUSTAKAWAN</span><?php endif; ?>
                <?php if ($r['is_solution']): ?><span class="solution-label">✓ Jawaban terbaik</span><?php endif; ?>
                <time><?=e(date('d M Y, H:i', strtotime($r['created_at'])))?></time>
            </div>
            <div class="prose"><?=nl2br(e($r['content']))?></div>
            <div class="reply-actions">
                <?php if (current_user()): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="action" value="vote_reply">
                        <input type="hidden" name="reply_id" value="<?=(int)$r['id_reply']?>">
                        <button class="text-button">♡ Dukung (<?=(int)$r['vote_count']?>)</button>
                    </form>
                    <?php if (topic_author_matches($topic, current_user()) && !$r['is_solution'] && $topic['status'] !== 'Closed'): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="solution">
                            <input type="hidden" name="reply_id" value="<?=(int)$r['id_reply']?>">
                            <button class="text-button">Tandai jawaban terbaik</button>
                        </form>
                    <?php endif; ?>
                    <details class="report"><summary>Laporkan</summary>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="report_reply">
                            <input type="hidden" name="reply_id" value="<?=(int)$r['id_reply']?>">
                            <textarea name="reason" required placeholder="Alasan laporan"></textarea>
                            <button class="btn small dark">Kirim</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if ($topic['status'] !== 'Closed'): ?>
        <form class="form-card reply-form" method="post">
            <h3>Berikan tanggapan</h3>
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="reply">
            <textarea name="content" rows="4" required placeholder="Tuliskan tanggapan yang relevan dan santun..."></textarea>
            <button class="btn primary">Kirim Tanggapan</button>
            <?php if (!current_user()): ?><p class="muted">Tanggapan pengunjung akan ditampilkan dengan identitas "Pengunjung". Masuk melalui SLiMS untuk menggunakan fitur suka dan laporan.</p><?php endif; ?>
        </form>
    <?php else: ?>
        <div class="alert">Diskusi ini telah ditutup. Tanggapan baru tidak dapat ditambahkan.</div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
