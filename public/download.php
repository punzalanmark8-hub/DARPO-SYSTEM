<?php
// Protected file delivery: the only way to reach scanned files. Files live outside the web root.
require __DIR__ . '/../app/bootstrap.php';
$f = one('SELECT f.*, r.access_level, r.division_id, r.inventory_no FROM digital_files f JOIN records r ON r.id=f.record_id WHERE f.id=?', [(int)get('id')]);
if (!$f || !can_view_record($f)) {
    if ($f) audit('access_denied', 'file', $f['id'], 'restricted file');
    http_response_code($f ? 403 : 404); exit('File not available.');
}
$base = realpath(cfg()['paths']['uploads']);
$path = realpath($base . '/' . $f['stored_name']);
if (!$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { http_response_code(404); exit('File missing from storage.'); }
$inlineOk = in_array($f['mime'], ['application/pdf', 'image/png', 'image/jpeg'], true);
$inline = get('mode') === 'view' && $inlineOk;
audit($inline ? 'file_viewed' : 'file_downloaded', 'file', $f['id'], $f['inventory_no'] . ' v' . $f['version']);
$name = preg_replace('/[^\w.\- ]/', '_', $f['original_name']);
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Cache-Control: private, no-store');
readfile($path);
