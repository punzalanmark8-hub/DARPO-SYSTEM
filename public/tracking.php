<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('inventory.view');
refresh_overdue();
$upd = can('tracking.update');
// Manual forward steps. For Verification -> Digitized -> Archived happen in the Verification module.
const MANUAL_NEXT = ['inventoried' => 'scanning', 'scanning' => 'for_verification'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $upd) {
    $r = one('SELECT * FROM records WHERE id=?', [(int)post('id')]);
    if ($r && can_view_record($r)) {
        if (post('action') === 'advance' && isset(MANUAL_NEXT[$r['status']])) {
            $to = MANUAL_NEXT[$r['status']];
            if ($r['condition_flag'] === 'missing') flash('err', 'A missing file cannot move forward. Locate it first.');
            elseif ($to === 'for_verification' && !val("SELECT COUNT(*) FROM digital_files WHERE record_id=? AND is_current=1 AND scan_flag IN ('none')", [$r['id']]))
                flash('err', 'Upload a clean scan (not flagged) before sending this folder for verification.');
            else {
                q('UPDATE records SET status=? WHERE id=?', [$to, $r['id']]);
                add_history((int)$r['id'], 'status_change', $r['status'], $to, post('remarks') ?: null);
                audit('status_change', 'record', $r['id'], "{$r['status']} -> $to");
                if ($to === 'for_verification') notify_roles(['verifier', 'admin'], "{$r['inventory_no']} is ready for verification", 'verification.php');
                flash('ok', $r['inventory_no'] . ' is now ' . status_label($to) . '.');
            }
        } elseif (post('action') === 'transfer') {
            $to = (int)post('custodian_id'); $div = (int)post('division_id');
            $u = $to ? one('SELECT * FROM users WHERE id=? AND is_active=1', [$to]) : null;
            if (!$u && !$div) flash('err', 'Choose a custodian or a division to transfer to.');
            else {
                q('UPDATE records SET custodian_id=COALESCE(?,custodian_id), division_id=COALESCE(?,division_id) WHERE id=?', [$u['id'] ?? null, $div ?: null, $r['id']]);
                $note = 'Transferred' . ($u ? ' to ' . $u['full_name'] : '') . ($div ? ' / division #' . $div : '') . (post('remarks') ? ': ' . post('remarks') : '');
                add_history((int)$r['id'], 'transfer', null, null, $note);
                audit('record_transfer', 'record', $r['id'], $note);
                if ($u) notify((int)$u['id'], "{$r['inventory_no']} was transferred to you", 'record.php?id=' . $r['id']);
                flash('ok', 'Transfer recorded.');
            }
        }
    }
    redirect('tracking.php' . (get('status') ? '?status=' . urlencode(get('status')) : ''));
}

$status = get('status'); $qs = get('q');
[$vw, $vp] = visible_where('r');
$where = [$vw]; $par = $vp;
if (isset(STATUSES[$status])) { $where[] = 'r.status=?'; $par[] = $status; }
if ($qs !== '') { $where[] = '(r.inventory_no LIKE ? OR r.title LIKE ?)'; array_push($par, "%$qs%", "%$qs%"); }
$rows = all('SELECT r.*, d.name division, u.full_name custodian, (SELECT MAX(created_at) FROM record_history WHERE record_id=r.id) last_move
             FROM records r JOIN divisions d ON d.id=r.division_id LEFT JOIN users u ON u.id=r.custodian_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.status, r.id DESC LIMIT 300', $par);
// Pagination: 5 per page by default, switchable
$perOpts = [5, 10, 20, 50]; $per = in_array((int)get('per'), $perOpts, true) ? (int)get('per') : 5;
$total = count($rows); $pages = max(1, (int)ceil($total / $per)); $pg = max(1, min($pages, (int)get('page') ?: 1));
$rows = array_slice($rows, ($pg - 1) * $per, $per);
$pageUrl = fn(int $p) => 'tracking.php?' . http_build_query(['q' => $qs, 'status' => $status, 'per' => $per, 'page' => $p]);
$overdue = (int)val("SELECT COUNT(*) FROM custody WHERE status='overdue'");
$users = all('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name');
$divs = all('SELECT id,name FROM divisions WHERE is_active=1 ORDER BY name');
page_top('Document tracking and monitoring');
?>
<?php if ($overdue): ?><div class="flash err"><?= $overdue ?> loaned folder(s) are overdue. <a href="custody.php">Review custody</a></div><?php endif; ?>
<form class="trkbar" method="get" role="search">
  <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search inventory no. or title" aria-label="Search">
  <select name="status" aria-label="Status" title="Status"><?= status_options($status) ?></select>
  <select name="per" aria-label="Records per page" title="Records per page"><?php foreach ($perOpts as $o): ?><option value="<?= $o ?>" <?= $o === $per ? 'selected' : '' ?>><?= $o ?> per page</option><?php endforeach; ?></select>
  <button>Search</button>
</form>
<?php foreach ($rows as $r): $next = MANUAL_NEXT[$r['status']] ?? null; ?>
<div class="card">
  <strong><a href="record.php?id=<?= $r['id'] ?>"><?= e($r['inventory_no']) ?></a></strong> <?= e($r['title']) ?> <?= flag_badge($r['condition_flag']) ?>
  <div class="muted"><?= e($r['division']) ?> · Custodian: <?= e($r['custodian'] ?? '—') ?> · Last movement: <?= fmt_dt($r['last_move']) ?></div>
  <div class="flow"><?php foreach (STATUSES as $k => $v): ?><span class="<?= $k === $r['status'] ? 'on' : '' ?>"><?= e($v) ?></span><?php endforeach; ?></div>
  <?php if ($upd): ?>
  <form method="post" class="row noprint"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
    <?php if ($next): ?><input type="hidden" name="action" value="advance"><input name="remarks" placeholder="Remarks (optional)"><button>Move to <?= e(status_label($next)) ?></button>
    <?php else: ?><span class="muted"><?= $r['status'] === 'for_verification' ? 'Waiting for a verifier.' : ($r['status'] === 'digitized' ? 'Ready for archiving approval.' : 'Completed.') ?></span><?php endif; ?>
  </form>
  <form method="post" class="row noprint"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="transfer">
    <label>Transfer custody to <select name="custodian_id"><option value="">—</option><?= select_options($users, 'id', 'full_name') ?></select></label>
    <label>and/or division <select name="division_id"><option value="">—</option><?= select_options($divs, 'id', 'name') ?></select></label>
    <input name="remarks" placeholder="Reason"><button class="alt">Record transfer</button></form>
  <?php endif; ?>
</div>
<?php endforeach; if (!$rows): ?><div class="card muted">No folders match this filter.</div><?php endif; ?>
<?php if ($total > 0): $from = ($pg - 1) * $per + 1; $to = min($total, $pg * $per); ?>
<div class="pager noprint" role="navigation" aria-label="Pages"><span class="muted">Showing <?= $from ?>&ndash;<?= $to ?> of <?= $total ?></span>
  <span class="pgbtns"><?php if ($pg > 1): ?><a class="btn alt sm" href="<?= e($pageUrl($pg - 1)) ?>">&laquo; Prev</a><?php else: ?><span class="btn alt sm dis">&laquo; Prev</span><?php endif; ?>
  <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++): ?><a class="btn sm <?= $i === $pg ? '' : 'alt' ?>" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a><?php endfor; ?>
  <?php if ($pg < $pages): ?><a class="btn alt sm" href="<?= e($pageUrl($pg + 1)) ?>">Next &raquo;</a><?php else: ?><span class="btn alt sm dis">Next &raquo;</span><?php endif; ?></span></div>
<?php endif; ?>
<?php page_bottom();