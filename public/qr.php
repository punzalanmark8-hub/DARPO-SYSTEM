<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('qr');
[$vw, $vp] = visible_where('r');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'printed' && can('inventory.edit')) {
    $ids = array_filter(array_map('intval', explode(',', post('ids'))));
    if ($ids) { q('UPDATE records SET label_printed_at=NOW() WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_values($ids)); audit('labels_printed', 'record', null, implode(',', $ids)); }
    flash('ok', 'Labels marked as printed.');
    redirect('qr.php');
}
$ids = array_filter(array_map('intval', explode(',', get('ids'))));
$where = [$vw]; $par = $vp;
if ($ids) { $where[] = 'r.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $par = array_merge($par, array_values($ids)); }
elseif (get('division_id')) { $where[] = 'r.division_id=?'; $par[] = (int)get('division_id'); }
if (get('unprinted') === '1') $where[] = 'r.label_printed_at IS NULL';
$labels = ($ids || get('division_id') || get('unprinted') === '1') ? all('SELECT r.* FROM records r WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id LIMIT 200', $par) : [];
$divs = all('SELECT id,name FROM divisions WHERE is_active=1 ORDER BY name');
$scatLabel = null;
if (get('scat') !== '') $scatLabel = one('SELECT * FROM storage_categories WHERE id=? AND qr_token IS NOT NULL', [(int)get('scat')]);
$base = rtrim(cfg()['app']['base_url'], '/');
page_top('QR labels');
?>
<form method="get" class="card row noprint"><label>Division <select name="division_id"><?= select_options($divs, 'id', 'name', get('division_id'), true) ?></select></label>
  <label><span><input type="checkbox" name="unprinted" value="1" <?= get('unprinted') === '1' ? 'checked' : '' ?>> Only labels not yet printed</span></label><button>Show labels</button>
  <?php if ($labels): ?><button type="button" class="alt" onclick="window.print()">Print</button><?php endif; ?></form>
<?php if ($scatLabel): ?>
<div class="card noprint"><button type="button" class="alt" onclick="window.print()">Print</button> <a href="settings.php#tab-storagecats">Back to Storage categories</a></div>
<div class="labels"><div class="lbl" style="width:320px"><div class="qr" data-url="<?= e($base . '/scan.php?s=' . $scatLabel['qr_token']) ?>"></div>
  <div><strong><?= e($scatLabel['name']) ?><?= $scatLabel['code'] ? ' (' . e($scatLabel['code']) . ')' : '' ?></strong><small><?= e(STORAGE_TYPES[$scatLabel['storage_type']] ?? '') ?></small><small>Scan to see the scanned documents</small></div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>document.querySelectorAll('.qr').forEach(function (el) { new QRCode(el, {text: el.dataset.url, width: 90, height: 90}); });</script>
<?php endif; ?>
<?php if ($labels): ?>
<div class="labels">
<?php foreach ($labels as $r): ?><div class="lbl"><div class="qr" data-url="<?= e($base . '/scan.php?t=' . $r['qr_token']) ?>"></div>
  <div><strong><?= e($r['inventory_no']) ?></strong><small><?= e(mb_strimwidth($r['title'], 0, 40, '...')) ?></small><small><?= e(location_text($r)) ?></small></div></div>
<?php endforeach; ?></div>
<?php if (can('inventory.edit')): ?><form method="post" class="noprint"><?= csrf_field() ?><input type="hidden" name="action" value="printed"><input type="hidden" name="ids" value="<?= implode(',', array_column($labels, 'id')) ?>"><p><button class="alt">Mark these labels as printed</button></p></form><?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>document.querySelectorAll('.qr').forEach(function (el) { new QRCode(el, {text: el.dataset.url, width: 90, height: 90}); });</script>
<?php elseif (!$scatLabel): ?><div class="card muted">Choose a division or tick "only labels not yet printed" to list labels. Every record has a unique code, so reprinting never creates duplicates.</div><?php endif;
page_bottom();