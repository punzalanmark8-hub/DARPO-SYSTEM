<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('dashboard');
refresh_overdue();
[$vw, $vp] = visible_where('r');
$c = fn(string $extra = '1=1') => (int)val("SELECT COUNT(*) FROM records r WHERE $vw AND ($extra)", $vp);
$stats = [
    'Physical records inventoried' => $c(),
    'Awaiting scanning' => $c("r.status='inventoried'"),
    'Being scanned' => $c("r.status='scanning'"),
    'For verification' => $c("r.status='for_verification'"),
    'Completed digital records' => $c("r.status IN ('digitized','archived')"),
    'Open access' => $c("r.access_level='open'"),
    'Restricted' => $c("r.access_level='restricted'"),
    'Missing / damaged' => $c("r.condition_flag<>'ok'"),
];
$pending = all("SELECT d.name, COUNT(*) n FROM records r JOIN divisions d ON d.id=r.division_id
                WHERE $vw AND r.status NOT IN ('digitized','archived') GROUP BY d.id ORDER BY n DESC", $vp);
$myTasks = (int)val("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status='open'", [$_SESSION['uid']]);
page_top('Dashboard');
?>
<div class="grid">
<?php foreach ($stats as $k => $v): ?><div class="stat"><span><?= e($k) ?></span><strong><?= $v ?></strong></div><?php endforeach; ?>
<div class="stat"><span>My open tasks</span><strong><?= $myTasks ?></strong></div>
</div>
<div class="dashrow">
<div class="card dashmain"><h2>Pending work per division</h2>
<div class="tw"><table><tr><th>Division</th><th>Records not yet digitized</th></tr>
<?php foreach ($pending as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= $p['n'] ?></td></tr><?php endforeach; ?>
<?php if (!$pending): ?><tr><td colspan="2" class="muted">Nothing pending.</td></tr><?php endif; ?>
</table></div></div>
<?php if (can('audit.view')): $recent = all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 6'); ?>
<div class="card recentact"><h2>Recent system activity</h2>
<div class="tw"><table><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($recent as $a): ?><tr><td><?= fmt_dt($a['created_at']) ?></td><td><?= e($a['username']) ?></td><td><?= e($a['action']) ?></td><td><?= e($a['details']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php else: $recent = all('SELECT h.*, r.inventory_no FROM record_history h JOIN records r ON r.id=h.record_id WHERE h.user_id=? ORDER BY h.id DESC LIMIT 6', [$_SESSION['uid']]); ?>
<div class="card recentact"><h2>My recent activity</h2>
<div class="tw"><table><tr><th>When</th><th>Record</th><th>Action</th></tr>
<?php foreach ($recent as $a): ?><tr><td><?= fmt_dt($a['created_at']) ?></td><td><?= e($a['inventory_no']) ?></td><td><?= e($a['action']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php endif; ?>
</div>
<?php page_bottom();