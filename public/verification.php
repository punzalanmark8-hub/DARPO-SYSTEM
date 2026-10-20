<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('verify');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = one('SELECT * FROM records WHERE id=?', [(int)post('record_id')]);
    if (post('action') === 'verify' && $r && $r['status'] === 'for_verification') {
        $f = one('SELECT * FROM digital_files WHERE record_id=? AND is_current=1', [$r['id']]);
        $phys = (int)post('physical_pages'); $dig = (int)post('digital_pages');
        $readable = isset($_POST['readable']) ? 1 : 0; $orient = isset($_POST['orientation']) ? 1 : 0;
        if (!$f || $phys < 1 || $dig < 1) flash('err', 'Enter the physical and digital page counts.');
        elseif ((int)$f['uploaded_by'] === (int)$_SESSION['uid']) flash('err', 'You scanned this file, so someone else must verify it.');
        else {
            $pass = $phys === $dig && $readable && $orient;
            $to = $pass ? 'digitized' : 'scanning';
            db()->beginTransaction();
            insert('verifications', ['record_id' => $r['id'], 'file_id' => $f['id'], 'verifier_id' => $_SESSION['uid'], 'physical_pages' => $phys, 'digital_pages' => $dig,
                'readable_ok' => $readable, 'orientation_ok' => $orient, 'result' => $pass ? 'pass' : 'fail', 'remarks' => post('remarks') ?: null]);
            q('UPDATE digital_files SET page_count=? WHERE id=?', [$dig, $f['id']]);
            if (!$pass) q('UPDATE digital_files SET scan_flag=? WHERE id=?', [$phys !== $dig ? 'incomplete' : 'unreadable', $f['id']]);
            q('UPDATE records SET status=? WHERE id=?', [$to, $r['id']]);
            add_history((int)$r['id'], $pass ? 'verified' : 'verification_failed', 'for_verification', $to, post('remarks') ?: null);
            db()->commit();
            audit($pass ? 'verified_pass' : 'verified_fail', 'record', $r['id'], $r['inventory_no']);
            $officers = role_ids('records_officer');
            if ($pass) {
                // Scanner and records officers learn the result; the other verifiers learn the folder is ready for final archiving
                notify_users(array_merge([(int)$f['uploaded_by']], $officers), "{$r['inventory_no']} passed verification and is now Digitized", 'record.php?id=' . $r['id']);
                notify_roles(['verifier', 'admin'], "{$r['inventory_no']} is digitized and ready for final archiving", 'verification.php');
            } else {
                notify_users(array_merge([(int)$f['uploaded_by']], $officers), "{$r['inventory_no']} failed verification and needs a rescan", 'inventory.php');
            }
            flash($pass ? 'ok' : 'warn', $pass ? 'Passed. The record is now Digitized.' : 'Failed. Returned to Scanning for correction.');
        }
    } elseif (post('action') === 'archive' && $r && $r['status'] === 'digitized') {
        $loc = post('archive_location');
        if ($loc === '') flash('err', 'Enter the archival location.');
        else {
            q("UPDATE records SET status='archived', archived_at=NOW(), archive_location=?, preservation_notes=?, retention_start=COALESCE(retention_start,CURDATE()) WHERE id=?",
              [$loc, post('preservation') ?: null, $r['id']]);
            add_history((int)$r['id'], 'archived', 'digitized', 'archived', "Archive location: $loc");
            audit('archived', 'record', $r['id'], $loc);
            notify_role('records_officer', "{$r['inventory_no']} was archived ($loc)", 'record.php?id=' . $r['id']);
            flash('ok', 'Approved for final archiving.');
        }
    }
    redirect('verification.php');
}
[$vw, $vp] = visible_where('r');
$queue = all("SELECT r.*, f.id fid, f.original_name, f.page_count, f.version, u.full_name uploader FROM records r
              JOIN digital_files f ON f.record_id=r.id AND f.is_current=1 JOIN users u ON u.id=f.uploaded_by
              WHERE r.status='for_verification' AND $vw ORDER BY r.id", $vp);
$toArchive = all("SELECT r.* FROM records r WHERE r.status='digitized' AND $vw ORDER BY r.id", $vp);
$log = all('SELECT v.*, r.inventory_no, u.full_name FROM verifications v JOIN records r ON r.id=v.record_id JOIN users u ON u.id=v.verifier_id ORDER BY v.id DESC LIMIT 30');
page_top('Verification and quality assurance');
?>
<?php tabs_begin('Verification', ['queue' => 'Waiting for verification', 'archive' => 'Final archiving', 'log' => 'Verification record']); tab_open('queue'); ?>
<div class="card"><h2>Waiting for verification</h2>
<?php foreach ($queue as $q): ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="record_id" value="<?= $q['id'] ?>">
  <strong><?= e($q['inventory_no']) ?></strong> <?= e($q['title']) ?> <span class="muted">scanned by <?= e($q['uploader']) ?>, <?= e($q['original_name']) ?> v<?= $q['version'] ?></span>
  <p><a target="_blank" href="download.php?id=<?= $q['fid'] ?>&mode=view">Open the scan</a> and compare it with the physical folder.</p>
  <div class="row"><label>Pages in physical folder <input type="number" min="1" name="physical_pages" required style="width:110px"></label>
  <label>Pages in scan <input type="number" min="1" name="digital_pages" value="<?= $q['page_count'] ?>" required style="width:110px"></label>
  <label><span><input type="checkbox" name="readable" value="1"> Every page is readable</span></label>
  <label><span><input type="checkbox" name="orientation" value="1"> Orientation is correct</span></label>
  <label>Remarks <input name="remarks"></label><button>Record result</button></div></form>
<?php endforeach; if (!$queue): ?><p class="muted">Nothing to verify.</p><?php endif; ?></div>
<?php tab_close(); tab_open('archive'); ?>
<div class="card"><h2>Approve for final archiving</h2>
<?php foreach ($toArchive as $a): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="record_id" value="<?= $a['id'] ?>">
  <span><strong><?= e($a['inventory_no']) ?></strong> <?= e($a['title']) ?></span>
  <label>Archival location <input name="archive_location" required></label><label>Preservation requirements <input name="preservation"></label><button>Approve</button></form>
<?php endforeach; if (!$toArchive): ?><p class="muted">No digitized records waiting.</p><?php endif; ?></div>
<?php tab_close(); tab_open('log'); ?>
<div class="card"><h2>Verification record</h2><div class="tw"><table><tr><th>Record</th><th>Result</th><th>Pages (physical / scan)</th><th>Verifier</th><th>When</th><th>Remarks</th></tr>
<?php foreach ($log as $l): ?><tr><td><?= e($l['inventory_no']) ?></td><td><?= badge($l['result'], $l['result'] === 'pass' ? 'ok' : 'bad') ?></td><td><?= $l['physical_pages'] ?> / <?= $l['digital_pages'] ?></td><td><?= e($l['full_name']) ?></td><td><?= fmt_dt($l['verified_at']) ?></td><td><?= e($l['remarks']) ?></td></tr>
<?php endforeach; ?></table></div></div>
<?php tab_close(); tabs_end(); page_bottom();