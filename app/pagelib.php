<?php
// Multi-page files: one physical file can have several pages. The pages are uploaded together (field "scan[]")
// and stored as ONE PDF, so the record keeps one digital file with a page count.
//
// Images (JPG, PNG, GIF, BMP, WEBP) are joined with plain PHP: no pdfunite, no Imagick. PNG and the other
// non-JPEG formats need the GD extension (bundled with XAMPP). Mixing PDF files with other pages needs pdfunite
// (poppler-utils) or Ghostscript on the server.

if (!defined('SCAN_MAX_PAGES')) define('SCAN_MAX_PAGES', 100);

// How many pages one file may have: PHP drops uploads beyond max_file_uploads (default 20), so the page limit follows it.
function scan_page_limit(): int {
    $n = (int)ini_get('max_file_uploads');
    return $n > 0 ? min($n, SCAN_MAX_PAGES) : 20;
}

function pagelib_mem_limit(): int {
    $v = trim((string)ini_get('memory_limit'));
    if ($v === '' || $v === '-1') return -1;
    $n = (int)$v; $u = strtolower(substr($v, -1));
    if ($u === 'g') $n *= 1073741824; elseif ($u === 'm') $n *= 1048576; elseif ($u === 'k') $n *= 1024;
    return $n;
}

// GD keeps about 4 bytes per pixel and we hold two copies while converting.
function pagelib_fits_memory(int $w, int $h): bool {
    $lim = pagelib_mem_limit();
    return $lim < 0 || ($w * $h * 9 + memory_get_usage()) < $lim * 0.9;
}

// Turns one image file into JPEG page(s) ready to embed. Returns ['ok'=>true,'pages'=>[['data','w','h','cs'],...]] or ['ok'=>false,'msg'=>...].
function pagelib_prepare_image(string $path): array {
    $fail = fn(string $m) => ['ok' => false, 'msg' => $m];
    $info = @getimagesize($path);
    if (!$info || $info[0] < 1 || $info[1] < 1) return $fail('This file is not a readable image.');
    [$w, $h, $type] = $info;
    $isTiff = $type === IMAGETYPE_TIFF_II || $type === IMAGETYPE_TIFF_MM;

    $orient = 1;
    if ($type === IMAGETYPE_JPEG) {
        if (function_exists('exif_read_data')) { $ex = @exif_read_data($path); $orient = (int)($ex['Orientation'] ?? 1); }
        $ch = (int)($info['channels'] ?? 3);
        if ($orient <= 1 && ($ch === 1 || $ch === 3)) {   // normal JPEG: embed as is, no quality loss, no memory use
            $data = @file_get_contents($path);
            if ($data === false) return $fail('Could not read this image.');
            return ['ok' => true, 'pages' => [['data' => $data, 'w' => $w, 'h' => $h, 'cs' => $ch === 1 ? 'DeviceGray' : 'DeviceRGB']]];
        }
    }

    if ($isTiff) {   // GD cannot read TIFF
        if (!class_exists('Imagick')) return $fail('TIFF pages need the PHP imagick extension. Save the page as JPG, PNG or PDF instead.');
        try {
            $out = [];
            $ik = new Imagick($path);
            foreach ($ik as $frame) {
                $frame->setImageFormat('jpeg'); $frame->setImageCompressionQuality(88);
                $frame->setImageBackgroundColor('white');
                try { $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE); } catch (Throwable $e) {}
                $blob = $frame->getImageBlob();
                $fi = @getimagesizefromstring($blob);
                if (!$blob || !$fi) continue;
                $out[] = ['data' => $blob, 'w' => $fi[0], 'h' => $fi[1], 'cs' => ((int)($fi['channels'] ?? 3)) === 1 ? 'DeviceGray' : 'DeviceRGB'];
            }
            $ik->clear();
            return $out ? ['ok' => true, 'pages' => $out] : $fail('Could not read this TIFF.');
        } catch (Throwable $e) { error_log((string)$e); return $fail('Could not read this TIFF.'); }
    }

    if (!function_exists('imagecreatefromstring')) return $fail('Joining image pages needs the PHP GD extension (enable extension=gd in php.ini and restart Apache).');
    if (!pagelib_fits_memory($w, $h)) return $fail("This image is too large to process ($w x $h px). Scan at 200 dpi or lower.");
    $raw = @file_get_contents($path);
    $im = $raw === false ? false : @imagecreatefromstring($raw);
    unset($raw);
    if (!$im) return $fail('Could not read this image.');

    // Phone photos carry their rotation in EXIF; a PDF does not, so apply it here.
    if ($orient > 1) {
        if (in_array($orient, [2, 4, 5, 7], true)) imageflip($im, $orient === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        $deg = [3 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orient] ?? 0;
        if ($deg) { $rot = imagerotate($im, $deg, imagecolorallocate($im, 255, 255, 255)); if ($rot) { imagedestroy($im); $im = $rot; } }
    }
    $W = imagesx($im); $H = imagesy($im);
    $flat = imagecreatetruecolor($W, $H);   // JPEG has no transparency: put the page on white
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $im, 0, 0, 0, 0, $W, $H);
    imagedestroy($im);
    ob_start(); imagejpeg($flat, null, 88); $data = (string)ob_get_clean();
    imagedestroy($flat);
    if ($data === '') return $fail('Could not convert this image.');
    return ['ok' => true, 'pages' => [['data' => $data, 'w' => $W, 'h' => $H, 'cs' => 'DeviceRGB']]];
}

// Page size in points: the short side is A4 wide (595 pt), the long side follows the picture's shape.
function pagelib_page_size(int $w, int $h): array {
    $s = 595.28;
    [$pw, $ph] = $h >= $w ? [$s, $s * $h / $w] : [$s * $w / $h, $s];
    $m = max($pw, $ph);
    if ($m > 14000) { $pw *= 14000 / $m; $ph *= 14000 / $m; }   // PDF viewers cap a page at 14400 pt
    return [$pw, $ph];
}

// Writes a PDF with one page per picture. $items = list of ['path' => file, 'label' => 'Page 3'].
// Pages are written one at a time, so a long document never has to fit in memory. Returns the page count (0 on failure, $err says why).
function pagelib_images_to_pdf(array $items, string $dest, ?string &$err = null): int {
    $fh = @fopen($dest, 'wb');
    if (!$fh) { $err = 'Could not create a temporary file on the server.'; return 0; }
    $off = []; $next = 4; $kids = [];   // object 1 = page tree, 2 = catalog, 3 = info; pages start at 4
    fwrite($fh, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
    foreach ($items as $it) {
        $prep = pagelib_prepare_image($it['path']);
        if (!$prep['ok']) { fclose($fh); @unlink($dest); $err = ($it['label'] ?? 'A page') . ': ' . $prep['msg']; return 0; }
        foreach ($prep['pages'] as $pg) {
            $pid = $next; $cid = $next + 1; $iid = $next + 2; $next += 3;
            [$pw, $ph] = pagelib_page_size($pg['w'], $pg['h']);
            $kids[] = "$pid 0 R";
            $content = sprintf('q %.2F 0 0 %.2F 0 0 cm /Im0 Do Q', $pw, $ph);
            $off[$pid] = ftell($fh);
            fwrite($fh, sprintf("%d 0 obj\n<< /Type /Page /Parent 1 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>\nendobj\n", $pid, $pw, $ph, $iid, $cid));
            $off[$cid] = ftell($fh);
            fwrite($fh, sprintf("%d 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj\n", $cid, strlen($content), $content));
            $off[$iid] = ftell($fh);
            fwrite($fh, sprintf("%d 0 obj\n<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $iid, $pg['w'], $pg['h'], $pg['cs'], strlen($pg['data'])));
            fwrite($fh, $pg['data']);
            fwrite($fh, "\nendstream\nendobj\n");
        }
        unset($prep);
    }
    $n = count($kids);
    if ($n === 0) { fclose($fh); @unlink($dest); $err = 'There were no pages to save.'; return 0; }
    $off[1] = ftell($fh); fwrite($fh, sprintf("1 0 obj\n<< /Type /Pages /Kids [%s] /Count %d >>\nendobj\n", implode(' ', $kids), $n));
    $off[2] = ftell($fh); fwrite($fh, "2 0 obj\n<< /Type /Catalog /Pages 1 0 R >>\nendobj\n");
    $off[3] = ftell($fh); fwrite($fh, sprintf("3 0 obj\n<< /Producer (DAR e-Records) /CreationDate (D:%s) >>\nendobj\n", date('YmdHis')));
    $xref = ftell($fh); $size = $next;
    fwrite($fh, "xref\n0 $size\n0000000000 65535 f \n");
    for ($i = 1; $i < $size; $i++) fwrite($fh, sprintf("%010d 00000 n \n", $off[$i]));
    fwrite($fh, "trailer\n<< /Size $size /Root 2 0 R /Info 3 0 R >>\nstartxref\n$xref\n%%EOF\n");
    fclose($fh);
    return $n;
}

// Joins PDF files with pdfunite (poppler) or Ghostscript when the server has one of them.
function pagelib_merge_pdfs(array $files, string $dest): bool {
    if (!function_exists('exec')) return false;
    $args = implode(' ', array_map('escapeshellarg', $files));
    $gs = (PHP_OS_FAMILY === 'Windows') ? 'gswin64c' : 'gs';
    $cmds = ['pdfunite ' . $args . ' ' . escapeshellarg($dest),
             $gs . ' -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($dest) . ' ' . $args];
    foreach ($cmds as $cmd) {
        @unlink($dest);
        $o = []; $rc = 1;
        @exec($cmd . ' 2>&1', $o, $rc);
        if ($rc === 0 && is_file($dest) && filesize($dest) > 0) return true;
    }
    return false;
}

// Joins validated uploads (in page order) into one PDF in the temp folder.
// Returns ['ok' => bool, 'msg' => string, 'path' => string, 'pages' => int].
function merge_scan_pages(array $list): array {
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'darpages_' . bin2hex(random_bytes(6));
    $made = [];
    $fail = function (string $m) use (&$made) { foreach ($made as $f) @unlink($f); return ['ok' => false, 'msg' => $m, 'path' => '', 'pages' => 0]; };

    // Runs of pictures become one segment each; every PDF is its own segment.
    $segs = [];
    foreach ($list as $i => $f) {
        $label = 'Page ' . ($i + 1);
        if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) === 'pdf') { $segs[] = ['pdf', $f['tmp_name'], $label]; continue; }
        $last = count($segs) - 1;
        if ($last >= 0 && $segs[$last][0] === 'img') $segs[$last][1][] = ['path' => $f['tmp_name'], 'label' => $label];
        else $segs[] = ['img', [['path' => $f['tmp_name'], 'label' => $label]]];
    }

    $dest = $tmp . '_out.pdf';
    if (count($segs) === 1 && $segs[0][0] === 'img') {   // the normal case: all pages are pictures
        $made[] = $dest; $err = null;
        $n = pagelib_images_to_pdf($segs[0][1], $dest, $err);
        return $n ? ['ok' => true, 'msg' => '', 'path' => $dest, 'pages' => $n] : $fail($err ?: 'Could not combine the pages.');
    }

    $parts = []; $total = 0;
    foreach ($segs as $k => $s) {
        if ($s[0] === 'pdf') { $parts[] = $s[1]; $total += pdf_page_count($s[1]); continue; }
        $p = $tmp . "_$k.pdf"; $made[] = $p; $err = null;
        $n = pagelib_images_to_pdf($s[1], $p, $err);
        if (!$n) return $fail($err ?: 'Could not combine the pages.');
        $parts[] = $p; $total += $n;
    }
    $made[] = $dest;
    if (!pagelib_merge_pdfs($parts, $dest))
        return $fail('Joining PDF files with other pages needs pdfunite (poppler-utils) or Ghostscript on the server, and neither was found. Upload every page as an image (JPG or PNG), or upload one PDF.');
    foreach ($made as $f) if ($f !== $dest) @unlink($f);
    return ['ok' => true, 'msg' => '', 'path' => $dest, 'pages' => $total];
}

// Reads the pages posted as $_FILES[$field] (one file or many), validates each, and returns ONE file ready for save_scan().
// Returns ['ok' => bool, 'msg' => string, 'file' => ?array, 'pages' => int]. A merged file has 'merged' => true and lives in the temp folder.
function collect_scan_upload(string $field = 'scan'): array {
    $fail = fn(string $m) => ['ok' => false, 'msg' => $m, 'file' => null, 'pages' => 0];
    $raw = $_FILES[$field] ?? null;
    if (!$raw) return $fail('Scan or upload the document first.');
    $list = [];
    if (is_array($raw['name'])) {
        foreach ($raw['name'] as $i => $n) $list[] = ['name' => $n, 'type' => $raw['type'][$i] ?? '', 'tmp_name' => $raw['tmp_name'][$i] ?? '', 'error' => $raw['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $raw['size'][$i] ?? 0];
    } else $list[] = $raw;
    $list = array_values(array_filter($list, fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    if (!$list) return $fail('Scan or upload the document first.');

    // PHP silently drops files beyond max_file_uploads, so compare with what the browser said it sent.
    $sent = (int)($_POST['page_count'] ?? 0);
    if ($sent > count($list))
        return $fail('Only ' . count($list) . " of $sent pages reached the server (PHP max_file_uploads is " . ini_get('max_file_uploads') . '). Add fewer pages, or ask the administrator to raise max_file_uploads in php.ini.');
    if (count($list) > SCAN_MAX_PAGES) return $fail('A file can have up to ' . SCAN_MAX_PAGES . ' pages.');

    foreach ($list as $i => $f) {
        if ($e = scan_file_error($f)) return $fail(count($list) > 1 ? 'Page ' . ($i + 1) . ' (' . mb_substr(basename((string)$f['name']), 0, 60) . '): ' . $e : $e);
    }
    if (count($list) === 1) return ['ok' => true, 'msg' => '', 'file' => $list[0], 'pages' => 0];   // single file: stored exactly as uploaded

    @set_time_limit(180);
    $m = merge_scan_pages($list);
    if (!$m['ok']) return $fail($m['msg']);
    $size = (int)filesize($m['path']);
    $max = (int)setting('max_upload_mb', '50') * 1048576;
    if ($size > $max) {
        @unlink($m['path']);
        return $fail('The combined file is ' . round($size / 1048576, 1) . ' MB, over the ' . setting('max_upload_mb', '50') . ' MB limit. Scan in grayscale or at a lower resolution.');
    }
    return ['ok' => true, 'msg' => '', 'pages' => $m['pages'], 'file' => [
        'name' => 'scan-' . date('Ymd-His') . '.pdf', 'type' => 'application/pdf', 'tmp_name' => $m['path'],
        'error' => UPLOAD_ERR_OK, 'size' => $size, 'merged' => true, 'pages' => $m['pages'],
    ]];
}

// Deletes the temporary combined file if save_scan() did not take it.
function discard_scan_upload(?array $file): void {
    if ($file && !empty($file['merged']) && !empty($file['tmp_name']) && is_file($file['tmp_name'])) @unlink($file['tmp_name']);
}