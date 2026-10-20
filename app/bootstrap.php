<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/pagelib.php';

date_default_timezone_set(cfg()['app']['timezone']);
ini_set('log_errors', '1');
ini_set('display_errors', cfg()['debug'] ? '1' : '0');
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log((string)$e);
    if (($_POST['ajax'] ?? '') === '1') {   // upload dialogs: send the reason back so it can be shown instead of a bare HTTP 500
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Server error: ' . (cfg()['debug'] ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'see the PHP error log.')]);
        exit;
    }
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:640px;margin:60px auto"><h2>System error</h2>';
    echo cfg()['debug'] ? '<p>' . htmlspecialchars($e->getMessage()) . '</p><p>Run <a href="check.php">check.php</a> to diagnose the setup.</p>'
                        : '<p>Something went wrong. Please contact the administrator.</p>';
    exit;
});
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; frame-ancestors 'none'");

if (session_status() === PHP_SESSION_NONE) {
    session_name('dar_sid');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// Idle timeout
if (!empty($_SESSION['uid'])) {
    if (time() - ($_SESSION['last'] ?? 0) > cfg()['app']['idle_minutes'] * 60) {
        $_SESSION = []; session_destroy(); session_start();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('warn', 'You were signed out after a period of inactivity.');
    } elseif (!defined('NO_SESSION_TOUCH')) {
        $_SESSION['last'] = time();   // background polling defines NO_SESSION_TOUCH so it cannot keep an idle session alive
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A body larger than post_max_size makes PHP drop every field, which would otherwise surface as a confusing token error
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $msg = 'The upload is larger than the server limit (post_max_size ' . ini_get('post_max_size') . ', upload_max_filesize ' . ini_get('upload_max_filesize') . '). Use a smaller file or raise these in php.ini.';
        http_response_code(413);
        if (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') !== 'navigate') { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $msg]); }
        else echo htmlspecialchars($msg);
        exit;
    }
    csrf_verify();
}

$script = basename($_SERVER['SCRIPT_NAME']);
if (!defined('PUBLIC_PAGE')) {
    require_login();
    // Force a password change on first login
    if (!in_array($script, ['account.php', 'logout.php'], true) && val('SELECT must_change_password FROM users WHERE id=?', [$_SESSION['uid']])) {
        flash('warn', 'Please set a new password before continuing.');
        redirect('account.php');
    }
    // Storage location tables/columns are created automatically on an existing database (once per login)
    if (empty($_SESSION['storage_ok'])) { ensure_storage_schema(); $_SESSION['storage_ok'] = 1; }
    // Account might have been deactivated while signed in
    if (!val('SELECT is_active FROM users WHERE id=?', [$_SESSION['uid']])) { logout_user(); redirect('login.php'); }
}

// ---- Scan upload (shared by inventory.php and its backend scanning.php) ----
if (!defined('MIME_BY_EXT')) define('MIME_BY_EXT', ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'tif' => ['image/tiff'], 'tiff' => ['image/tiff']]);

// Returns an error message, or null when the uploaded file is acceptable.
if (!function_exists('scan_file_error')) { function scan_file_error(?array $file): ?string {
    if (!$file || !isset($file['error'])) return 'Scan or upload the document first.';
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return 'Scan or upload the document first.';
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: return 'The file is larger than the server upload limit (upload_max_filesize / post_max_size).';
        default: return 'The upload did not complete. Please try again.';
    }
    if (!is_uploaded_file($file['tmp_name'])) return 'The upload did not complete. Please try again.';
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = array_map('trim', explode(',', (string)setting('allowed_ext', 'pdf')));
    $max = (int)setting('max_upload_mb', '50') * 1048576;
    if (!in_array($ext, $allowed, true) || !isset(MIME_BY_EXT[$ext])) return 'File type not allowed. Allowed: ' . implode(', ', $allowed);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, MIME_BY_EXT[$ext], true)) return 'File content does not match its extension.';
    if ($file['size'] > $max) return 'File is larger than the ' . setting('max_upload_mb', '50') . ' MB limit.';
    return null;
}

} // end scan_file_error

// Stores an already-validated upload as the current scan of a record.
// Returns ['ok' => bool, 'msg' => string, 'pages' => int, 'dupe' => bool, 'version' => int].
if (!function_exists('save_scan')) { function save_scan(int $recordId, string $invNo, array $file, ?int $batchId = null, ?string $reason = null): array {
    $fail = fn(string $m) => ['ok' => false, 'msg' => $m, 'pages' => 0, 'dupe' => false];
    $r = one('SELECT * FROM records WHERE id=?', [$recordId]);
    if (!$r) return $fail('Record not found.');
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $hash = hash_file('sha256', $file['tmp_name']);
    $dupe = (int)val('SELECT COUNT(*) FROM digital_files WHERE sha256=?', [$hash]) > 0;

    $sub = date('Y/m'); $root = cfg()['paths']['uploads']; $dir = $root . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return $fail('The storage folder is not writable.');
    $stored = $sub . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $root . '/' . $stored;
    // A file made by joining several pages (see collect_scan_upload) is not an HTTP upload, so it is renamed instead
    $merged = !empty($file['merged']);
    $moved = $merged ? (@rename($file['tmp_name'], $dest) || (@copy($file['tmp_name'], $dest) && @unlink($file['tmp_name'])))
                     : move_uploaded_file($file['tmp_name'], $dest);
    if (!$moved) return $fail('Could not save the file.');
    @chmod($dest, 0640);
    $pages = ($merged && !empty($file['pages'])) ? (int)$file['pages'] : ($ext === 'pdf' ? pdf_page_count($dest) : 1);

    $current = one('SELECT * FROM digital_files WHERE record_id=? AND is_current=1', [$recordId]);
    $version = $current ? (int)$current['version'] + 1 : 1;
    try {
        db()->beginTransaction();
        if ($current) q('UPDATE digital_files SET is_current=0, replaced_reason=? WHERE id=?', [$reason, $current['id']]);
        $fid = insert('digital_files', ['record_id' => $recordId, 'batch_id' => $batchId ?: null, 'version' => $version,
            'original_name' => mb_substr(basename((string)$file['name']), 0, 255), 'stored_name' => $stored, 'mime' => $mime,
            'size_bytes' => $file['size'], 'page_count' => $pages, 'sha256' => $hash, 'scan_flag' => $dupe ? 'duplicate' : 'none',
            'ocr_text' => extract_text($dest, $ext) ?: null, 'uploaded_by' => $_SESSION['uid']]);
        if ($r['status'] === 'inventoried') {
            q("UPDATE records SET status='scanning' WHERE id=?", [$recordId]);
            add_history($recordId, 'status_change', $r['status'], 'scanning', 'Scan uploaded');
        }
        add_history($recordId, 'scan_uploaded', null, null, "v$version, $pages page(s)" . ($dupe ? ', flagged duplicate' : ''));
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        @unlink($dest);
        error_log((string)$e);
        return $fail('Database error while saving the scan.');
    }
    audit('file_uploaded', 'file', $fid, $invNo . " v$version");
    return ['ok' => true, 'msg' => '', 'pages' => $pages, 'dupe' => $dupe, 'version' => $version];
}
}