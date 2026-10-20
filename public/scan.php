<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('qr');
$code = trim((string)(get('t') ?: get('code')));
$found = null; $scat = null; $stoken = trim((string)get('s'));
if ($stoken === '' && preg_match('/[?&]s=([a-f0-9]{16})/i', $code, $m)) $stoken = $m[1];   // scanned full storage URL
if ($stoken !== '' && preg_match('/^[a-f0-9]{16}$/i', $stoken))
    $scat = one('SELECT * FROM storage_categories WHERE qr_token=?', [strtolower($stoken)]);
if ($scat) {
    $code = ''; $sno = max(0, (int)get('no'));
    audit('storage_scanned', 'storage_category', $scat['id'], (STORAGE_TYPES[$scat['storage_type']] ?? '') . ': ' . $scat['name']);
    [$vw, $vp] = visible_where('r');
    $w = [$vw, 'r.storage_category_id=?']; $pr = array_merge($vp, [(int)$scat['id']]);
    $totalAll = (int)val('SELECT COUNT(*) FROM records WHERE storage_category_id=?', [(int)$scat['id']]);
    $nums = array_column(all('SELECT DISTINCT r.storage_no FROM records r WHERE ' . implode(' AND ', $w) . ' ORDER BY r.storage_no', $pr), 'storage_no');
    if ($sno) { $w[] = 'r.storage_no=?'; $pr[] = $sno; }
    $srecs = all('SELECT r.*, d.name division FROM records r JOIN divisions d ON d.id=r.division_id WHERE ' . implode(' AND ', $w) . ' ORDER BY r.storage_no, r.inventory_no LIMIT 500', $pr);
    $sfiles = [];
    if ($srecs) {
        $ids = array_column($srecs, 'id');
        foreach (all('SELECT * FROM digital_files WHERE is_current=1 AND record_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY uploaded_at DESC', $ids) as $f) $sfiles[(int)$f['record_id']][] = $f;
    }
    $groups = [];
    foreach ($srecs as $r) $groups[storage_label($r) ?: 'Unassigned'][] = $r;
    $hidden = $sno ? 0 : max(0, $totalAll - count($srecs));
}
if (!$scat && $code !== '') {
    if (preg_match('/[?&]t=([a-f0-9]{16})/i', $code, $m)) $code = $m[1]; // scanned full URL
    $found = one('SELECT r.*, d.name division FROM records r JOIN divisions d ON d.id=r.division_id WHERE r.qr_token=? OR r.inventory_no=?', [$code, $code]);
    if ($found && !can_view_record($found)) { audit('access_denied', 'record', $found['id'], 'label scan'); $found = null; $denied = true; }
    elseif ($found) audit('label_scanned', 'record', $found['id'], $found['inventory_no']);
    if (!$found && empty($denied) && preg_match('/^[a-f0-9]{16}$/i', $code) && one('SELECT id FROM storage_categories WHERE qr_token=?', [strtolower($code)]))
        redirect('scan.php?s=' . strtolower($code));
}
page_top('Scan a QR code or barcode');
?>
<div class="card">
  <form method="get" class="row"><label>Scan or type an inventory number / QR code <input name="code" id="code" value="<?= e($code) ?>" autofocus style="min-width:280px"></label><button>Open record</button></form>
  <p class="muted">A USB barcode scanner types into the box above. To use a phone camera, open this page over HTTPS.</p>
  <div id="reader" style="max-width:360px"></div>
</div>
<?php if ($scat): ?>
<div class="card"><h2><?= e(STORAGE_TYPES[$scat['storage_type']] ?? $scat['storage_type']) ?> &rsaquo; <?= e($scat['name']) ?><?= $scat['code'] ? ' (' . e($scat['code']) . ')' : '' ?></h2>
  <p class="muted"><?= count($srecs) ?> file(s) shown<?= $sno ? ' for No. ' . $sno : '' ?>. Scanned documents stored in this category are listed below.</p>
  <?php if (count($nums) > 1 || $sno): ?><p class="noprint"><a class="btn sm <?= $sno ? 'alt' : '' ?>" href="scan.php?s=<?= e($scat['qr_token']) ?>">All</a>
    <?php foreach ($nums as $n): ?><a class="btn sm <?= (int)$n === $sno ? '' : 'alt' ?>" href="scan.php?s=<?= e($scat['qr_token']) ?>&amp;no=<?= (int)$n ?>"><?= $scat['storage_type'] === 'document_holder' ? e(($scat['code'] ?: 'No.') . '-' . $n) : 'Cabinet ' . (int)$n ?></a> <?php endforeach; ?></p><?php endif; ?>
  <?php if (!empty($hidden)): ?><p class="muted"><?= $hidden ?> restricted file(s) in this category are not shown because you do not have access to them.</p><?php endif; ?>
</div>
<?php foreach ($groups as $label => $recs): ?>
<div class="card tw"><h3><?= e($label) ?></h3>
<table><tr><th>Inventory no.</th><th>Title</th><th>Year</th><th>Scanned document</th></tr>
<?php foreach ($recs as $r): $fl = $sfiles[(int)$r['id']] ?? []; ?>
<tr><td><a href="record.php?id=<?= (int)$r['id'] ?>"><?= e($r['inventory_no']) ?></a><br><?= status_badge($r['status']) ?></td><td><?= e($r['title']) ?></td><td><?= (int)$r['doc_year'] ?></td>
<td><?php foreach ($fl as $f): ?><div><?= e($f['original_name']) ?> <span class="muted">(<?= (int)$f['page_count'] ?> p)</span> <a href="download.php?id=<?= (int)$f['id'] ?>&amp;mode=view">Preview</a> | <a href="download.php?id=<?= (int)$f['id'] ?>&amp;mode=download">Download</a></div><?php endforeach; if (!$fl): ?><span class="muted">No scanned copy yet</span><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php endforeach; if (!$srecs): ?><div class="card muted">No records are filed under this category yet.</div><?php endif; ?>
<?php elseif ($stoken !== ''): ?><div class="flash err">No storage category matches that QR code.</div><?php endif; ?>
<?php if ($code !== '' && !$found): ?><div class="flash err"><?= !empty($denied) ? 'You do not have access to that record.' : 'No record matches that code.' ?></div><?php endif; ?>
<?php if ($found): ?><div class="card"><h2><?= e($found['inventory_no']) ?></h2><p><?= e($found['title']) ?></p>
  <p>Location: <strong><?= e(location_text($found)) ?></strong> <?= flag_badge($found['condition_flag']) ?><br>Status: <?= status_badge($found['status']) ?> · Access: <?= access_badge($found['access_level']) ?></p>
  <a class="btn" href="record.php?id=<?= $found['id'] ?>">Open full record</a></div><?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
if (window.Html5QrcodeScanner) {
  new Html5QrcodeScanner('reader', {fps: 10, qrbox: 220}, false).render(function (t) {
    document.getElementById('code').value = t; document.querySelector('form').submit();
  }, function () {});
}
</script>
<?php page_bottom();