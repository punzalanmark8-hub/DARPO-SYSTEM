<?php
// Client requests: staff encode a client's request for scanned documents, the administrator approves it,
// and the approved documents are e-mailed to the client with PHPMailer through Gmail SMTP.
use PHPMailer\PHPMailer\PHPMailer;

const CREQ_MAX_BYTES = 18 * 1048576;   // Gmail allows 25 MB per message and attachments grow about 37% when encoded

function ensure_creq_schema(): void {
    try {
        q("CREATE TABLE IF NOT EXISTS client_requests(
            id INT AUTO_INCREMENT PRIMARY KEY, request_no VARCHAR(30) NOT NULL UNIQUE,
            client_name VARCHAR(150) NOT NULL, client_email VARCHAR(150) NOT NULL, client_org VARCHAR(150) NULL, purpose VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','sent','rejected') NOT NULL DEFAULT 'pending',
            requested_by INT NOT NULL, division_id INT NULL, decided_by INT NULL, decided_at DATETIME NULL, decision_note VARCHAR(255) NULL,
            sent_at DATETIME NULL, send_error VARCHAR(500) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY(status), KEY(requested_by)) ENGINE=InnoDB");
        q("CREATE TABLE IF NOT EXISTS client_request_files(
            id INT AUTO_INCREMENT PRIMARY KEY, request_id INT NOT NULL, file_id BIGINT NOT NULL, record_id INT NOT NULL,
            UNIQUE KEY uq_creq_file(request_id,file_id), KEY(request_id)) ENGINE=InnoDB");
    } catch (Throwable $e) { error_log('ensure_creq_schema: ' . $e->getMessage()); }
}

function creq_fmt_size(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB'; }

function creq_status_badge(string $s): string {
    return ['pending' => badge('Pending approval', 's-for_verification'), 'approved' => badge('Approved, not yet sent', 's-scanning'),
            'sent' => badge('Sent', 'ok'), 'rejected' => badge('Rejected', 'bad')][$s] ?? badge($s);
}

// What is missing from the Gmail / PHPMailer setup (empty array = ready).
function creq_mail_problems(): array {
    $m = cfg()['mail'] ?? [];
    $p = [];
    foreach (['PHPMailer', 'SMTP', 'Exception'] as $f)
        if (!is_file(__DIR__ . "/lib/PHPMailer/src/$f.php")) { $p[] = 'PHPMailer is not installed: copy PHPMailer.php, SMTP.php and Exception.php into app/lib/PHPMailer/src/.'; break; }
    if (empty($m['username']) || empty($m['password'])) $p[] = 'Gmail is not set up: fill in the "mail" section of app/config.php (Gmail address and App Password).';
    if (!extension_loaded('openssl')) $p[] = 'The PHP openssl extension is off: enable extension=openssl in php.ini.';
    return $p;
}

// Turns the common SMTP errors into something the administrator can act on.
function creq_explain(string $m): string {
    if (stripos($m, 'authenticate') !== false || stripos($m, 'Username and Password not accepted') !== false)
        $m .= ' Gmail rejected the login. Use a 16-character App Password (not the normal Gmail password) created in the SAME Gmail account with 2-Step Verification on, and set "username" to the full Gmail address.';
    elseif (stripos($m, 'certificate') !== false)
        $m .= ' The server cannot verify Gmail\'s certificate. On local XAMPP set \'verify_ssl\' => false in app/config.php.';
    elseif (stripos($m, 'connect') !== false)
        $m .= ' Check the internet connection, that port 587 is not blocked by a firewall, and that extension=openssl is enabled.';
    return mb_substr($m, 0, 490);
}

function creq_new_mailer(): PHPMailer {
    if ($p = creq_mail_problems()) throw new RuntimeException($p[0]);
    $d = __DIR__ . '/lib/PHPMailer/src/';
    require_once $d . 'Exception.php'; require_once $d . 'PHPMailer.php'; require_once $d . 'SMTP.php';
    $m = cfg()['mail'];
    $enc = ($m['encryption'] ?? 'tls') === 'ssl' ? 'ssl' : 'tls';
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $m['host'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = trim($m['username']);
    $mail->Password = str_replace(' ', '', (string)$m['password']);   // Google shows App Passwords with spaces
    $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)($m['port'] ?? ($enc === 'ssl' ? 465 : 587));
    $mail->Timeout = 25;
    $mail->CharSet = 'UTF-8';
    if (array_key_exists('verify_ssl', $m) && !$m['verify_ssl'])   // only for servers (some Windows XAMPP) that cannot verify Gmail's certificate
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->setFrom(($m['from_email'] ?? '') ?: $m['username'], ($m['from_name'] ?? '') ?: cfg()['app']['name']);
    return $mail;
}

// E-mails every file of the request to the client. Returns [ok, error message].
function creq_send(array $req): array {
    @set_time_limit(180);
    try {
        $files = all('SELECT f.*, r.inventory_no, r.title FROM client_request_files c JOIN digital_files f ON f.id=c.file_id JOIN records r ON r.id=f.record_id WHERE c.request_id=? ORDER BY r.inventory_no, f.id', [(int)$req['id']]);
        if (!$files) return [false, 'This request has no documents.'];
        $base = realpath(cfg()['paths']['uploads']);
        $mail = creq_new_mailer();
        $mail->addAddress($req['client_email'], $req['client_name']);
        $mail->Subject = cfg()['app']['name'] . ': requested document(s) ' . $req['request_no'];
        $rows = ''; $plain = '';
        foreach ($files as $f) {
            $path = $base ? realpath($base . '/' . $f['stored_name']) : false;
            if (!$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) throw new RuntimeException('A file is missing from storage (' . $f['inventory_no'] . ').');
            $mail->addAttachment($path, preg_replace('/[^\w.\- ]/', '_', $f['inventory_no'] . '_' . $f['original_name']));
            $rows .= '<li>' . e($f['inventory_no']) . ' &ndash; ' . e($f['title']) . '</li>';
            $plain .= '- ' . $f['inventory_no'] . ' - ' . $f['title'] . "\n";
        }
        $app = e(cfg()['app']['name']);
        $mail->isHTML(true);
        $mail->Body = '<p>Good day ' . e($req['client_name']) . ',</p><p>As approved, the document(s) you requested (reference <strong>' . e($req['request_no']) . '</strong>) are attached to this e-mail:</p><ul>' . $rows . '</ul>'
            . '<p>Purpose of request: ' . e($req['purpose']) . '</p><p>These documents are for the stated purpose only. If you received this message by mistake, please delete it and let us know.</p><p>Thank you,<br>' . $app . '</p>';
        $mail->AltBody = "Good day {$req['client_name']},\n\nAs approved, the document(s) you requested (reference {$req['request_no']}) are attached:\n\n$plain\nPurpose of request: {$req['purpose']}\n\nThese documents are for the stated purpose only. If you received this message by mistake, please delete it.\n\nThank you,\n" . cfg()['app']['name'];
        $mail->send();
        return [true, ''];
    } catch (Throwable $e) {
        error_log('creq_send: ' . $e->getMessage());
        return [false, creq_explain($e->getMessage())];
    }
}

function creq_send_test(string $to): array {
    try {
        $mail = creq_new_mailer();
        $mail->addAddress($to);
        $mail->Subject = cfg()['app']['name'] . ': test e-mail';
        $mail->Body = 'This is a test message. Gmail SMTP is working.';
        $mail->send();
        return [true, ''];
    } catch (Throwable $e) { return [false, creq_explain($e->getMessage())]; }
}