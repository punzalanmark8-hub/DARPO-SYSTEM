<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('custody.view');
refresh_overdue();
$manage = can('custody.manage');

// Printable routing slip / acknowledgment receipt
if (get('slip')) {
    $s = one('SELECT c.*, r.inventory_no, r.title, b.full_name borrower, rb.full_name releaser FROM custody c JOIN records r ON r.id=c.record_id
              JOIN users b ON b.id=c.borrower_id JOIN users rb ON rb.id=c.released_by WHERE c.id=?', [(int)get('slip')]);
    if (!$s || (!$manage && (int)$s['borrower_id'] !== (int)$_SESSION['uid'])) { http_response_code(404); exit('Slip not found.'); }
    audit('slip_printed', 'custody', $s['id'], $s['slip_no']);
    page_top('Routing slip ' . $s['slip_no']);
    ?>
    <div class="card"><table>
      <tr><th>Folder</th><td><?= e($s['inventory_no']) ?> - <?= e($s['title']) ?></td></tr>
      <tr><th>Released to</th><td><?= e($s['borrower']) ?></td></tr><tr><th>Released by</th><td><?= e($s['releaser']) ?> on <?= fmt_dt($s['released_at']) ?></td></tr>
      <tr><th>Purpose</th><td><?= e($s['purpose']) ?></td></tr><tr><th>Return by</th><td><?= fmt_d($s['due_date']) ?></td></tr></table>
      <p style="margin-top:40px">Received by: ______________________ Date: ____________ &nbsp;&nbsp; Returned to records: ______________________ Date: ____________</p>
      <button class="noprint" onclick="window.print()">Print</button></div>
    <?php page_bottom(); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $manage) {
    if (post('action') === 'release') {
        $r = one('SELECT * FROM records WHERE id=?', [(int)post('record_id')]);
        $b = one('SELECT * FROM users WHERE id=? AND is_active=1', [(int)post('borrower_id')]);
        $due = post('due_date'); $purpose = post('purpose');
        if (!$r || !$b || $purpose === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || $due < date('Y-m-d')) flash('err', 'Record, borrower, purpose and a future due date are required.');
        elseif ($r['condition_flag'] === 'missing') flash('err', 'This folder is marked missing.');
        elseif (one("SELECT id FROM custody WHERE record_id=? AND status<>'returned'", [$r['id']])) flash('err', 'This folder is already out on loan.');
        else {
            $slip = sprintf('RS-%d-%04d', date('Y'), next_counter('slip', (int)date('Y')));
            $id = insert('custody', ['slip_no' => $slip, 'record_id' => $r['id'], 'borrower_id' => $b['id'], 'purpose' => $purpose, 'released_by' => $_SESSION['uid'], 'released_at' => date('Y-m-d H:i:s'), 'due_date' => $due]);
            add_history((int)$r['id'], 'released', null, null, "To {$b['full_name']}, due $due. $purpose");
            audit('custody_release', 'record', $r['id'], $slip);
            notify((int)$b['id'], "Folder {$r['inventory_no']} was released to you, due $due", 'custody.php');
            redirect('custody.php?slip=' . $id);
        }
    } elseif (post('action') === 'return') {
        $c = one("SELECT * FROM custody WHERE id=? AND status<>'returned'", [(int)post('id')]);
        if ($c) {
            q("UPDATE custody SET status='returned', returned_at=NOW(), received_by=? WHERE id=?", [$_SESSION['uid'], $c['id']]);
            add_history((int)$c['record_id'], 'returned', null, null, 'Slip ' . $c['slip_no']);
            audit('custody_return', 'record', $c['record_id'], $c['slip_no']);
            flash('ok', 'Return recorded.');
        }
    }
    redirect('custody.php');
}
$mine = $manage ? '' : ' AND c.borrower_id=' . (int)$_SESSION['uid'];
$rows = all("SELECT c.*, r.inventory_no, r.title, b.full_name borrower FROM custody c JOIN records r ON r.id=c.record_id JOIN users b ON b.id=c.borrower_id
             WHERE 1=1$mine ORDER BY FIELD(c.status,'overdue','out','returned'), c.id DESC LIMIT 200");
[$vw, $vp] = visible_where('r');
$recs = all("SELECT r.id, CONCAT(r.inventory_no,' - ',r.title) label FROM records r WHERE $vw AND r.condition_flag<>'missing'
             AND NOT EXISTS (SELECT 1 FROM custody c WHERE c.record_id=r.id AND c.status<>'returned') ORDER BY r.id DESC LIMIT 500", $vp);
$users = all('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name');
page_top('Routing and custody');
?>
<?php if ($manage): ?><form method="post" class="card row"><?= csrf_field() ?><input type="hidden" name="action" value="release">
  <label>Folder <select name="record_id" required><?= select_options($recs, 'id', 'label') ?></select></label>
  <label>Release to <select name="borrower_id" required><?= select_options($users, 'id', 'full_name') ?></select></label>
  <label>Purpose <input name="purpose" required></label>
  <label>Return by <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+' . (int)setting('loan_days', '7') . ' days')) ?>" min="<?= date('Y-m-d') ?>" required></label>
  <button>Release and print slip</button></form><?php endif; ?>
<div class="card tw"><table><tr><th>Slip</th><th>Folder</th><th>Borrower</th><th>Purpose</th><th>Released</th><th>Due</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $c): ?><tr><td><a href="custody.php?slip=<?= $c['id'] ?>"><?= e($c['slip_no']) ?></a></td><td><?= e($c['inventory_no']) ?> <?= e($c['title']) ?></td><td><?= e($c['borrower']) ?></td>
<td><?= e($c['purpose']) ?></td><td><?= fmt_dt($c['released_at']) ?></td><td><?= fmt_d($c['due_date']) ?></td>
<td><?= badge($c['status'], $c['status'] === 'overdue' ? 'bad' : ($c['status'] === 'returned' ? 'ok' : '')) ?></td>
<td><?php if ($manage && $c['status'] !== 'returned'): ?><form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="sm alt">Receive return</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No custody records.</td></tr><?php endif; ?></table></div>
<?php page_bottom();
