<?php
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/backup.php';
require_perm('backup.manage');

// Download one backup file (audited)
if (get('download') !== '') {
    $b = one('SELECT * FROM backups WHERE id=? AND status=?', [(int)get('download'), 'ok']);
    $path = $b ? backup_dir() . '/' . basename($b['filename']) : '';
    if (!$b || !is_file($path)) { http_response_code(404); exit('Backup file not found.'); }
    audit('backup_downloaded', 'backup', $b['id'], $b['filename']);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]/', '_', $b['filename']) . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    @set_time_limit(0);
    if ($act === 'db' || $act === 'both') { [$ok, $msg] = run_db_backup((int)$_SESSION['uid']); flash($ok ? 'ok' : 'err', 'Database backup: ' . ($ok ? 'completed.' : $msg)); }
    if ($act === 'files' || $act === 'both') { [$ok, $msg] = run_files_backup((int)$_SESSION['uid']); flash($ok ? 'ok' : 'err', 'Files backup: ' . ($ok ? $msg : $msg)); }
    if ($act === 'verify') { $res = verify_backup((int)post('id')); flash($res === 'ok' ? 'ok' : 'err', 'Backup verification: ' . $res . '.'); }
    redirect('backup.php');
}

$rows = all('SELECT b.*, u.full_name FROM backups b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.id DESC LIMIT 100');
$lastDb = val("SELECT MAX(created_at) FROM backups WHERE kind='database' AND status='ok'");
$lastFiles = val("SELECT MAX(created_at) FROM backups WHERE kind='files' AND status='ok'");
function fmt_size($n): string { $n = (float)$n; foreach (['B', 'KB', 'MB', 'GB'] as $u) { if ($n < 1024 || $u === 'GB') return ($u === 'B' ? (int)$n : number_format($n, 1)) . ' ' . $u; $n /= 1024; } return ''; }
page_top('Backup and recovery');
?>
<div class="note">Backups contain the whole database and every scanned file. Copy them to a separate machine or drive regularly. Scheduled backups run from <code>cron/daily.php</code> (frequency: <?= e(setting('backup_frequency', 'daily')) ?>).</div>
<div class="card"><h2>Run a backup</h2>
<p class="muted">Last database backup: <?= $lastDb ? fmt_dt($lastDb) : 'never' ?>. Last files backup: <?= $lastFiles ? fmt_dt($lastFiles) : 'never' ?>.</p>
<form method="post" class="row"><?= csrf_field() ?>
  <button name="action" value="both">Back up database and files</button>
  <button name="action" value="db" class="alt">Database only</button>
  <button name="action" value="files" class="alt">Files only</button></form></div>
<div class="card"><h2>Backup history</h2><div class="tw"><table>
<tr><th>When</th><th>Type</th><th>File</th><th>Size</th><th>Status</th><th>By</th><th>Last verified</th><th></th></tr>
<?php foreach ($rows as $b): ?><tr>
  <td><?= fmt_dt($b['created_at']) ?></td><td><?= e($b['kind']) ?></td><td><?= e($b['filename']) ?><?= $b['note'] ? '<br><span class="muted">' . e($b['note']) . '</span>' : '' ?></td>
  <td><?= $b['status'] === 'ok' ? fmt_size($b['size_bytes']) : '—' ?></td><td><?= badge($b['status'], $b['status'] === 'ok' ? 'ok' : 'bad') ?></td><td><?= e($b['full_name'] ?? 'Scheduled') ?></td>
  <td><?= $b['last_verified_at'] ? fmt_dt($b['last_verified_at']) . ' ' . badge((string)$b['verify_result'], $b['verify_result'] === 'ok' ? 'ok' : 'bad') : 'Not yet' ?></td>
  <td><?php if ($b['status'] === 'ok'): ?><a href="backup.php?download=<?= (int)$b['id'] ?>">Download</a>
    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="sm alt">Verify</button></form><?php endif; ?></td>
</tr><?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No backups yet.</td></tr><?php endif; ?></table></div></div>
<div class="card"><h2>How to restore</h2><ol>
  <li>Stop users from using the system (stop Apache in the XAMPP Control Panel).</li>
  <li>Database: unzip <code>db-....sql.gz</code>, then import it, for example <code>mysql -u root -p dar_erecords &lt; db-....sql</code>, or use phpMyAdmin Import.</li>
  <li>Files: extract <code>files-....zip</code> into <code>storage/uploads</code>, keeping the folder structure.</li>
  <li>Start Apache, sign in, and open a few records to confirm their scans open.</li>
  <li>Use Verify above after copying a backup elsewhere to confirm it is intact.</li></ol></div>
<?php page_bottom();