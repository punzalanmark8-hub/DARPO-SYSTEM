<?php
// Run daily from cron / Windows Task Scheduler:  php cron/daily.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/backup.php';
date_default_timezone_set(cfg()['app']['timezone']);

refresh_overdue();

// Tasks that became overdue today
foreach (all("SELECT * FROM tasks WHERE status='open' AND due_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)") as $t) {
    notify((int)$t['assigned_to'], 'Task overdue: ' . $t['title'], 'tasks.php');
}
// Retention review reminders (weekly on Mondays)
if (date('N') === '1') {
    $days = (int)setting('retention_review_days', '90');
    $n = (int)val("SELECT COUNT(*) FROM records r JOIN retention_schedules rs ON rs.id=r.retention_schedule_id
                   WHERE r.status='archived' AND r.disposition_status='none' AND r.retention_start IS NOT NULL
                   AND DATE_ADD(r.retention_start, INTERVAL (rs.active_years+rs.inactive_years) YEAR) <= DATE_ADD(CURDATE(), INTERVAL $days DAY)");
    if ($n) { notify_role('admin', "$n archived record(s) are due for retention review", 'retention.php'); notify_role('records_officer', "$n archived record(s) are due for retention review", 'retention.php'); }
}
// Pending verification reminder
$pv = (int)val("SELECT COUNT(*) FROM records WHERE status='for_verification'");
if ($pv) notify_roles(['verifier', 'admin'], "$pv folder(s) are waiting for verification", 'verification.php');

// Scheduled backup
$gap = ['daily' => 1, 'weekly' => 7, 'monthly' => 30][setting('backup_frequency', 'daily')] ?? 1;
$last = val("SELECT MAX(created_at) FROM backups WHERE kind='database' AND status='ok'");
if (!$last || strtotime($last) <= time() - ($gap * 86400 - 3600)) {
    [$ok, $msg] = run_db_backup(null); echo "DB backup: " . ($ok ? 'ok' : "FAILED - $msg") . "\n";
    [$ok, $msg] = run_files_backup(null); echo "Files backup: " . ($ok ? 'ok' : "FAILED - $msg") . "\n";
}
echo "Done " . date('c') . "\n";