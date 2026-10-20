<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('scan.upload');


// Backend for scan uploads, batches and flags. The Inventory tab posts here (optionally via AJAX for a progress bar).
function finish(string $to) {
    if (post('ajax') === '1') { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
    redirect($to);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    $back = 'inventory.php';
    if ($act === 'batch') {
        $no = sprintf('SB-%d-%04d', date('Y'), next_counter('scanbatch', (int)date('Y')));
        $id = insert('scan_batches', ['batch_no' => $no, 'division_id' => (int)post('division_id') ?: null, 'operator_id' => $_SESSION['uid'], 'notes' => post('notes') ?: null]);
        audit('batch_created', 'batch', $id, $no);
        flash('ok', "Batch $no created.");
    } elseif ($act === 'upload') {
        $r = one('SELECT * FROM records WHERE id=?', [(int)post('record_id')]);
        $file = null;
        $err = null;
        if (!$r || !can_view_record($r)) $err = 'Choose a valid record.';
        elseif (role() === 'division_staff' && (int)$r['division_id'] !== (int)$_SESSION['division_id']) $err = 'You can only scan records of your own division.';
        elseif (!in_array($r['status'], ['inventoried', 'scanning'], true)) $err = 'This record has already moved past scanning, so its scan can no longer be replaced here.';
        elseif ($r['condition_flag'] === 'missing') $err = 'This folder is marked missing.';
        else {
            // One file can have several pages (scan[]); they are joined into one PDF and stored as a single digital file
            $up = collect_scan_upload('scan');
            if ($up['ok']) $file = $up['file']; else $err = $up['msg'];
        }
        $current = $r ? one('SELECT * FROM digital_files WHERE record_id=? AND is_current=1', [$r['id']]) : null;
        if (!$err && $current && post('reason') === '') $err = 'A scan already exists. Enter the reason for replacing it.';
        $res = null;
        if (!$err) {
            $res = save_scan((int)$r['id'], (string)$r['inventory_no'], $file, (int)post('batch_id') ?: null, post('reason') ?: null);
            if (!$res['ok']) $err = $res['msg'];
        }
        discard_scan_upload($file);   // removes the joined temp file if it was not stored
        if ($err) {
            if (post('ajax') === '1') { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $err]); exit; }
            flash('err', $err); finish($back);
        }
        if (role() === 'division_staff') notify_role('records_officer', "{$r['inventory_no']}: a new scan was uploaded by {$_SESSION['name']}", 'inventory.php');
        flash($res['dupe'] ? 'warn' : 'ok', $res['dupe'] ? 'Saved, but an identical file already exists and it was flagged as a duplicate.' : "Uploaded v{$res['version']} ({$res['pages']} page(s)).");
    } elseif ($act === 'flag') {
        $f = one('SELECT f.*, r.division_id FROM digital_files f JOIN records r ON r.id=f.record_id WHERE f.id=?', [(int)post('file_id')]);
        $flag = post('flag');
        if ($f && in_array($flag, ['none', 'unreadable', 'incomplete', 'duplicate'], true)) {
            q('UPDATE digital_files SET scan_flag=? WHERE id=?', [$flag, $f['id']]);
            audit('scan_flagged', 'file', $f['id'], $flag);
            if ($flag !== 'none') notify((int)$f['uploaded_by'], "Scan #{$f['id']} was flagged $flag. Please rescan.", 'inventory.php');
            flash('ok', 'Flag updated.');
        }
    }
    finish($back);
}

// The Scanning page was merged into the Inventory tab. This file now only receives the POSTs sent from there.
redirect('inventory.php');