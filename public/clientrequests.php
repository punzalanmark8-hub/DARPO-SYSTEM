<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('creq');
require_once __DIR__ . '/../app/clientreq.php';
if (($_SESSION['creq_ok'] ?? 0) !== 1) { ensure_creq_schema(); $_SESSION['creq_ok'] = 1; }
$isAdmin = can('creq.approve');
$uid = (int)$_SESSION['uid'];
$mine = fn(array $r): bool => $isAdmin || (int)$r['requested_by'] === $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'create') {
        $name = post('client_name'); $email = post('client_email'); $org = post('client_org'); $purpose = post('purpose');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['files'] ?? [])))));
        $err = null; $fl = [];
        if ($name === '' || $purpose === '') $err = 'Enter the client name and the purpose of the request.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid client e-mail address.';
        elseif (!$ids) $err = 'Select at least one document.';
        else {
            $fl = all('SELECT f.id, f.record_id, f.size_bytes, r.access_level, r.division_id, r.inventory_no FROM digital_files f JOIN records r ON r.id=f.record_id WHERE f.is_current=1 AND f.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
            if (count($fl) !== count($ids)) $err = 'One of the selected documents is no longer available.';
            else {
                $size = 0;
                foreach ($fl as $f) { if (!can_view_record($f)) { $err = 'You do not have access to ' . $f['inventory_no'] . '.'; break; } $size += (int)$f['size_bytes']; }
                if (!$err && $size > CREQ_MAX_BYTES) $err = 'The selected documents total ' . creq_fmt_size($size) . ' but e-mail is limited to ' . creq_fmt_size(CREQ_MAX_BYTES) . '. Split this into more than one request.';
            }
        }
        if ($err) {
            flash('err', $err);
            $_SESSION['creq_old'] = ['client_name' => $name, 'client_email' => $email, 'client_org' => $org, 'purpose' => $purpose, 'files' => $ids];
            redirect('clientrequests.php?new=1');
        }
        $id = insert('client_requests', ['request_no' => 'TMP' . bin2hex(random_bytes(8)), 'client_name' => mb_substr($name, 0, 150), 'client_email' => mb_substr($email, 0, 150),
            'client_org' => $org !== '' ? mb_substr($org, 0, 150) : null, 'purpose' => mb_substr($purpose, 0, 255), 'requested_by' => $uid, 'division_id' => ($_SESSION['division_id'] ?? null) ?: null]);
        $no = 'CR-' . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        q('UPDATE client_requests SET request_no=? WHERE id=?', [$no, $id]);
        foreach ($fl as $f) q('INSERT INTO client_request_files(request_id,file_id,record_id) VALUES(?,?,?)', [$id, (int)$f['id'], (int)$f['record_id']]);
        audit('client_request_created', 'client_request', $id, $no . ' for ' . $email . ', ' . count($fl) . ' file(s)');
        notify_role('admin', "New client request $no is waiting for your approval.", 'clientrequests.php?id=' . $id);
        flash('ok', "Request $no was submitted to the administrator for approval.");
        redirect('clientrequests.php?id=' . $id);
    }
    $req = one('SELECT * FROM client_requests WHERE id=?', [(int)post('id')]);
    if ($act === 'approve') {
        if (!$req || !$isAdmin) flash('err', 'Request not found.');
        elseif (!in_array($req['status'], ['pending', 'approved'], true)) flash('err', 'This request was already ' . $req['status'] . '.');
        else {
            q("UPDATE client_requests SET status='approved', decided_by=?, decided_at=COALESCE(decided_at,NOW()), decision_note=COALESCE(?,decision_note) WHERE id=?", [$uid, post('note') !== '' ? mb_substr(post('note'), 0, 255) : null, (int)$req['id']]);
            audit('client_request_approved', 'client_request', $req['id'], $req['request_no']);
            [$ok, $msg] = creq_send($req);
            if ($ok) {
                q("UPDATE client_requests SET status='sent', sent_at=NOW(), send_error=NULL WHERE id=?", [(int)$req['id']]);
                foreach (all('SELECT DISTINCT record_id FROM client_request_files WHERE request_id=?', [(int)$req['id']]) as $rf)
                    add_history((int)$rf['record_id'], 'client_copy_emailed', null, null, $req['request_no'] . ' to ' . $req['client_email']);
                audit('client_request_sent', 'client_request', $req['id'], $req['request_no'] . ' to ' . $req['client_email']);
                notify_users([(int)$req['requested_by']], 'Client request ' . $req['request_no'] . ' was approved and e-mailed to the client.', 'clientrequests.php?id=' . $req['id']);
                flash('ok', 'Approved and e-mailed to ' . $req['client_email'] . '.');
            } else {
                q('UPDATE client_requests SET send_error=? WHERE id=?', [$msg, (int)$req['id']]);
                audit('client_request_send_failed', 'client_request', $req['id'], $msg);
                flash('err', 'Approved, but the e-mail could not be sent: ' . $msg);
            }
        }
        redirect('clientrequests.php?id=' . (int)post('id'));
    } elseif ($act === 'reject') {
        if (!$req || !$isAdmin) flash('err', 'Request not found.');
        elseif ($req['status'] !== 'pending') flash('err', 'Only pending requests can be rejected.');
        elseif (post('note') === '') flash('err', 'Enter the reason for rejecting this request.');
        else {
            q("UPDATE client_requests SET status='rejected', decided_by=?, decided_at=NOW(), decision_note=? WHERE id=?", [$uid, mb_substr(post('note'), 0, 255), (int)$req['id']]);
            audit('client_request_rejected', 'client_request', $req['id'], $req['request_no'] . ': ' . post('note'));
            notify_users([(int)$req['requested_by']], 'Client request ' . $req['request_no'] . ' was rejected: ' . post('note'), 'clientrequests.php?id=' . $req['id']);
            flash('ok', 'Request rejected.');
        }
        redirect('clientrequests.php?id=' . (int)post('id'));
    } elseif ($act === 'test_mail' && $isAdmin) {
        if (!filter_var(post('to'), FILTER_VALIDATE_EMAIL)) flash('err', 'Enter a valid e-mail address for the test.');
        else { [$ok, $msg] = creq_send_test(post('to')); flash($ok ? 'ok' : 'err', $ok ? 'Test e-mail sent to ' . post('to') . '.' : 'Test failed: ' . $msg); }
    }
    redirect('clientrequests.php');
}

$id = (int)get('id');

// ---------- One request ----------
if ($id) {
    $req = one('SELECT q.*, u.full_name requester, d.full_name decider FROM client_requests q JOIN users u ON u.id=q.requested_by LEFT JOIN users d ON d.id=q.decided_by WHERE q.id=?', [$id]);
    if (!$req || !$mine($req)) { http_response_code(404); page_top('Client request'); echo '<div class="card"><h2>Request not found</h2><p><a href="clientrequests.php">Back to client requests</a></p></div>'; page_bottom(); exit; }
    $files = all('SELECT f.*, r.inventory_no, r.title FROM client_request_files c JOIN digital_files f ON f.id=c.file_id JOIN records r ON r.id=f.record_id WHERE c.request_id=? ORDER BY r.inventory_no, f.id', [$id]);
    $total = array_sum(array_map(fn($f) => (int)$f['size_bytes'], $files));
    page_top($req['request_no']);
    ?>
<div class="card"><h2><?= e($req['request_no']) ?> <?= creq_status_badge($req['status']) ?></h2>
<div class="tw"><table>
<tr><th>Client</th><td><?= e($req['client_name']) ?><?= $req['client_org'] ? ' (' . e($req['client_org']) . ')' : '' ?></td><th>E-mail</th><td><?= e($req['client_email']) ?></td></tr>
<tr><th>Purpose</th><td colspan="3"><?= e($req['purpose']) ?></td></tr>
<tr><th>Requested by</th><td><?= e($req['requester']) ?>, <?= fmt_dt($req['created_at']) ?></td><th>Decision</th><td><?= $req['decided_at'] ? e($req['decider'] ?? '') . ', ' . fmt_dt($req['decided_at']) : '—' ?><?= $req['decision_note'] ? '<br><span class="muted">' . e($req['decision_note']) . '</span>' : '' ?></td></tr>
<tr><th>E-mailed</th><td colspan="3"><?= $req['sent_at'] ? fmt_dt($req['sent_at']) . ' to ' . e($req['client_email']) : 'Not yet' ?><?= $req['send_error'] && $req['status'] !== 'sent' ? '<br><span class="badge bad">Last send failed</span> ' . e($req['send_error']) : '' ?></td></tr>
</table></div>
<p><a class="btn alt" href="clientrequests.php">Back to list</a></p></div>

<div class="card tw"><h2>Documents (<?= count($files) ?>, <?= creq_fmt_size($total) ?>)</h2>
<table><tr><th>Inventory no.</th><th>Title</th><th>File</th><th>Pages</th><th>Size</th><th></th></tr>
<?php foreach ($files as $f): ?><tr><td><a href="record.php?id=<?= (int)$f['record_id'] ?>"><?= e($f['inventory_no']) ?></a></td><td><?= e($f['title']) ?></td><td><?= e($f['original_name']) ?></td><td><?= (int)$f['page_count'] ?></td><td><?= creq_fmt_size((int)$f['size_bytes']) ?></td>
<td><a href="download.php?id=<?= (int)$f['id'] ?>&amp;mode=view">Preview</a></td></tr>
<?php endforeach; ?></table></div>

<?php if ($isAdmin && in_array($req['status'], ['pending', 'approved'], true)): ?>
<div class="card noprint"><h2><?= $req['status'] === 'pending' ? 'Decision' : 'Send again' ?></h2>
<?php if ($p = creq_mail_problems()): ?><div class="flash err"><?= e($p[0]) ?></div><?php endif; ?>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<label>Note (optional for approval, required for rejection) <input name="note" maxlength="255" style="min-width:320px"></label>
<button name="action" value="approve"><?= $req['status'] === 'pending' ? 'Approve and e-mail to client' : 'Retry sending' ?></button>
<?php if ($req['status'] === 'pending'): ?><button name="action" value="reject" class="alt" onclick="return confirm('Reject this request?')">Reject</button><?php endif; ?></form></div>
<?php endif; ?>
<?php page_bottom(); exit; }

// ---------- New request ----------
if (get('new') === '1') {
    $old = $_SESSION['creq_old'] ?? []; unset($_SESSION['creq_old']);
    $sel = array_map('intval', $old['files'] ?? []);
    [$vw, $vp] = visible_where('r');
    $docs = all("SELECT f.id fid, f.original_name, f.size_bytes, f.page_count, r.inventory_no, r.title, r.doc_year, r.access_level FROM digital_files f JOIN records r ON r.id=f.record_id WHERE f.is_current=1 AND $vw ORDER BY r.id DESC LIMIT 300", $vp);
    page_top('New client request');
    ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="create"><h2>New client request</h2>
<p class="muted" style="margin-top:0">The request goes to the administrator. Nothing is e-mailed to the client until it is approved.</p>
<div class="rgrid">
<label>Client name <input name="client_name" required maxlength="150" value="<?= e($old['client_name'] ?? '') ?>"></label>
<label>Client e-mail <input type="email" name="client_email" required maxlength="150" value="<?= e($old['client_email'] ?? '') ?>"></label>
<label>Organization / office (optional) <input name="client_org" maxlength="150" value="<?= e($old['client_org'] ?? '') ?>"></label>
<label>Purpose of request <input name="purpose" required maxlength="255" value="<?= e($old['purpose'] ?? '') ?>"></label>
</div>
<h3>Documents to send</h3>
<p class="muted">Only scanned documents you are allowed to view are listed (newest 300; use the box to filter). E-mail is limited to <?= creq_fmt_size(CREQ_MAX_BYTES) ?> in total.</p>
<p><input type="search" id="docfilter" placeholder="Filter by inventory no. or title" style="min-width:300px"> <strong id="docsel">0 selected</strong></p>
<div class="tw"><table id="doctable"><tr><th></th><th>Inventory no.</th><th>Title</th><th>Year</th><th>File</th><th>Size</th><th>Access</th></tr>
<?php foreach ($docs as $d): ?><tr data-t="<?= e(mb_strtolower($d['inventory_no'] . ' ' . $d['title'] . ' ' . $d['original_name'])) ?>">
<td><input type="checkbox" name="files[]" value="<?= (int)$d['fid'] ?>" data-size="<?= (int)$d['size_bytes'] ?>" <?= in_array((int)$d['fid'], $sel, true) ? 'checked' : '' ?>></td>
<td><?= e($d['inventory_no']) ?></td><td><?= e($d['title']) ?></td><td><?= (int)$d['doc_year'] ?></td><td><?= e($d['original_name']) ?> <span class="muted">(<?= (int)$d['page_count'] ?> p)</span></td><td><?= creq_fmt_size((int)$d['size_bytes']) ?></td><td><?= access_badge($d['access_level']) ?></td></tr>
<?php endforeach; if (!$docs): ?><tr><td colspan="7" class="muted">No scanned documents are available to you yet.</td></tr><?php endif; ?></table></div>
<p><button>Submit for approval</button> <a class="btn alt" href="clientrequests.php">Cancel</a></p></form>
<script>
(function () {
  var f = document.getElementById('docfilter'), out = document.getElementById('docsel'), max = <?= CREQ_MAX_BYTES ?>;
  var rows = [].slice.call(document.querySelectorAll('#doctable tr[data-t]')), boxes = [].slice.call(document.querySelectorAll('#doctable input[type=checkbox]'));
  function mb(b) { return (b / 1048576).toFixed(1) + ' MB'; }
  function count() { var n = 0, s = 0; boxes.forEach(function (c) { if (c.checked) { n++; s += +c.dataset.size; } }); out.textContent = n + ' selected (' + mb(s) + ')' + (s > max ? ' - too large for one e-mail' : ''); out.style.color = s > max ? '#b00020' : ''; }
  f.addEventListener('input', function () { var t = f.value.toLowerCase().trim(); rows.forEach(function (r) { r.hidden = t !== '' && r.dataset.t.indexOf(t) < 0; }); });
  boxes.forEach(function (c) { c.addEventListener('change', count); }); count();
})();
</script>
<?php page_bottom(); exit; }

// ---------- List ----------
$w = ['1=1']; $pr = [];
if (!$isAdmin) { $w[] = 'q.requested_by=?'; $pr[] = $uid; }
$st = get('status');
if (in_array($st, ['pending', 'approved', 'sent', 'rejected'], true)) { $w[] = 'q.status=?'; $pr[] = $st; }
$per = 10;
$total = (int)val('SELECT COUNT(*) FROM client_requests q WHERE ' . implode(' AND ', $w), $pr);
$pages = max(1, (int)ceil($total / $per));
$pg = min($pages, max(1, (int)get('page')));
$rows = all('SELECT q.*, u.full_name requester, (SELECT COUNT(*) FROM client_request_files c WHERE c.request_id=q.id) n_files FROM client_requests q JOIN users u ON u.id=q.requested_by WHERE ' . implode(' AND ', $w) . ' ORDER BY q.id DESC LIMIT ' . $per . ' OFFSET ' . (($pg - 1) * $per), $pr);
$qs = $st !== '' ? 'status=' . urlencode($st) . '&' : '';
$link = fn(int $n, string $label, bool $on = false) => '<a class="btn sm ' . ($on ? '' : 'alt') . '" href="clientrequests.php?' . e($qs) . 'page=' . $n . '">' . $label . '</a> ';
$problems = $isAdmin ? creq_mail_problems() : [];
page_top('Client requests');
?>
<?php if ($isAdmin): ?><div class="card noprint"><h2>E-mail (Gmail SMTP)</h2>
<?php if ($problems): foreach ($problems as $p): ?><div class="flash err"><?= e($p) ?></div><?php endforeach; else: ?><p class="muted" style="margin-top:0">Ready. Approved requests are e-mailed from <strong><?= e(cfg()['mail']['username']) ?></strong>.</p>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail"><label>Send a test e-mail to <input type="email" name="to" required value="<?= e(val('SELECT email FROM users WHERE id=?', [$uid]) ?: '') ?>"></label><button class="alt">Send test</button></form><?php endif; ?></div><?php endif; ?>
<div class="card row noprint"><a class="btn" href="clientrequests.php?new=1">New client request</a>
<span><?php foreach ([''=>'All','pending'=>'Pending','approved'=>'Approved, not sent','sent'=>'Sent','rejected'=>'Rejected'] as $k => $v): ?><a class="btn sm <?= $st === (string)$k ? '' : 'alt' ?>" href="clientrequests.php<?= $k !== '' ? '?status=' . $k : '' ?>"><?= e($v) ?></a> <?php endforeach; ?></span></div>
<div class="card tw"><h2>Client requests</h2><table><tr><th>Request no.</th><th>Client</th><th>Purpose</th><th>Docs</th><th>Requested by</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['request_no']) ?></td><td><?= e($r['client_name']) ?><br><span class="muted"><?= e($r['client_email']) ?></span></td><td><?= e($r['purpose']) ?></td><td><?= (int)$r['n_files'] ?></td>
<td><?= e($r['requester']) ?><br><span class="muted"><?= fmt_dt($r['created_at']) ?></span></td><td><?= creq_status_badge($r['status']) ?></td><td><a href="clientrequests.php?id=<?= (int)$r['id'] ?>"><?= $isAdmin && $r['status'] === 'pending' ? 'Review' : 'Open' ?></a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">No client requests yet.</td></tr><?php endif; ?></table></div>
<div class="card row noprint" style="justify-content:space-between;align-items:center"><span class="muted">Showing <?= $total ? ($pg - 1) * $per + 1 : 0 ?>&ndash;<?= min($total, $pg * $per) ?> of <?= $total ?> &middot; Page <?= $pg ?> of <?= $pages ?></span>
<?php if ($pages > 1): ?><span><?php if ($pg > 1) echo $link($pg - 1, '&lsaquo; Prev'); for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++) echo $link($i, (string)$i, $i === $pg); if ($pg < $pages) echo $link($pg + 1, 'Next &rsaquo;'); ?></span><?php endif; ?></div>
<?php page_bottom();