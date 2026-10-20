<?php
// Setup diagnostic. Delete this file (or set debug to false) when the system is live.
error_reporting(E_ALL); ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
$rows = array();
function row($label, $ok, $msg) { global $rows; $rows[] = array($label, $ok, $msg); }
row('PHP version', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION . ' (needs 7.4 or newer; 8.1+ recommended)');
$ini = php_ini_loaded_file() ?: 'php.ini';
foreach (array('pdo_mysql', 'mbstring', 'fileinfo', 'zip', 'zlib') as $x) {
    row("Extension $x", extension_loaded($x), extension_loaded($x) ? 'loaded' : "Open $ini, remove the ; in front of extension=$x, save, then restart Apache in the XAMPP Control Panel.");
}
$cfgFile = __DIR__ . '/../app/config.php';
$cfg = is_file($cfgFile) ? include $cfgFile : null;
row('app/config.php', is_array($cfg), is_array($cfg) ? 'found' : 'missing: check that the folder structure was not changed');
if (is_array($cfg)) {
    $c = $cfg['db'];
    $port = (int)($c['port'] ?? 3306);
    try {
        $pdo = new PDO("mysql:host={$c['host']};port=$port;dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        row('Database connection', true, "connected to {$c['name']} ({$c['host']}:$port)");
        $n = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=" . $pdo->quote($c['name']))->fetchColumn();
        row('Tables imported', $n >= 19, "$n tables found (expected 19). Import database/schema.sql if fewer.");
        $u = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        row('Admin account', $u > 0, "$u user(s)");
    } catch (Exception $e) {
        $m = $e->getMessage();
        if (stripos($m, 'socket address') !== false || stripos($m, '10048') !== false) {
            $hint = "Windows says the network port is already in use or exhausted. Fix: (1) restart MySQL in the XAMPP Control Panel, and if that fails reboot the PC; (2) make sure only ONE MySQL is running (Task Manager > Services: stop any other MySQL/MariaDB service); (3) if another program owns port $port, either stop it or change MySQL's port in XAMPP (my.ini) and set the same 'port' in app/config.php.";
        } elseif (stripos($m, 'Unknown database') !== false) {
            $hint = "MySQL is running but the database does not exist. Open phpMyAdmin and import database/schema.sql.";
        } elseif (stripos($m, 'Access denied') !== false) {
            $hint = "Wrong MySQL user or password. Check 'user' and 'pass' in app/config.php (XAMPP default: root with an empty password).";
        } elseif (stripos($m, 'refused') !== false || stripos($m, '2002') !== false) {
            $hint = "MySQL is not reachable on {$c['host']}:$port. Start MySQL in the XAMPP Control Panel and check the port in app/config.php.";
        } else {
            $hint = "Start MySQL in XAMPP, check user/password/port in app/config.php, and import database/schema.sql.";
        }
        row('Database connection', false, $m . ' | ' . $hint);
        // Probe common MySQL ports so you can see where (or whether) MySQL is really listening.
        $open = array();
        foreach (array(3306, 3307, 3308, 3309, 3310) as $tp) {
            $fp = @fsockopen($c['host'], $tp, $en, $es, 1);
            if ($fp) { $open[] = $tp; fclose($fp); }
        }
        row('MySQL port scan', count($open) > 0 && in_array($port, $open, true),
            $open ? 'Something is listening on port(s): ' . implode(', ', $open) . ". app/config.php uses $port." . (in_array($port, $open, true) ? '' : " Set 'port' to the one MySQL uses.")
                  : "Nothing answered on ports 3306-3310 at {$c['host']}. MySQL is stopped or crashed: open the XAMPP Control Panel > MySQL > Logs and read the last lines.");
    }
    foreach (array('uploads', 'backups') as $k) {
        $p = $cfg['paths'][$k];
        row("storage/$k writable", is_dir($p) && is_writable($p), is_dir($p) ? (is_writable($p) ? 'ok' : 'not writable') : 'folder missing');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $actual = $https . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $conf = rtrim($cfg['app']['base_url'], '/');
    $same = strcasecmp($conf, $actual) === 0;
    row('base_url', $same, $same ? $conf : "config.php says $conf but you opened this page at $actual. Set 'base_url' => '$actual' in app/config.php so QR codes point to the right address.");
}

// ---- Upload checks ----
$bp = __DIR__ . '/../app/bootstrap.php'; $bs = is_file($bp) ? file_get_contents($bp) : '';
row('app/bootstrap.php has the scan upload fix', strpos($bs, 'function save_scan') !== false && strpos($bs, 'function scan_file_error') !== false, strpos($bs, 'function save_scan') !== false ? 'installed' : 'old version: copy the new bootstrap.php into dar/app (not public).');
row('PHP upload limits', true, 'upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ', memory_limit ' . ini_get('memory_limit') . ' (a scan must be smaller than both of the first two)');
if (isset($pdo)) {
    $need = array('digital_files' => array('record_id', 'batch_id', 'version', 'is_current', 'original_name', 'stored_name', 'mime', 'size_bytes', 'page_count', 'sha256', 'scan_flag', 'replaced_reason', 'ocr_text', 'uploaded_by'),
                  'records' => array('inventory_no', 'qr_token', 'title', 'ref_no', 'division_id', 'section_id', 'category_id', 'doc_year', 'volume', 'cabinet', 'shelf', 'box', 'folder', 'storage_type', 'storage_category_id', 'storage_no', 'custodian_id', 'access_level', 'restriction_basis', 'retention_schedule_id', 'created_by', 'status', 'condition_flag'),
                  'record_history' => array('record_id', 'action', 'from_status', 'to_status', 'remarks', 'user_id'),
                  'audit_logs' => array('user_id', 'username', 'action', 'entity', 'entity_id', 'details', 'ip'),
                  'counters' => array('name', 'yr', 'val'));
    foreach ($need as $t => $cols) {
        try {
            $have = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=" . $pdo->quote($c['name']) . " AND table_name=" . $pdo->quote($t))->fetchAll(PDO::FETCH_COLUMN);
            $miss = array_values(array_diff($cols, array_map('strtolower', $have)));
            row("Table $t columns", !$miss && $have, $miss ? 'missing columns: ' . implode(', ', $miss) . ' (re-import the current database/schema.sql)' : ($have ? 'ok' : 'table missing'));
        } catch (Exception $e) { row("Table $t columns", false, $e->getMessage()); }
    }
    try {
        $ti = $pdo->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=" . $pdo->quote($c['name']))->fetchColumn();
        row('Audit triggers', true, "$ti trigger(s) (2 expected; they only block editing audit rows)");
    } catch (Exception $e) {}
}
$log = ini_get('error_log');
if (!$log) foreach (array('C:/xampp/php/logs/php_error_log', 'C:/xampp/apache/logs/error.log') as $cand) if (is_file($cand)) { $log = $cand; break; }
if ($log && is_readable($log)) {
    $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: array(), -12);
    row('Latest PHP error log lines', true, $log . ":\n" . implode("\n", array_map(function ($l) { return substr($l, 0, 300); }, $lines)));
} else row('PHP error log', true, 'not found here. In XAMPP open xampp/apache/logs/error.log and read the last lines.');
?><!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup check</title>
<body style="font-family:sans-serif;max-width:860px;margin:30px auto;padding:0 16px">
<h2>DARPO LAGUNA e-Records setup check</h2><table cellpadding="8" style="border-collapse:collapse;width:100%">
<?php foreach ($rows as $r): ?><tr style="border-bottom:1px solid #ddd"><td><?= $r[1] ? '&#9989;' : '&#10060;' ?></td><td><b><?= htmlspecialchars($r[0]) ?></b></td><td style="white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars($r[2]) ?></td></tr><?php endforeach; ?>
</table><p><a href="login.php">Go to login</a></p></body>