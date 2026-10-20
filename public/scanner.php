<?php
// Backend for the "Use scanner" button. Returns scanner info (GET), or the scanned file itself (POST).
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/scanlib.php';
require_perm('scan.upload');

function jerr(string $m, int $code = 400) { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $m]); exit; }
function send_file_bytes(string $data, string $ext, string $name) {
    header('Content-Type: ' . (['pdf' => 'application/pdf', 'png' => 'image/png', 'tif' => 'image/tiff', 'tiff' => 'image/tiff'][$ext] ?? 'image/jpeg'));
    header('Content-Length: ' . strlen($data));
    header('X-Scan-Name: ' . $name);
    header('Cache-Control: no-store');
    echo $data; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $dir = scan_folder();
    $list = array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], scanner_list());
    $files = $dir ? scan_inbox($dir) : [];
    echo json_encode(['ok' => true, 'scanners' => $list, 'folder' => $dir !== null, 'folder_set' => trim((string)setting('scan_folder', '')) !== '',
        'files' => array_map(fn($f) => ['name' => $f['name'], 'size' => $f['size'], 'time' => $f['time']], $files),
        'paper' => array_map(fn($p) => $p[0], SCAN_PAPER), 'dpi' => (int)setting('scan_dpi', '200')]);
    exit;
}

$act = post('action');
session_write_close();   // a scan can take a minute; do not block the user's other pages
set_time_limit(300);

if ($act === 'scan') {
    $list = scanner_list(); $s = $list[(int)post('scanner')] ?? null;
    if (!$s) jerr('Choose a scanner.');
    $res = escl_scan($s['base'], ['source' => post('source'), 'color' => post('color'), 'dpi' => (int)post('dpi'), 'paper' => post('paper'), 'format' => post('format')]);
    if (!$res['ok']) jerr($res['msg'], 502);
    session_start(); audit('scanner_used', 'scanner', null, $s['name'] . ', ' . strlen($res['data']) . ' bytes'); session_write_close();
    send_file_bytes($res['data'], $res['ext'], 'scan-' . date('Ymd-His') . '.' . $res['ext']);
}

if ($act === 'folder_get') {
    $dir = scan_folder(); if (!$dir) jerr('The scan folder is not set up or cannot be read by the server.');
    $name = basename(post('name'));
    $p = realpath($dir . DIRECTORY_SEPARATOR . $name);
    if ($name === '' || $name[0] === '.' || !$p || dirname($p) !== $dir || !is_file($p)) jerr('That file is no longer in the scan folder.', 404);
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, array_map('trim', explode(',', (string)setting('allowed_ext', 'pdf'))), true)) jerr('File type not allowed.');
    $data = file_get_contents($p);
    if ($data === false) jerr('Could not read the file.', 500);
    // Move it out of the inbox so it is not picked twice; it stays on disk in _imported
    $done = $dir . DIRECTORY_SEPARATOR . '_imported';
    if (is_dir($done) || @mkdir($done, 0750)) @rename($p, $done . DIRECTORY_SEPARATOR . date('Ymd-His') . '_' . $name);
    session_start(); audit('scan_folder_import', 'file', null, $name); session_write_close();
    send_file_bytes($data, $ext, $name);
}
jerr('Unknown request.');