<?php
// Backup helpers shared by the Backup page and cron/daily.php
function backup_dir(): string {
    $d = cfg()['paths']['backups'];
    if (!is_dir($d)) mkdir($d, 0750, true);
    return realpath($d);
}
function log_backup(string $file, string $kind, string $path, bool $ok, string $note, ?int $uid): array {
    $size = $ok && is_file($path) ? filesize($path) : 0;
    $id = insert('backups', ['filename' => $file, 'kind' => $kind, 'size_bytes' => $size, 'sha256' => $ok ? hash_file('sha256', $path) : null,
                             'status' => $ok ? 'ok' : 'failed', 'note' => mb_substr($note, 0, 255), 'created_by' => $uid]);
    audit($ok ? 'backup_created' : 'backup_failed', 'backup', $id, "$kind: $file $note");
    return [$ok, $note ?: 'Backup completed.'];
}
function run_db_backup(?int $uid): array {
    $c = cfg()['db'];
    $name = 'db-' . date('Ymd-His') . '.sql.gz';
    $path = backup_dir() . '/' . $name;
    if (!function_exists('proc_open')) return log_backup($name, 'database', $path, false, 'proc_open is disabled on this server', $uid);
    $errFile = tempnam(sys_get_temp_dir(), 'dumperr');
    $cmd = [cfg()['mysqldump'], '--single-transaction', '--routines', '--triggers', '-h', $c['host'], '-u', $c['user'], $c['name']];
    $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, null, array_merge(getenv() ?: [], ['MYSQL_PWD' => $c['pass']]));
    if (!is_resource($p)) return log_backup($name, 'database', $path, false, 'Could not start mysqldump. Set the full path in config.php', $uid);
    $gz = gzopen($path, 'wb9');
    while (!feof($pipes[1])) { $b = fread($pipes[1], 65536); if ($b !== false && $b !== '') gzwrite($gz, $b); }
    gzclose($gz); fclose($pipes[1]);
    $code = proc_close($p);
    $err = trim((string)@file_get_contents($errFile)); @unlink($errFile);
    $ok = $code === 0 && is_file($path) && filesize($path) > 100;
    if (!$ok && is_file($path)) @unlink($path);
    return log_backup($name, 'database', $path, $ok, $ok ? '' : "mysqldump failed: $err", $uid);
}
function run_files_backup(?int $uid): array {
    $name = 'files-' . date('Ymd-His') . '.zip';
    $path = backup_dir() . '/' . $name;
    if (!class_exists('ZipArchive')) return log_backup($name, 'files', $path, false, 'PHP zip extension is not installed', $uid);
    $base = realpath(cfg()['paths']['uploads']);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return log_backup($name, 'files', $path, false, 'Cannot create the archive', $uid);
    $n = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && $f->getFilename() !== '.htaccess') { $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($base) + 1)); $n++; }
    }
    $zip->close();
    $ok = is_file($path);
    return log_backup($name, 'files', $path, $ok, $ok ? "$n file(s)" : 'Archive was not written', $uid);
}
function verify_backup(int $id): string {
    $b = one('SELECT * FROM backups WHERE id=?', [$id]);
    if (!$b || $b['status'] !== 'ok') return 'n/a';
    $path = backup_dir() . '/' . basename($b['filename']);
    $res = 'failed';
    if (is_file($path) && hash_equals((string)$b['sha256'], hash_file('sha256', $path))) {
        if ($b['kind'] === 'files') { $z = new ZipArchive(); $res = ($z->open($path, ZipArchive::CHECKCONS) === true) ? 'ok' : 'failed'; }
        else { $g = gzopen($path, 'rb'); $okRead = true; while (!gzeof($g)) { if (gzread($g, 65536) === false) { $okRead = false; break; } } gzclose($g); $res = $okRead ? 'ok' : 'failed'; }
    }
    q('UPDATE backups SET last_verified_at=NOW(), verify_result=? WHERE id=?', [$res, $id]);
    audit('backup_verified', 'backup', $id, $res);
    return $res;
}
