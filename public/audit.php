<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('audit.view');
$f = ['user' => get('user'), 'action' => get('action'), 'from' => get('from'), 'to' => get('to')];
$where = ['1=1']; $par = [];
if ($f['user'] !== '') { $where[] = 'username LIKE ?'; $par[] = '%' . $f['user'] . '%'; }
if ($f['action'] !== '') { $where[] = 'action LIKE ?'; $par[] = '%' . $f['action'] . '%'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'created_at >= ?'; $par[] = $f['from'] . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'created_at <= ?'; $par[] = $f['to'] . ' 23:59:59'; }
$per = 10;
$total = (int)val('SELECT COUNT(*) FROM audit_logs WHERE ' . implode(' AND ', $where), $par);
$pages = max(1, (int)ceil($total / $per));
$pg = min($pages, max(1, (int)get('page')));
$rows = get('export') === '1'
    ? all('SELECT * FROM audit_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 5000', $par)
    : all('SELECT * FROM audit_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($pg - 1) * $per), $par);
if (get('export') === '1') {
    audit('audit_exported', null, null, count($rows) . ' rows');
    csv_download('audit-trail.csv', ['When', 'User', 'Action', 'Entity', 'Entity ID', 'Details', 'IP'], array_map(fn($r) => [$r['created_at'], $r['username'], $r['action'], $r['entity'], $r['entity_id'], $r['details'], $r['ip']], $rows));
}
page_top('Audit trail and security monitoring');
?>
<div class="note">Entries are append-only. Database triggers reject any UPDATE or DELETE on the audit table.</div>
<form class="card row" method="get"><label>User <input name="user" value="<?= e($f['user']) ?>"></label><label>Action <input name="action" value="<?= e($f['action']) ?>" placeholder="e.g. login_failed"></label>
<label>From <input type="date" name="from" value="<?= e($f['from']) ?>"></label><label>To <input type="date" name="to" value="<?= e($f['to']) ?>"></label>
<button>Filter</button><button name="export" value="1" class="alt">Export CSV</button></form>
<div class="card tw"><table><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr>
<?php foreach ($rows as $a): ?><tr><td><?= fmt_dt($a['created_at']) ?></td><td><?= e($a['username'] ?? '—') ?></td><td><?= strpos($a['action'], 'fail') !== false || strpos($a['action'], 'denied') !== false ? badge($a['action'], 'bad') : e($a['action']) ?></td>
<td><?= e(trim(($a['entity'] ?? '') . ' ' . ($a['entity_id'] ?? ''))) ?></td><td><?= e($a['details']) ?></td><td><?= e($a['ip']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No entries match.</td></tr><?php endif; ?></table></div>
<?php
$qs = http_build_query(array_filter($f, fn($v) => $v !== ''));
$link = fn(int $n, string $label, bool $on = false) => '<a class="btn sm ' . ($on ? '' : 'alt') . '" href="audit.php?' . e($qs . ($qs !== '' ? '&' : '') . 'page=' . $n) . '">' . $label . '</a> ';
$from = $total ? ($pg - 1) * $per + 1 : 0; $to = min($total, $pg * $per);
?>
<div class="card row noprint" style="justify-content:space-between;align-items:center">
  <span class="muted">Showing <?= $from ?>&ndash;<?= $to ?> of <?= $total ?> entries &middot; Page <?= $pg ?> of <?= $pages ?></span>
  <?php if ($pages > 1): ?><span>
    <?php if ($pg > 1): ?><?= $link(1, '&laquo; First') ?><?= $link($pg - 1, '&lsaquo; Prev') ?><?php endif; ?>
    <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++) echo $link($i, (string)$i, $i === $pg); ?>
    <?php if ($pg < $pages): ?><?= $link($pg + 1, 'Next &rsaquo;') ?><?= $link($pages, 'Last &raquo;') ?><?php endif; ?>
  </span><?php endif; ?>
</div>
<?php page_bottom();