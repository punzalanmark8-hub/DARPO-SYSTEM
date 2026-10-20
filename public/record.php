<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('search');
$r = one('SELECT r.*, d.name division, s.name section, c.name category, u.full_name custodian, rs.class_name, rs.active_years, rs.inactive_years, rs.disposition
          FROM records r JOIN divisions d ON d.id=r.division_id LEFT JOIN sections s ON s.id=r.section_id JOIN categories c ON c.id=r.category_id
          LEFT JOIN users u ON u.id=r.custodian_id LEFT JOIN retention_schedules rs ON rs.id=r.retention_schedule_id WHERE r.id=?', [(int)get('id')]);
if (!$r || !can_view_record($r)) {
    if ($r) audit('access_denied', 'record', $r['id'], 'restricted record');
    http_response_code($r ? 403 : 404);
    page_top('Record not available');
    echo '<div class="card">This record does not exist or you do not have access to it.</div>';
    page_bottom(); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'location' && can('inventory.edit')) {
    [$sc, $serr] = storage_from_post();
    if ($serr) flash('err', $serr);
    else {
        q('UPDATE records SET storage_type=?, storage_category_id=?, storage_no=? WHERE id=?', [$sc['storage_type'], $sc['storage_category_id'], $sc['storage_no'], $r['id']]);
        $label = location_text(array_merge($r, $sc));
        add_history((int)$r['id'], 'location_changed', null, null, 'Location: ' . $label);
        audit('location_changed', 'record', $r['id'], $label);
        flash('ok', 'Physical location updated.');
    }
    redirect('record.php?id=' . (int)$r['id']);
}
audit('record_viewed', 'record', $r['id'], $r['inventory_no']);
$files = all('SELECT f.*, u.full_name uploader FROM digital_files f JOIN users u ON u.id=f.uploaded_by WHERE f.record_id=? ORDER BY f.version DESC', [$r['id']]);
$hist = all('SELECT h.*, u.full_name FROM record_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.record_id=? ORDER BY h.id DESC', [$r['id']]);
$loan = one("SELECT c.*, u.full_name borrower FROM custody c JOIN users u ON u.id=c.borrower_id WHERE c.record_id=? AND c.status<>'returned'", [$r['id']]);
$pendingReq = one("SELECT id FROM class_requests WHERE record_id=? AND status='pending'", [$r['id']]);
page_top($r['inventory_no']);
?>
<div class="card">
  <h2><?= e($r['title']) ?></h2>
  <div class="flow"><?php foreach (STATUSES as $k => $v): ?><span class="<?= $k === $r['status'] ? 'on' : '' ?>"><?= e($v) ?></span><?php endforeach; ?></div>
  <div class="tw"><table>
    <tr><th>Reference no.</th><td><?= e($r['ref_no'] ?? '—') ?></td><th>Division / section</th><td><?= e($r['division']) ?> / <?= e($r['section'] ?? '—') ?></td></tr>
    <tr><th>Type / year / volume</th><td><?= e($r['category']) ?> / <?= $r['doc_year'] ?> / <?= e($r['volume'] ?? '—') ?></td><th>Physical location</th><td><?= e(location_text($r)) ?></td></tr>
    <tr><th>Custodian</th><td><?= e($r['custodian'] ?? '—') ?></td><th>Condition</th><td><?= $r['condition_flag'] === 'ok' ? 'Good' : flag_badge($r['condition_flag']) ?></td></tr>
    <tr><th>Access</th><td><?= access_badge($r['access_level']) ?> <span class="muted"><?= e($r['restriction_basis'] ?? '') ?></span></td>
        <th>Retention</th><td><?= $r['class_name'] ? e($r['class_name']) . " ({$r['active_years']}y active + {$r['inactive_years']}y inactive, " . e($r['disposition']) . ')' : '—' ?></td></tr>
    <tr><th>Current custody</th><td colspan="3"><?= $loan ? 'With ' . e($loan['borrower']) . ', due ' . fmt_d($loan['due_date']) . ' (' . e($loan['status']) . ')' : 'In storage' ?></td></tr>
  </table></div>
  <p><a class="btn alt" href="qr.php?ids=<?= $r['id'] ?>">QR label</a> <a class="btn alt" href="tracking.php?q=<?= urlencode($r['inventory_no']) ?>">Tracking</a></p>
</div>

<?php if (can('inventory.edit')): ?>
<div class="card noprint"><h2>Physical location</h2>
<form method="post" class="rgrid"><?= csrf_field() ?><input type="hidden" name="action" value="location">
  <?= storage_picker($r) ?>
  <div class="span2"><button>Save location</button></div></form></div>
<?php endif; ?>

<div class="card"><h2>Digital copies</h2>
<div class="tw"><table><tr><th>Version</th><th>File</th><th>Pages</th><th>Flag</th><th>Uploaded</th><th></th></tr>
<?php foreach ($files as $f): ?><tr><td>v<?= $f['version'] ?><?= $f['is_current'] ? ' ' . badge('current', 'ok') : '' ?></td><td><?= e($f['original_name']) ?></td><td><?= $f['page_count'] ?></td>
<td><?= $f['scan_flag'] === 'none' ? '' : badge($f['scan_flag'], 'bad') ?></td><td><?= fmt_dt($f['uploaded_at']) ?> by <?= e($f['uploader']) ?></td>
<td><a href="download.php?id=<?= $f['id'] ?>&mode=view">Preview</a> | <a href="download.php?id=<?= $f['id'] ?>&mode=download">Download</a></td></tr>
<?php endforeach; if (!$files): ?><tr><td colspan="6" class="muted">No scanned copy yet.</td></tr><?php endif; ?></table></div></div>

<?php if (can('class.request')): ?>
<div class="card"><h2>Change access classification</h2>
<?php if ($pendingReq): ?><p class="muted">A request is already waiting for review.</p>
<?php else: ?>
<form method="post" action="classification.php" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="request"><input type="hidden" name="record_id" value="<?= $r['id'] ?>">
  <label>Change to <input value="<?= $r['access_level'] === 'open' ? 'restricted' : 'open' ?>" disabled></label>
  <label style="flex:1">Basis or reason (required) <input name="basis" required style="width:100%"></label><button>Submit for review</button></form>
<?php endif; ?></div><?php endif; ?>

<div class="card"><h2>History</h2>
<div class="tw"><table><tr><th>When</th><th>Action</th><th>From → To</th><th>Remarks</th><th>By</th></tr>
<?php foreach ($hist as $h): ?><tr><td><?= fmt_dt($h['created_at']) ?></td><td><?= e($h['action']) ?></td>
<td><?= $h['to_status'] ? e(status_label((string)$h['from_status'])) . ' → ' . e(status_label($h['to_status'])) : '' ?></td><td><?= e($h['remarks']) ?></td><td><?= e($h['full_name'] ?? 'System') ?></td></tr>
<?php endforeach; ?></table></div></div>
<?php page_bottom();