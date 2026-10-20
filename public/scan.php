<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('qr');
$code = trim((string)(get('t') ?: get('code')));
$found = null;
if ($code !== '') {
    if (preg_match('/[?&]t=([a-f0-9]{16})/i', $code, $m)) $code = $m[1]; // scanned full URL
    $found = one('SELECT r.*, d.name division FROM records r JOIN divisions d ON d.id=r.division_id WHERE r.qr_token=? OR r.inventory_no=?', [$code, $code]);
    if ($found && !can_view_record($found)) { audit('access_denied', 'record', $found['id'], 'label scan'); $found = null; $denied = true; }
    elseif ($found) audit('label_scanned', 'record', $found['id'], $found['inventory_no']);
}
page_top('Scan a QR code or barcode');
?>
<div class="card">
  <form method="get" class="row"><label>Scan or type an inventory number / QR code <input name="code" id="code" value="<?= e($code) ?>" autofocus style="min-width:280px"></label><button>Open record</button></form>
  <p class="muted">A USB barcode scanner types into the box above. To use a phone camera, open this page over HTTPS.</p>
  <div id="reader" style="max-width:360px"></div>
</div>
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
