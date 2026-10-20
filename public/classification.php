<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'request' && can('class.request')) {
        $r = one('SELECT * FROM records WHERE id=?', [(int)post('record_id')]);
        $basis = post('basis');
        if (!$r || !can_view_record($r)) flash('err', 'Record not found.');
        elseif ($basis === '') flash('err', 'A basis or reason is required.');
        elseif (one("SELECT id FROM class_requests WHERE record_id=? AND status='pending'", [$r['id']])) flash('err', 'A request for this record is already pending.');
        else {
            $new = $r['access_level'] === 'open' ? 'restricted' : 'open';
            $id = insert('class_requests', ['record_id' => $r['id'], 'old_level' => $r['access_level'], 'new_level' => $new, 'basis' => $basis, 'requested_by' => $_SESSION['uid']]);
            add_history((int)$r['id'], 'class_requested', null, null, "Request to make $new: $basis");
            audit('class_requested', 'record', $r['id'], "$new: $basis");
            notify_role('admin', "Classification review needed for {$r['inventory_no']}", 'classification.php');
            flash('ok', 'Request submitted for authorized review.');
        }
        redirect('record.php?id=' . (int)post('record_id'));
    }
    if ($act === 'review' && can('class.approve')) {
        $req = one("SELECT * FROM class_requests WHERE id=? AND status='pending'", [(int)post('id')]);
        $approve = post('decision') === 'approve';
        if (!$req) flash('err', 'Request not found or already decided.');
        elseif ((int)$req['requested_by'] === (int)$_SESSION['uid']) flash('err', 'You cannot review your own request. Ask another authorized reviewer.');
        else {
            q('UPDATE class_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?', [$approve ? 'approved' : 'rejected', $_SESSION['uid'], post('note') ?: null, $req['id']]);
            if ($approve) q('UPDATE records SET access_level=?, restriction_basis=? WHERE id=?', [$req['new_level'], $req['new_level'] === 'restricted' ? $req['basis'] : null, $req['record_id']]);
            add_history((int)$req['record_id'], 'class_' . ($approve ? 'approved' : 'rejected'), null, null, post('note') ?: null);
            audit('class_' . ($approve ? 'approved' : 'rejected'), 'record', $req['record_id'], $req['old_level'] . ' -> ' . $req['new_level']);
            notify((int)$req['requested_by'], 'Your classification request was ' . ($approve ? 'approved' : 'rejected'), 'record.php?id=' . $req['record_id']);
            flash('ok', 'Decision recorded.');
        }
        redirect('classification.php');
    }
}
[$vw, $vp] = visible_where('r');
$base = "SELECT q.*, r.inventory_no, r.title, a.full_name requester, b.full_name reviewer FROM class_requests q
         JOIN records r ON r.id=q.record_id JOIN users a ON a.id=q.requested_by LEFT JOIN users b ON b.id=q.reviewed_by WHERE $vw";
$pending = all("$base AND q.status='pending' ORDER BY q.id", $vp);
$done = all("$base AND q.status<>'pending' ORDER BY q.id DESC LIMIT 100", $vp);
$summary = all("SELECT access_level, COUNT(*) n FROM records r WHERE $vw GROUP BY access_level", $vp);
page_top('Classification and access control');
?>
<div class="note">Access can only change through a request with a stated basis, reviewed by an authorized person other than the requester. Restricted files are hidden from roles without permission.</div>
<?php tabs_begin('Classification', ['pending' => 'Pending review', 'decisions' => 'Decision record']); tab_open('pending'); ?>
<div class="card"><h2>Pending review</h2><div class="tw"><table><tr><th>Record</th><th>Change</th><th>Basis</th><th>Requested by</th><th></th></tr>
<?php foreach ($pending as $p): ?><tr><td><a href="record.php?id=<?= $p['record_id'] ?>"><?= e($p['inventory_no']) ?></a> <?= e($p['title']) ?></td>
<td><?= e($p['old_level']) ?> → <?= e($p['new_level']) ?></td><td><?= e($p['basis']) ?></td><td><?= e($p['requester']) ?> <span class="muted"><?= fmt_dt($p['requested_at']) ?></span></td>
<td><?php if (can('class.approve')): ?><form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?= $p['id'] ?>">
<input name="note" placeholder="Note"><button name="decision" value="approve" class="sm">Approve</button><button name="decision" value="reject" class="sm danger">Reject</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$pending): ?><tr><td colspan="5" class="muted">No pending requests.</td></tr><?php endif; ?></table></div></div>
<?php tab_close(); tab_open('decisions'); ?>
<div class="card"><h2>Decision record</h2><div class="tw"><table><tr><th>Record</th><th>Change</th><th>Decision</th><th>Reviewer</th><th>Note</th></tr>
<?php foreach ($done as $p): ?><tr><td><?= e($p['inventory_no']) ?></td><td><?= e($p['old_level']) ?> → <?= e($p['new_level']) ?></td>
<td><?= badge($p['status'], $p['status'] === 'approved' ? 'ok' : 'bad') ?> <?= fmt_dt($p['reviewed_at']) ?></td><td><?= e($p['reviewer']) ?></td><td><?= e($p['review_note']) ?></td></tr>
<?php endforeach; if (!$done): ?><tr><td colspan="5" class="muted">No decisions yet.</td></tr><?php endif; ?></table></div></div>
<?php tab_close(); tabs_end(); page_bottom();