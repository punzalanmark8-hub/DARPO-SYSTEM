<?php
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/scanlib.php';
require_perm('inventory.view');
$edit = can('inventory.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $edit) {
    $act = post('action');
    if ($act === 'add') {
        $needScan = can('scan.upload');
        $err = null;
        $title = post('title'); $year = (int)post('doc_year');
        $div = one('SELECT * FROM divisions WHERE id=? AND is_active=1', [(int)post('division_id')]);
        $cat = one('SELECT * FROM categories WHERE id=? AND is_active=1', [(int)post('category_id')]);
        // The scan comes first, so check it before anything is saved. It may hold several pages (scan[]); they are joined into one PDF here.
        $file = null;
        [$sc, $serr] = storage_from_post();
        if ($needScan) { $up = collect_scan_upload('scan'); if ($up['ok']) $file = $up['file']; else $err = $up['msg']; }
        if ($err) { /* reported below */ }
        elseif ($title === '' || !$div || !$cat || $year < 1900 || $year > (int)date('Y') + 1) $err = 'Title, division, category and a valid year are required.';
        elseif ($serr) $err = $serr;
        else {
            $seq = next_counter('inv-' . $div['code'], $year);
            $invNo = sprintf('DAR-%s-%d-%04d', $div['code'], $year, $seq);
            $sec = (int)post('section_id') ?: null;
            if ($sec && !one('SELECT id FROM sections WHERE id=? AND division_id=?', [$sec, $div['id']])) $sec = null;
            $sched = val('SELECT id FROM retention_schedules WHERE category_id=? AND is_active=1 ORDER BY id LIMIT 1', [$cat['id']]);
            $id = insert('records', [
                'inventory_no' => $invNo, 'qr_token' => bin2hex(random_bytes(8)), 'title' => $title, 'ref_no' => post('ref_no') ?: null,
                'division_id' => $div['id'], 'section_id' => $sec, 'category_id' => $cat['id'], 'doc_year' => $year, 'volume' => post('volume') ?: null,
                'storage_type' => $sc['storage_type'], 'storage_category_id' => $sc['storage_category_id'], 'storage_no' => $sc['storage_no'],
                'custodian_id' => (int)post('custodian_id') ?: null, 'access_level' => $cat['default_access'],
                'restriction_basis' => $cat['default_access'] === 'restricted' ? 'Default for category: ' . $cat['name'] : null,
                'retention_schedule_id' => $sched ?: null, 'created_by' => $_SESSION['uid'],
            ]);
            add_history($id, 'inventoried', null, 'inventoried', 'Registered in inventory');
            audit('record_created', 'record', $id, $invNo);
            $type = 'ok'; $msg = "Registered $invNo.";
            if ($needScan) {
                $res = save_scan($id, $invNo, $file, (int)post('batch_id') ?: null);
                if ($res['ok']) {
                    $msg .= " Scan uploaded ({$res['pages']} page(s)).";
                    if ($res['dupe']) { $type = 'warn'; $msg .= ' An identical file already exists, so it was flagged as a duplicate.'; }
                } else {
                    $type = 'warn'; $msg .= ' The record was saved, but the scan could not be stored: ' . $res['msg'] . ' Use the Scan column to upload it again.';
                }
            }
            flash($type, $msg);
        }
        discard_scan_upload($file);   // removes the joined temp file if it was not stored
        if ($err) {
            if (post('ajax') === '1') { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $err]); exit; }
            flash('err', $err);
        }
    } elseif ($act === 'flag') {
        $rec = one('SELECT * FROM records WHERE id=?', [(int)post('id')]);
        $flag = post('flag');
        if ($rec && in_array($flag, ['ok', 'missing', 'damaged', 'unlocated'], true)) {
            q('UPDATE records SET condition_flag=? WHERE id=?', [$flag, $rec['id']]);
            add_history((int)$rec['id'], 'condition', null, null, "Marked $flag" . (post('note') ? ': ' . post('note') : ''));
            audit('record_condition', 'record', $rec['id'], $flag);
            if ($flag !== 'ok') notify_users(array_merge(role_ids('admin'), role_ids('records_officer')), "{$rec['inventory_no']} marked $flag", 'record.php?id=' . $rec['id']);
            flash('ok', 'Condition updated.');
        }
    }
    if (post('ajax') === '1') { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
    redirect('inventory.php');
}

$f = ['q' => get('q'), 'division_id' => (int)get('division_id'), 'status' => get('status'), 'flag' => get('flag'), 'scan' => get('scan')];
[$vw, $vp] = visible_where('r');
$where = [$vw]; $par = $vp;
if ($f['q'] !== '') { $where[] = '(r.title LIKE ? OR r.inventory_no LIKE ? OR r.ref_no LIKE ?)'; array_push($par, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }
if ($f['division_id']) { $where[] = 'r.division_id=?'; $par[] = $f['division_id']; }
if (isset(STATUSES[$f['status']])) { $where[] = 'r.status=?'; $par[] = $f['status']; }
if ($f['flag'] === 'issue') $where[] = "r.condition_flag<>'ok'";
if ($f['scan'] === 'with') $where[] = 'f.id IS NOT NULL';
if ($f['scan'] === 'without') $where[] = 'f.id IS NULL';
$rows = all('SELECT r.*, d.name division, c.name category, u.full_name custodian,
                    f.id file_id, f.version file_ver, f.page_count file_pages, f.scan_flag file_flag, f.original_name file_name FROM records r
             JOIN divisions d ON d.id=r.division_id JOIN categories c ON c.id=r.category_id LEFT JOIN users u ON u.id=r.custodian_id
             LEFT JOIN digital_files f ON f.record_id=r.id AND f.is_current=1
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC LIMIT 300', $par);
// Pagination: 5 per page by default, switchable
$perOpts = [5, 10, 20, 50]; $per = in_array((int)get('per'), $perOpts, true) ? (int)get('per') : 5;
$total = count($rows); $pages = max(1, (int)ceil($total / $per)); $pg = max(1, min($pages, (int)get('page') ?: 1));
$rows = array_slice($rows, ($pg - 1) * $per, $per);
$pageUrl = fn(int $p) => 'inventory.php?' . http_build_query($f + ['per' => $per, 'page' => $p]) . '#tab-records';
$divs = all('SELECT id,name FROM divisions WHERE is_active=1 ORDER BY name');
$cats = all('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name');
$secs = all('SELECT s.id, CONCAT(d.code," - ",s.name) label FROM sections s JOIN divisions d ON d.id=s.division_id WHERE s.is_active=1 ORDER BY d.code,s.name');
$users = all('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name');
$canScan = can('scan.upload');
$batches = $canScan ? all('SELECT b.*, u.full_name operator, d.name division, COUNT(f.id) files, COUNT(DISTINCT f.record_id) recs, SUM(f.scan_flag<>"none") flagged
                FROM scan_batches b JOIN users u ON u.id=b.operator_id LEFT JOIN divisions d ON d.id=b.division_id LEFT JOIN digital_files f ON f.batch_id=b.id
                GROUP BY b.id ORDER BY b.id DESC LIMIT 30') : [];
$flagged = $canScan ? all("SELECT f.*, r.inventory_no FROM digital_files f JOIN records r ON r.id=f.record_id WHERE f.is_current=1 AND f.scan_flag<>'none' ORDER BY f.id DESC LIMIT 50") : [];
$tot = (int)val('SELECT COUNT(*) FROM records r WHERE ' . $vw, $vp);
$scn = (int)val('SELECT COUNT(DISTINCT r.id) FROM records r JOIN digital_files f ON f.record_id=r.id AND f.is_current=1 WHERE ' . $vw, $vp);
$pct = $tot ? (int)round($scn * 100 / $tot) : 0;
$allowedExt = array_values(array_filter(array_map('trim', explode(',', (string)setting('allowed_ext', 'pdf')))));
$maxMb = (int)setting('max_upload_mb', '50');
$maxPages = scan_page_limit();   // pages of one file (PHP max_file_uploads)
// Can the current user attach or replace a scan for this record from here?
function can_scan_here(array $r): bool {
    if (!can('scan.upload') || $r['condition_flag'] === 'missing') return false;
    if (!in_array($r['status'], ['inventoried', 'scanning'], true)) return false;
    return role() !== 'division_staff' || (int)$r['division_id'] === (int)($_SESSION['division_id'] ?? 0);
}
page_top('Physical records inventory');
?>
<?php $tabs = ['records' => 'Inventory', 'register' => 'Register and progress']; if ($canScan) $tabs['batches'] = 'Scan batches'; tabs_begin('Physical records inventory', $tabs, true); tab_open('records'); ?>
<div class="card invcard">
<form class="row noprint invfilter" method="get">
  <label>Search <input name="q" value="<?= e($f['q']) ?>"></label>
  <label>Division <select name="division_id"><?= select_options($divs, 'id', 'name', $f['division_id'], true) ?></select></label>
  <label>Status <select name="status"><?= status_options($f['status']) ?></select></label>
  <label>Condition <select name="flag"><option value="">All</option><option value="issue" <?= $f['flag'] === 'issue' ? 'selected' : '' ?>>Missing / damaged / unlocated</option></select></label>
  <label>Scan <select name="scan"><option value="">All</option><option value="with" <?= $f['scan'] === 'with' ? 'selected' : '' ?>>With scan</option><option value="without" <?= $f['scan'] === 'without' ? 'selected' : '' ?>>No scan yet</option></select></label>
  <label>Per page <select name="per"><?php foreach ($perOpts as $o): ?><option value="<?= $o ?>" <?= $o === $per ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></label>
  <button>Filter</button> <button type="button" class="alt" onclick="window.print()">Print inventory sheet</button>
  <a class="btn alt" href="qr.php">QR labels</a>
</form>
<div class="tw"><table>
<tr><th>Inventory no.</th><th>Title</th><th>Division</th><th>Type</th><th>Year</th><th>Location</th><th>Custodian</th><th>Status</th><th>Access</th><th>Condition</th><th>Scan</th><?php if ($edit): ?><th class="noprint">Mark</th><?php endif; ?></tr>
<?php foreach ($rows as $r): ?>
<tr><td><a href="record.php?id=<?= $r['id'] ?>"><?= e($r['inventory_no']) ?></a></td><td><?= e($r['title']) ?></td><td><?= e($r['division']) ?></td>
<td><?= e($r['category']) ?></td><td><?= $r['doc_year'] ?></td><td><?= e(location_text($r)) ?></td><td><?= e($r['custodian'] ?? '—') ?></td>
<td><?= status_badge($r['status']) ?></td><td><?= access_badge($r['access_level']) ?></td><td><?= $r['condition_flag'] === 'ok' ? badge('Good', 'ok') : flag_badge($r['condition_flag']) ?></td>
<td class="scancell"><?php if ($r['file_id']): ?><a class="scanfile <?= $r['file_flag'] === 'none' ? 'ok' : 'bad' ?>" href="download.php?id=<?= $r['file_id'] ?>&amp;mode=view" target="_blank" rel="noopener" title="<?= e($r['file_name']) ?> &middot; v<?= (int)$r['file_ver'] ?> &middot; <?= (int)$r['file_pages'] ?> pg<?= $r['file_flag'] !== 'none' ? ' &middot; flagged ' . e($r['file_flag']) : '' ?>"><?= e($r['file_name']) ?></a><?php else: ?><span class="muted">No scan</span><?php endif; ?>
  <?php if (can_scan_here($r)): ?><button type="button" class="sm alt js-scan noprint" data-id="<?= $r['id'] ?>" data-inv="<?= e($r['inventory_no']) ?>" data-title="<?= e($r['title']) ?>" data-replace="<?= $r['file_id'] ? 1 : 0 ?>"><?= $r['file_id'] ? 'Replace' : 'Upload scan' ?></button><?php endif; ?></td>
<?php if ($edit): ?><td class="noprint"><form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="flag"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<select name="flag" aria-label="Mark condition" onchange="if(this.form.requestSubmit){this.form.requestSubmit()}else{this.form.submit()}"><?php foreach (['ok' => 'Good', 'missing' => 'Missing', 'damaged' => 'Damaged', 'unlocated' => 'Unlocated'] as $v => $lbl): ?><option value="<?= $v ?>" <?= $r['condition_flag'] === $v ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select><noscript><button class="sm alt">Set</button></noscript></form></td><?php endif; ?></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="12" class="muted">No records match. Adjust the filters or register a file.</td></tr><?php endif; ?>
</table></div>
<?php if ($total > 0): $from = ($pg - 1) * $per + 1; $to = min($total, $pg * $per); ?>
<div class="pager noprint" role="navigation" aria-label="Pages"><span class="muted">Showing <?= $from ?>&ndash;<?= $to ?> of <?= $total ?></span>
  <span class="pgbtns"><?php if ($pg > 1): ?><a class="btn alt sm" href="<?= e($pageUrl($pg - 1)) ?>">&laquo; Prev</a><?php else: ?><span class="btn alt sm dis">&laquo; Prev</span><?php endif; ?>
  <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++): ?><a class="btn sm <?= $i === $pg ? '' : 'alt' ?>" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a><?php endfor; ?>
  <?php if ($pg < $pages): ?><a class="btn alt sm" href="<?= e($pageUrl($pg + 1)) ?>">Next &raquo;</a><?php else: ?><span class="btn alt sm dis">Next &raquo;</span><?php endif; ?></span></div>
<?php endif; ?>
</div>
<?php tab_close(); tab_open('register'); ?>
<div class="card noprint scanprog regcombo">
<?php if ($edit): ?>
  <div class="regcard regrow"><div><h2 style="margin:0 0 2px">Register a physical file</h2>
    <span class="muted"><?= $canScan ? 'Step 1: scan or upload the document. Step 2: fill in the file details.' : 'Fill in the file details.' ?></span></div>
    <button type="button" id="regOpen">+ Register a physical file</button></div>
<?php endif; ?>
  <div class="progwrap"><div class="sp-top"><strong>Digitization progress</strong><span><?= $scn ?> of <?= $tot ?> folders have a scan (<?= $pct ?>%)</span></div>
  <div class="bar"><i style="width:<?= $pct ?>%"></i></div></div>
</div>
<?php tab_close(); ?>
<?php if ($canScan): tab_open('batches'); ?>
<div class="card"><h2>Scan batches and flagged scans</h2>
<h3>New batch</h3><form method="post" action="scanning.php" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="batch">
  <label>Division <select name="division_id"><option value="">Any</option><?= select_options($divs, 'id', 'name') ?></select></label>
  <label>Notes <input name="notes"></label><button class="alt">Create batch</button></form>
<h3>Batch progress</h3><div class="tw"><table><tr><th>Batch</th><th>Division</th><th>Operator</th><th>Date</th><th>Folders</th><th>Files</th><th>Flagged</th></tr>
<?php foreach ($batches as $b): ?><tr><td><?= e($b['batch_no']) ?></td><td><?= e($b['division'] ?? 'Any') ?></td><td><?= e($b['operator']) ?></td><td><?= fmt_d($b['created_at']) ?></td><td><?= $b['recs'] ?></td><td><?= $b['files'] ?></td><td><?= (int)$b['flagged'] ?></td></tr>
<?php endforeach; if (!$batches): ?><tr><td colspan="7" class="muted">No batches yet.</td></tr><?php endif; ?></table></div>
<h3>Flagged scans</h3><div class="tw"><table><tr><th>Record</th><th>File</th><th>Flag</th><th>Change flag</th></tr>
<?php foreach ($flagged as $fl): ?><tr><td><?= e($fl['inventory_no']) ?></td><td><?= e($fl['original_name']) ?> v<?= $fl['version'] ?></td><td><?= badge($fl['scan_flag'], 'bad') ?></td>
<td><form method="post" action="scanning.php" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="flag"><input type="hidden" name="file_id" value="<?= $fl['id'] ?>">
<select name="flag"><?php foreach (['none', 'unreadable', 'incomplete', 'duplicate'] as $x): ?><option <?= $fl['scan_flag'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select><button class="sm alt">Set</button></form></td></tr>
<?php endforeach; if (!$flagged): ?><tr><td colspan="4" class="muted">No flagged scans.</td></tr><?php endif; ?></table></div>
</div>
<?php tab_close(); endif; ?>
<?php tabs_end(); ?>
<?php if ($canScan): ?>
<dialog id="scanDlg" class="sdlg">
  <form method="post" action="scanning.php" enctype="multipart/form-data" data-xhr>
    <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="return" value="inventory.php"><input type="hidden" name="ajax" value="1"><input type="hidden" name="record_id" value="">
    <h3 id="sdTitle">Upload scan</h3><p class="muted" id="sdRec"></p>
    <input type="hidden" name="page_count" id="sdCount" value="0">
    <div id="sdPages"></div>
    <label id="sdReasonWrap" hidden>Reason for replacing the current scan <input name="reason" id="sdReason" maxlength="200" placeholder="e.g. rescan, pages were missing"></label>
    <label>Scan batch (optional) <select name="batch_id"><option value="">No batch</option><?= select_options($batches, 'id', 'batch_no') ?></select></label>
    <div class="upbar" id="sdBar" hidden><i></i><span>0%</span></div>
    <p class="err-text" id="sdErr" hidden></p>
    <div class="acts"><button type="button" class="alt" id="sdCancel">Cancel</button><button id="sdGo">Upload</button></div>
  </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var dlg = document.getElementById('scanDlg'); if (!dlg) return;
  var form = dlg.querySelector('form'), err = document.getElementById('sdErr'), bar = document.getElementById('sdBar'),
      go = document.getElementById('sdGo'), reasonWrap = document.getElementById('sdReasonWrap'), reason = document.getElementById('sdReason'),
      count = document.getElementById('sdCount'), busy = false;
  function showErr(m) { err.textContent = m; err.hidden = !m; }
  // One physical file can have several pages: add them all here, they are saved as ONE file
  var pg = DARPages.mount(document.getElementById('sdPages'), {
    allowed: <?= json_encode($allowedExt) ?>, maxBytes: <?= $maxMb ?> * 1048576, maxMb: <?= $maxMb ?>, maxPages: <?= $maxPages ?>,
    onChange: function (n) { count.value = n; if (n) showErr(''); }, onError: showErr
  });
  function reset() { form.reset(); pg.clear(); showErr(''); bar.hidden = true; go.classList.remove('loading'); busy = false; }
  function check() {
    if (!pg.count()) return 'Add the scanned page(s) first.';
    if (!reasonWrap.hidden && !reason.value.trim()) return 'Enter the reason for replacing the current scan.';
    return '';
  }
  document.getElementById('sdCancel').addEventListener('click', function () { if (!busy) dlg.close(); });
  dlg.addEventListener('cancel', function (e) { if (busy) e.preventDefault(); });
  dlg.addEventListener('close', reset);
  dlg.addEventListener('click', function (e) { if (e.target === dlg && !busy && !pg.count()) dlg.close(); });

  document.addEventListener('click', function (e) {
    var b = e.target.closest('.js-scan'); if (!b) return;
    reset(); form.record_id.value = b.dataset.id;
    var rep = b.dataset.replace === '1';
    document.getElementById('sdTitle').textContent = rep ? 'Replace scan' : 'Upload scan';
    document.getElementById('sdRec').textContent = b.dataset.inv + ' \u2014 ' + b.dataset.title;
    reasonWrap.hidden = !rep; reason.required = rep;
    dlg.showModal();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return;
    var m = check(); if (m) { showErr(m); return; }
    showErr(''); busy = true; go.classList.add('loading'); bar.hidden = false;
    var x = new XMLHttpRequest(), fill = bar.querySelector('i'), lbl = bar.querySelector('span');
    x.open('POST', form.getAttribute('action'));
    x.upload.onprogress = function (ev) { if (ev.lengthComputable) { var p = Math.round(ev.loaded * 100 / ev.total); fill.style.width = p + '%'; lbl.textContent = p < 100 ? p + '%' : 'Processing\u2026'; } };
    x.onload = function () { var r = null; try { r = JSON.parse(x.responseText); } catch (z) {} if (r && r.ok === false) { busy = false; go.classList.remove('loading'); bar.hidden = true; showErr(r.msg || 'Could not save the scan.'); } else if (x.status === 200) { location.href = 'inventory.php' + location.search; } else { busy = false; go.classList.remove('loading'); bar.hidden = true; showErr('Upload failed (HTTP ' + x.status + '). Refresh the page and try again.'); } };
    x.onerror = function () { busy = false; go.classList.remove('loading'); bar.hidden = true; showErr('Network error. Check the connection and try again.'); };
    x.send(new FormData(form));
  });
});
</script>
<?php endif; ?>
<?php if ($edit): ?>
<?php if ($canScan): ?>
<dialog id="rgScan" class="sdlg"><div class="dpad">
  <p class="stepno">Step 1 of 2</p><h3>Scan or upload the document</h3>
  <p class="muted">Add every page of this physical file, in order. A document with several pages is saved as <strong>one file</strong>. Press Continue when all pages are in.</p>
  <div id="rgPages"></div>
  <p class="err-text" id="rgErr1" hidden></p>
  <div class="acts"><button type="button" class="alt" id="rgCancel1">Cancel</button><button type="button" id="rgNext" disabled>Continue</button></div>
</div></dialog>
<?php endif; ?>
<dialog id="rgReg" class="sdlg wide">
  <form method="post" action="inventory.php" enctype="multipart/form-data" id="rgForm" data-xhr class="dpad">
    <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="ajax" value="1"><input type="hidden" name="page_count" id="rgCount" value="0">
    <p class="stepno"><?= $canScan ? 'Step 2 of 2' : 'New record' ?></p><h3>Register a physical file</h3>
    <?php if ($canScan): ?><div class="chip" id="rgChip"><img id="rgThumb" alt="" hidden><div><strong id="rgChipName"></strong><div class="muted" id="rgChipSize"></div></div><button type="button" class="alt sm" id="rgChange">Change pages</button></div><?php endif; ?>
    <div class="rgrid">
      <label class="span2">Title <input name="title" required></label>
      <label>Reference no. <input name="ref_no"></label>
      <label>Division <select name="division_id"><?= select_options($divs, 'id', 'name') ?></select></label>
      <label>Section <select name="section_id"><option value="">None</option><?= select_options($secs, 'id', 'label') ?></select></label>
      <label>Document type <select name="category_id"><?= select_options($cats, 'id', 'name') ?></select></label>
      <label>Year <input type="number" name="doc_year" value="<?= date('Y') ?>"></label>
      <label>Volume <input name="volume"></label>
      <?= storage_picker() ?>
      <label>Records custodian <select name="custodian_id"><option value="">Unassigned</option><?= select_options($users, 'id', 'full_name') ?></select></label>
      <?php if ($canScan): ?><label>Scan batch (optional) <select name="batch_id"><option value="">No batch</option><?= select_options($batches, 'id', 'batch_no') ?></select></label><?php endif; ?>
    </div>
    <p class="muted">Access level starts from the category default. Changing it later needs an approved classification request.</p>
    <div class="upbar" id="rgBar" hidden><i></i><span>0%</span></div>
    <p class="err-text" id="rgErr2" hidden></p>
    <div class="acts"><button type="button" class="alt" id="rgCancel2">Cancel</button><button id="rgGo">Register</button></div>
  </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var openBtn = document.getElementById('regOpen'); if (!openBtn) return;
  var need = <?= $canScan ? 'true' : 'false' ?>;
  var d1 = document.getElementById('rgScan'), d2 = document.getElementById('rgReg'), form = document.getElementById('rgForm'),
      err1 = document.getElementById('rgErr1'), err2 = document.getElementById('rgErr2'), bar = document.getElementById('rgBar'),
      go = document.getElementById('rgGo'), thumb = document.getElementById('rgThumb'), next = document.getElementById('rgNext'),
      count = document.getElementById('rgCount'), keep = false, busy = false, pg = null;
  function show(el, m) { if (el) { el.textContent = m; el.hidden = !m; } }
  function resetAll() { form.reset(); if (pg) pg.clear(); show(err1, ''); show(err2, ''); bar.hidden = true; go.classList.remove('loading'); busy = false; }
  function swap(from, to) { keep = true; if (from.open) from.close(); keep = false; to.showModal(); }

  openBtn.addEventListener('click', function () { resetAll(); (need ? d1 : d2).showModal(); });

  if (need) {
    // Step 1: collect the pages of this physical file (one or many), then continue to the details
    pg = DARPages.mount(document.getElementById('rgPages'), {
      allowed: <?= json_encode($allowedExt) ?>, maxBytes: <?= $maxMb ?> * 1048576, maxMb: <?= $maxMb ?>, maxPages: <?= $maxPages ?>, form: 'rgForm',
      onChange: function (n) { next.disabled = !n; count.value = n; if (n) show(err1, ''); },
      onError: function (m) { show(err1, m); }
    });
    next.addEventListener('click', function () {
      var n = pg.count(); if (!n) { show(err1, 'Add at least one page first.'); return; }
      var first = pg.first();
      document.getElementById('rgChipName').textContent = n === 1 ? first.file.name : n + ' pages';
      document.getElementById('rgChipSize').textContent = n === 1 ? DARPages.size(first.file.size) : 'Saved together as one PDF \u00b7 ' + DARPages.size(pg.total());
      if (first.url) { thumb.src = first.url; thumb.hidden = false; } else { thumb.hidden = true; thumb.removeAttribute('src'); }
      var t = form.elements['title']; if (!t.value.trim()) t.value = first.file.name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ').trim();
      swap(d1, d2);
    });
    // Pages are easy to lose by accident now, so ask before throwing a collected set away
    function leave() { return !pg.count() || confirm('Discard the ' + pg.count() + ' page(s) you added?'); }
    document.getElementById('rgCancel1').addEventListener('click', function () { if (leave()) d1.close(); });
    d1.addEventListener('cancel', function (e) { if (!leave()) e.preventDefault(); });
    d1.addEventListener('close', function () { if (!keep && !d2.open) resetAll(); });
    d1.addEventListener('click', function (e) { if (e.target === d1 && !pg.count()) d1.close(); });
    document.getElementById('rgChange').addEventListener('click', function () { if (busy) return; swap(d2, d1); });   // pages stay, so more can be added or removed
  }
  document.getElementById('rgCancel2').addEventListener('click', function () { if (!busy) d2.close(); });
  d2.addEventListener('cancel', function (e) { if (busy) e.preventDefault(); });
  d2.addEventListener('close', function () { if (!keep && !busy && !(d1 && d1.open)) resetAll(); });

  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return;
    if (need && !pg.count()) { show(err2, 'Scan or upload the document first.'); return; }
    show(err2, ''); busy = true; go.classList.add('loading'); bar.hidden = false;
    var x = new XMLHttpRequest(), fill = bar.querySelector('i'), lbl = bar.querySelector('span');
    function stop(m) { busy = false; go.classList.remove('loading'); bar.hidden = true; fill.style.width = '0'; show(err2, m); }
    x.open('POST', form.getAttribute('action'));
    x.upload.onprogress = function (ev) { if (ev.lengthComputable) { var p = Math.round(ev.loaded * 100 / ev.total); fill.style.width = p + '%'; lbl.textContent = p < 100 ? p + '%' : 'Saving\u2026'; } };
    x.onload = function () {
      var r = null; try { r = JSON.parse(x.responseText); } catch (z) {}
      if (r && r.ok) { location.href = 'inventory.php' + location.search; }
      else if (r) { stop(r.msg || 'Could not register the file.'); }
      else { stop('Upload failed (HTTP ' + x.status + '). The files may be larger than the server limit, or the page expired. Refresh and try again.'); }
    };
    x.onerror = function () { stop('Network error. Check the connection and try again.'); };
    x.send(new FormData(form));
  });
});
</script>
<?php endif; ?>
<?php page_bottom();