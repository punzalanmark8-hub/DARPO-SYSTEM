<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('retention.request');
$admin = can('retention.approve');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'schedule' && $admin) {
        $cat = (int)post('category_id');
        if (!$cat || post('class_name') === '' || !in_array(post('disposition'), ['permanent', 'dispose', 'transfer_nap'], true)) flash('err', 'Category, class name and disposition are required.');
        else {
            $id = insert('retention_schedules', ['category_id' => $cat, 'class_name' => post('class_name'), 'active_years' => max(0, (int)post('active_years')), 'inactive_years' => max(0, (int)post('inactive_years')),
                'disposition' => post('disposition'), 'authority_ref' => post('authority_ref') ?: null]);
            audit('retention_schedule_added', 'schedule', $id, post('class_name'));
            flash('ok', 'Schedule item added. Use only items from the approved DAR schedule and NAP authority.');
        }
    } elseif ($act === 'assign' && can('retention.request')) {
        $rid = (int)post('record_id'); $sid = (int)post('schedule_id') ?: null;
        q('UPDATE records SET retention_schedule_id=? WHERE id=?', [$sid, $rid]);
        add_history($rid, 'retention_assigned', null, null, 'Schedule #' . ($sid ?? 'none'));
        audit('retention_assigned', 'record', $rid, (string)$sid);
        flash('ok', 'Retention class assigned.');
    } elseif ($act === 'request') {
        $r = one('SELECT * FROM records r WHERE id=?', [(int)post('record_id')]);
        if (!$r || !can_view_record($r) || post('justification') === '' || !in_array(post('disp_action'), ['transfer_storage', 'transfer_nap', 'dispose'], true)) flash('err', 'Record, action and justification are required.');
        elseif ($r['status'] !== 'archived') flash('err', 'Only archived records can have a disposition request.');
        elseif ($r['disposition_status'] !== 'none') flash('err', 'A disposition request already exists for this record.');
        else {
            $id = insert('disposition_requests', ['record_id' => $r['id'], 'action' => post('disp_action'), 'justification' => post('justification'), 'requested_by' => $_SESSION['uid']]);
            q("UPDATE records SET disposition_status='pending' WHERE id=?", [$r['id']]);
            add_history((int)$r['id'], 'disposition_requested', null, null, post('disp_action'));
            audit('disposition_requested', 'record', $r['id'], post('disp_action'));
            notify_role('admin', "Disposition approval needed: {$r['inventory_no']}", 'retention.php');
            flash('ok', 'Disposition request submitted for approval.');
        }
    } elseif ($act === 'decide' && $admin) {
        $d = one("SELECT * FROM disposition_requests WHERE id=? AND status='pending'", [(int)post('id')]);
        $ok = post('decision') === 'approve';
        if (!$d) flash('err', 'Request not found.');
        elseif ((int)$d['requested_by'] === (int)$_SESSION['uid']) flash('err', 'You cannot approve your own request.');
        else {
            q('UPDATE disposition_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?', [$ok ? 'approved' : 'rejected', $_SESSION['uid'], $d['id']]);
            q('UPDATE records SET disposition_status=? WHERE id=?', [$ok ? 'approved' : 'none', $d['record_id']]);
            add_history((int)$d['record_id'], $ok ? 'disposition_approved' : 'disposition_rejected', null, null, $d['action']);
            audit($ok ? 'disposition_approved' : 'disposition_rejected', 'record', $d['record_id'], $d['action']);
            notify((int)$d['requested_by'], 'Your disposition request for ' . val('SELECT inventory_no FROM records WHERE id=?', [$d['record_id']]) . ' was ' . ($ok ? 'approved' : 'rejected'), 'retention.php');
            flash('ok', 'Decision recorded. Approval does not delete anything; carry out the action under the official procedure.');
        }
    } elseif ($act === 'executed' && $admin) {
        $d = one("SELECT * FROM disposition_requests WHERE id=? AND status='approved'", [(int)post('id')]);
        if ($d) {
            q("UPDATE disposition_requests SET status='executed' WHERE id=?", [$d['id']]);
            q("UPDATE records SET disposition_status='executed' WHERE id=?", [$d['record_id']]);
            add_history((int)$d['record_id'], 'disposition_executed', null, null, $d['action']);
            audit('disposition_executed', 'record', $d['record_id'], $d['action']);
            flash('ok', 'Marked as carried out.');
        }
    }
    redirect('retention.php');
}
$days = (int)setting('retention_review_days', '90');
[$vw, $vp] = visible_where('r');
// Lifecycle is computed from retention_start and the schedule's active/inactive years
$rows = all("SELECT r.*, rs.class_name, rs.active_years, rs.inactive_years, rs.disposition, rs.authority_ref,
        DATE_ADD(r.retention_start, INTERVAL rs.active_years YEAR) inactive_on,
        DATE_ADD(r.retention_start, INTERVAL (rs.active_years+rs.inactive_years) YEAR) final_on
        FROM records r LEFT JOIN retention_schedules rs ON rs.id=r.retention_schedule_id WHERE $vw AND r.status IN ('digitized','archived') ORDER BY r.id DESC LIMIT 300", $vp);
$sched = all('SELECT rs.*, c.name category FROM retention_schedules rs JOIN categories c ON c.id=rs.category_id WHERE rs.is_active=1 ORDER BY c.name');
$reqs = all("SELECT q.*, r.inventory_no, u.full_name requester FROM disposition_requests q JOIN records r ON r.id=q.record_id JOIN users u ON u.id=q.requested_by WHERE q.status IN ('pending','approved') ORDER BY q.id");
$cats = all('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name');
page_top('Retention and archiving');
$stage = function (array $r): string {
    if (!$r['retention_start'] || !$r['final_on']) return 'No schedule or start date';
    $t = date('Y-m-d');
    return $t < $r['inactive_on'] ? 'Active' : ($t < $r['final_on'] ? 'Inactive' : 'Archival / due for disposition review');
};
?>
<div class="note">Retention periods and disposal actions must follow the approved DAR records schedule and applicable National Archives of the Philippines (NAP) requirements. The schedule items below are SAMPLES until replaced. Nothing is ever deleted automatically.</div>
<?php tabs_begin('Retention', ['stages' => 'Lifecycle stages', 'disposition' => 'Disposition requests', 'schedule' => 'Retention schedule'], true); tab_open('stages'); ?>
<div class="card tw"><h2>Records by lifecycle stage</h2><button class="alt noprint" onclick="window.print()">Print retention review report</button>
<table><tr><th>Record</th><th>Status</th><th>Retention class</th><th>Start</th><th>Stage</th><th>Review due</th><th>Archival location</th><th></th></tr>
<?php foreach ($rows as $r): $due = $r['final_on'] && $r['final_on'] <= date('Y-m-d', strtotime("+$days days")); ?>
<tr><td><a href="record.php?id=<?= $r['id'] ?>"><?= e($r['inventory_no']) ?></a> <?= e($r['title']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e($r['class_name'] ?? '—') ?></td>
<td><?= fmt_d($r['retention_start']) ?></td><td><?= e($stage($r)) ?></td><td><?= $r['final_on'] ? fmt_d($r['final_on']) . ($due ? ' ' . badge('due', 'bad') : '') : '—' ?></td><td><?= e($r['archive_location'] ?? '—') ?></td>
<td class="noprint"><?php if ($r['status'] === 'archived' && $r['disposition_status'] === 'none'): ?>
<form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="request"><input type="hidden" name="record_id" value="<?= $r['id'] ?>">
<select name="disp_action"><option value="transfer_storage">Transfer to storage</option><option value="transfer_nap">Transfer to NAP</option><option value="dispose">Dispose</option></select>
<input name="justification" placeholder="Justification / authority" required><button class="sm alt">Request</button></form><?php else: echo e($r['disposition_status'] === 'none' ? '' : 'Disposition: ' . $r['disposition_status']); endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No digitized or archived records yet.</td></tr><?php endif; ?></table></div>
<?php tab_close(); tab_open('disposition'); ?>
<div class="card tw"><h2>Disposition requests</h2><table><tr><th>Record</th><th>Action</th><th>Justification</th><th>By</th><th>Status</th><th></th></tr>
<?php foreach ($reqs as $q): ?><tr><td><?= e($q['inventory_no']) ?></td><td><?= e($q['action']) ?></td><td><?= e($q['justification']) ?></td><td><?= e($q['requester']) ?></td><td><?= badge($q['status']) ?></td>
<td><?php if ($admin): ?><form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $q['id'] ?>">
<?php if ($q['status'] === 'pending'): ?><input type="hidden" name="action" value="decide"><button name="decision" value="approve" class="sm">Approve</button><button name="decision" value="reject" class="sm danger">Reject</button>
<?php else: ?><button name="action" value="executed" class="sm alt">Mark carried out</button><?php endif; ?></form><?php endif; ?></td></tr>
<?php endforeach; if (!$reqs): ?><tr><td colspan="6" class="muted">No open requests.</td></tr><?php endif; ?></table></div>
<?php tab_close(); tab_open('schedule'); ?>
<div class="card tw"><h2>Retention schedule</h2><table><tr><th>Category</th><th>Class</th><th>Active (yrs)</th><th>Inactive (yrs)</th><th>Final action</th><th>Authority</th></tr>
<?php foreach ($sched as $s): ?><tr><td><?= e($s['category']) ?></td><td><?= e($s['class_name']) ?></td><td><?= $s['active_years'] ?></td><td><?= $s['inactive_years'] ?></td><td><?= e($s['disposition']) ?></td><td><?= e($s['authority_ref']) ?></td></tr><?php endforeach; ?></table>
<?php if ($admin): ?><form method="post" class="row noprint" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="schedule">
<label>Category <select name="category_id"><?= select_options($cats, 'id', 'name') ?></select></label><label>Class name <input name="class_name" required></label>
<label>Active yrs <input type="number" name="active_years" value="0" style="width:80px"></label><label>Inactive yrs <input type="number" name="inactive_years" value="0" style="width:80px"></label>
<label>Final action <select name="disposition"><option value="permanent">Permanent</option><option value="transfer_nap">Transfer to NAP</option><option value="dispose">Dispose</option></select></label>
<label>DAR schedule / NAP authority no. <input name="authority_ref"></label><button>Add</button></form><?php endif; ?></div>
<?php tab_close(); tabs_end(); page_bottom();