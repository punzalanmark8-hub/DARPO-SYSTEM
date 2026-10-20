<?php
// Edit these values for your server. Do not commit real passwords.
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'dar_erecords', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'],
    'app' => [
        'name'            => 'DAR LAGUNA e-Records',
        'base_url'        => 'http://localhost/dar/public', // used inside QR codes, no trailing slash. Must match the address you open in the browser
        'timezone'        => 'Asia/Manila',
        'idle_minutes'    => 30,   // session timeout
        'max_failed'      => 5,    // failed logins before lock
        'lock_minutes'    => 15,
        'min_password'    => 10,
    ],
    // Keep storage OUTSIDE the web root (public/) so files are never reachable by direct URL.
    'paths' => [
        'uploads' => __DIR__ . '/../storage/uploads',
        'backups' => __DIR__ . '/../storage/backups',
    ],
    // Client requests are e-mailed with PHPMailer through Gmail SMTP.
    'mail' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,           // 587 with 'tls', or 465 with 'ssl'
        'encryption' => 'tls',
        'username'   => 'mbdjc23@gmail.com',            // the Gmail address
        'password'   => 'lxdf lhou rgny zimh',            // the Gmail App Password (16 characters), NOT the normal password
        'from_email' => '',            // leave empty to use the Gmail address
        'from_name'  => 'DAR e-Records',
        'verify_ssl' => true,          // set to false only if you get "certificate verify failed" on local Windows XAMPP
    ],
    // Optional: full paths if mysqldump is not in PATH (e.g. XAMPP on Windows)
    'mysqldump' => 'mysqldump',
    // true = show a readable error message while setting up. Set to false on the live server.
    'debug' => true,
];