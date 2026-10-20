<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('reports.view');
refresh_overdue();
[$vw, $vp] = visible_where('r');
// Each report: title, headers, SQL, params
$R = [
 'inventory' => ['Physical inventory per division', ['Division', 'Records'], "SELECT d.name, COUNT(*) FROM records r JOIN divisions d ON d.id=r.division_id WHERE $vw GROUP BY d.id ORDER BY d.name", $vp],
 'progress' => ['Total files scanned and remaining', ['Division', 'Total', 'Digitized or archived', 'Remaining'], "SELECT d.name, COUNT(*), SUM(r.status IN ('digitized','archived')), SUM(r.status NOT IN ('digitized','archived')) FROM records r JOIN divisions d ON d.id=r.division_id WHERE $vw GROUP BY d.id ORDER BY d.name", $vp],
 'monthly' => ['Digitization progress by month', ['Month', 'Files uploaded', 'Folders'], "SELECT DATE_FORMAT(f.uploaded_at,'%Y-%m'), COUNT(*), COUNT(DISTINCT f.record_id) FROM digital_files f JOIN records r ON r.id=f.record_id WHERE $vw GROUP BY 1 ORDER BY 1 DESC", $vp],
 'access' => ['Open-access and restricted summary', ['Division', 'Open', 'Restricted'], "SELECT d.name, SUM(r.access_level='open'), SUM(r.access_level='restricted') FROM records r JOIN divisions d ON d.id=r.division_id WHERE $vw GROUP BY d.id ORDER BY d.name", $vp],
 'missing' => ['Missing or damaged records', ['Inventory no.', 'Title', 'Condition', 'Location', 'Division'], "SELECT r.inventory_no, r.title, r.condition_flag, COALESCE(CASE WHEN r.storage_type='document_holder' THEN CONCAT(COALESCE(sc.code, sc.name, 'Holder'), '-', r.storage_no) WHEN r.storage_type IS NOT NULL THEN CONCAT_WS(' / ', IF(r.storage_type='filing_cabinet', 'Filing Cabinet', 'Storage Shelf'), CONCAT('Cabinet ', r.storage_no), sc.name) END, CONCAT_WS(' / ', r.cabinet, r.shelf, r.box, r.folder)), d.name FROM records r JOIN divisions d ON d.id=r.division_id LEFT JOIN storage_categories sc ON sc.id=r.storage_category_id WHERE $vw AND r.condition_flag<>'ok' ORDER BY r.id", $vp],
 'verification' => ['Records pending verification', ['Inventory no.', 'Title', 'Division', 'Waiting since'], "SELECT r.inventory_no, r.title, d.name, (SELECT MAX(created_at) FROM record_history WHERE record_id=r.id) FROM records r JOIN divisions d ON d.id=r.division_id WHERE $vw AND r.status='for_verification' ORDER BY r.id", $vp],
 'custody' => ['Custody and borrowing', ['Slip', 'Folder', 'Borrower', 'Released', 'Due', 'Returned', 'Status'], "SELECT c.slip_no, r.inventory_no, u.full_name, c.released_at, c.due_date, c.returned_at, c.status FROM custody c JOIN records r ON r.id=c.record_id JOIN users u ON u.id=c.borrower_id WHERE $vw ORDER BY c.id DESC", $vp],
 'overdue' => ['Overdue returns', ['Slip', 'Folder', 'Borrower', 'Due', 'Days overdue'], "SELECT c.slip_no, r.inventory_no, u.full_name, c.due_date, DATEDIFF(CURDATE(), c.due_date) FROM custody c JOIN records r ON r.id=c.record_id JOIN users u ON u.id=c.borrower_id WHERE $vw AND c.status='overdue' ORDER BY c.due_date", $vp],
];
$key = get('r', 'inventory');
if (!isset($R[$key])) $key = 'inventory';
[$title, $heads, $sql, $par] = $R[$key];
$rows = array_map('array_values', all($sql, $par));
if (get('export') === 'csv') { audit('report_exported', 'report', $key, count($rows) . ' rows'); csv_download("report-$key.csv", $heads, $rows); }
audit('report_viewed', 'report', $key);
page_top('Reports and analytics');
?>
<form method="get" class="card row noprint"><label>Report <select name="r"><?php foreach ($R as $k => $v): ?><option value="<?= $k ?>" <?= $k === $key ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></label>
<button>Show</button><a class="btn alt" href="reports.php?r=<?= e($key) ?>&export=csv">Export to Excel (CSV)</a><button type="button" class="alt" onclick="window.print()">Save as PDF / print</button></form>
<div class="card tw"><h2><?= e($title) ?></h2><p class="muted">Generated <?= date('M j, Y g:i A') ?> by <?= e($_SESSION['name']) ?></p>
<table><tr><?php foreach ($heads as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e($c ?? '—') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($heads) ?>" class="muted">No data for this report.</td></tr><?php endif; ?></table></div>
<?php page_bottom();