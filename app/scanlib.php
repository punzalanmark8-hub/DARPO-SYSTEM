<?php
// Scanner support: network scanners (eSCL / AirScan, supported by most Epson network models) and a "scan to folder" inbox
// for USB scanners (Epson Scan 2, Epson ScanSmart or Document Capture Pro saving into a shared folder).

const SCAN_PAPER = [   // width x height in 1/300 inch
    'a4' => ['A4', 2480, 3507], 'letter' => ['Letter (8.5 x 11 in)', 2550, 3300],
    'long' => ['Long bond (8.5 x 13 in)', 2550, 3900], 'legal' => ['Legal (8.5 x 14 in)', 2550, 4200],
];

// Setting "scanners": one per line, "Name | address". The address can be just an IP, or a full eSCL URL.
function scanner_list(): array {
    $out = [];
    foreach (preg_split('/\R/', (string)setting('scanners', '')) as $i => $line) {
        $line = trim($line); if ($line === '') continue;
        [$name, $addr] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
        if ($addr === '') { $addr = $name; $name = 'Scanner ' . (count($out) + 1); }
        if (!preg_match('~^https?://~i', $addr)) $addr = 'http://' . $addr;
        $p = parse_url($addr);
        if (!$p || empty($p['host'])) continue;
        $path = rtrim($p['path'] ?? '', '/');
        if ($path === '') $path = '/eSCL';
        $base = strtolower($p['scheme']) . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . $path;
        $out[] = ['id' => count($out), 'name' => mb_substr($name, 0, 60), 'base' => $base];
    }
    return $out;
}

function scan_folder(): ?string {
    $f = trim((string)setting('scan_folder', ''));
    if ($f === '') return null;
    $r = realpath($f);
    return ($r && is_dir($r) && is_readable($r)) ? $r : null;
}

// Minimal HTTP client for the scanner. Returns ['code' => int, 'headers' => [lowercase => value], 'body' => string, 'err' => string].
function escl_http(string $method, string $url, ?string $body = null, int $timeout = 30): array {
    if (!function_exists('curl_init')) return ['code' => 0, 'headers' => [], 'body' => '', 'err' => 'The PHP curl extension is not enabled (enable extension=curl in php.ini).'];
    $h = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,   // scanners use self-signed certificates on the local network
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$h) { $p = explode(':', $line, 2); if (count($p) === 2) $h[strtolower(trim($p[0]))] = trim($p[1]); return strlen($line); },
    ]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']); }
    $resp = curl_exec($ch);
    $err = $resp === false ? curl_error($ch) : '';
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['code' => $code, 'headers' => $h, 'body' => $resp === false ? '' : (string)$resp, 'err' => $err];
}

// What the scanner can do. Returns ['ok' => bool, 'msg' => string, 'pdf' => bool, 'feeder' => bool, 'platen' => bool]
function escl_caps(string $base): array {
    $r = escl_http('GET', $base . '/ScannerCapabilities', null, 10);
    if ($r['code'] !== 200) {
        $why = $r['err'] ?: 'HTTP ' . $r['code'];
        return ['ok' => false, 'msg' => "Cannot reach the scanner ($why). Check that it is on, connected to the network, and that the address is correct.", 'pdf' => false, 'jpeg' => false, 'feeder' => false, 'platen' => false];
    }
    $x = $r['body'];
    return ['ok' => true, 'msg' => '', 'pdf' => (bool)preg_match('~application/pdf~i', $x), 'jpeg' => (bool)preg_match('~image/jpeg~i', $x), 'feeder' => stripos($x, 'Adf') !== false, 'platen' => stripos($x, 'Platen') !== false];
}

// Runs one scan. $opt: source (platen|feeder), color (color|gray), dpi, paper, format ('jpeg' = ask for a picture, used when the pages of
// one file are collected one by one). Returns ['ok','msg','data','ext'].
function escl_scan(string $base, array $opt): array {
    $fail = fn(string $m) => ['ok' => false, 'msg' => $m, 'data' => '', 'ext' => ''];
    $caps = escl_caps($base);
    if (!$caps['ok']) return $fail($caps['msg']);
    $feeder = ($opt['source'] ?? '') === 'feeder';
    if ($feeder && !$caps['feeder']) return $fail('This scanner has no automatic document feeder. Use the flatbed glass.');
    // Pictures can always be joined into one PDF by plain PHP; separate PDFs need pdfunite. So the feeder, and any scan that is one
    // page of a multi-page file, asks the scanner for JPEG when it offers it.
    $wantJpeg = $caps['jpeg'] && ($feeder || ($opt['format'] ?? '') === 'jpeg');
    $fmt = ($caps['pdf'] && !$wantJpeg) ? 'application/pdf' : 'image/jpeg';
    $paper = SCAN_PAPER[$opt['paper'] ?? 'a4'] ?? SCAN_PAPER['a4'];
    $dpi = (int)($opt['dpi'] ?? 200); if (!in_array($dpi, [100, 150, 200, 300, 400, 600], true)) $dpi = 200;
    $mode = ($opt['color'] ?? '') === 'gray' ? 'Grayscale8' : 'RGB24';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<scan:ScanSettings xmlns:pwg="http://www.pwg.org/schemas/2010/12/sm" xmlns:scan="http://schemas.hp.com/imaging/escl/2011/05/03">'
        . '<pwg:Version>2.0</pwg:Version><pwg:ScanRegions><pwg:ScanRegion><pwg:ContentRegionUnits>escl:ThreeHundredthsOfInches</pwg:ContentRegionUnits>'
        . "<pwg:Height>{$paper[2]}</pwg:Height><pwg:Width>{$paper[1]}</pwg:Width><pwg:XOffset>0</pwg:XOffset><pwg:YOffset>0</pwg:YOffset></pwg:ScanRegion></pwg:ScanRegions>"
        . '<scan:InputSource>' . ($feeder ? 'Feeder' : 'Platen') . '</scan:InputSource>'
        . "<scan:ColorMode>$mode</scan:ColorMode><scan:XResolution>$dpi</scan:XResolution><scan:YResolution>$dpi</scan:YResolution>"
        . "<pwg:DocumentFormat>$fmt</pwg:DocumentFormat></scan:ScanSettings>";

    $r = escl_http('POST', $base . '/ScanJobs', $xml, 20);
    if ($r['code'] === 503) return $fail('The scanner is busy or warming up. Wait a few seconds and try again.');
    if ($r['code'] !== 201 || empty($r['headers']['location'])) return $fail('The scanner refused the scan job' . ($r['err'] ? " ({$r['err']})" : " (HTTP {$r['code']})") . '. Check the paper, cover and that nobody is scanning at the printer panel.');
    // Use only the path of the Location header, on the address we were configured with
    $origin = preg_replace('~^(https?://[^/]+).*$~i', '$1', $base);
    $job = $origin . (parse_url($r['headers']['location'], PHP_URL_PATH) ?: '');

    $docs = [];
    for ($n = 0; $n < 100; $n++) {
        $d = null;
        for ($try = 0; $try < 6; $try++) {   // 503 = still scanning or warming up
            $d = escl_http('GET', $job . '/NextDocument', null, 150);
            if ($d['code'] !== 503) break;
            sleep(2);
        }
        if ($d['code'] === 200 && $d['body'] !== '') $docs[] = $d['body'];
        else break;
        if (!$feeder) break;   // flatbed gives exactly one page
    }
    escl_http('DELETE', $job, null, 5);
    if (!$docs) return $fail($feeder ? 'Nothing was scanned. Load the pages face up in the feeder and try again.' : 'Nothing came back from the scanner. Close the cover and try again.');

    $isPdf = $fmt === 'application/pdf';
    // One picture from the flatbed stays a picture (the page collector joins it with the others); feeder sheets always become one PDF
    if (count($docs) === 1 && ($isPdf || !$feeder)) return ['ok' => true, 'msg' => '', 'data' => $docs[0], 'ext' => $isPdf ? 'pdf' : 'jpg'];
    // Several separate pages: combine them into one PDF
    $merged = merge_pages($docs, $isPdf);
    return $merged ? ['ok' => true, 'msg' => '', 'data' => $merged, 'ext' => 'pdf']
                   : $fail('The scanner returned ' . count($docs) . ' separate pages and the server could not join them. Enable the PHP GD extension, or install pdfunite (poppler-utils). Scan fewer pages at a time.');
}

function merge_pages(array $docs, bool $pdf): ?string {
    $tmp = sys_get_temp_dir() . '/darscan_' . bin2hex(random_bytes(6));
    $files = [];
    foreach ($docs as $i => $d) { $f = "{$tmp}_$i." . ($pdf ? 'pdf' : 'jpg'); file_put_contents($f, $d); $files[] = $f; }
    $out = null; $dst = $tmp . '_out.pdf';
    if ($pdf) {
        if (pagelib_merge_pdfs($files, $dst)) $out = (string)file_get_contents($dst);
    } elseif (pagelib_images_to_pdf(array_map(fn($f, $i) => ['path' => $f, 'label' => 'Page ' . ($i + 1)], $files, array_keys($files)), $dst) > 0) {
        $out = (string)file_get_contents($dst);   // plain PHP, no extra software needed
    } elseif (class_exists('Imagick')) {
        try { $im = new Imagick(); foreach ($files as $f) $im->readImage($f); $im->setImageFormat('pdf'); $im->writeImages($dst, true); $out = (string)file_get_contents($dst); } catch (Throwable $e) { error_log((string)$e); }
    }
    foreach ($files as $f) @unlink($f); @unlink($dst);
    return $out ?: null;
}

// Files waiting in the scan folder (newest first). Skips files that are still being written.
function scan_inbox(string $dir): array {
    $allowed = array_map('trim', explode(',', (string)setting('allowed_ext', 'pdf')));
    $out = [];
    foreach (scandir($dir) ?: [] as $n) {
        $p = $dir . DIRECTORY_SEPARATOR . $n;
        if ($n[0] === '.' || !is_file($p)) continue;
        if (!in_array(strtolower(pathinfo($n, PATHINFO_EXTENSION)), $allowed, true)) continue;
        $m = filemtime($p); if (time() - $m < 3) continue;
        $out[] = ['name' => $n, 'size' => filesize($p), 'time' => date('M j, g:i A', $m), 'm' => $m];
    }
    usort($out, fn($a, $b) => $b['m'] <=> $a['m']);
    return array_slice($out, 0, 60);
}