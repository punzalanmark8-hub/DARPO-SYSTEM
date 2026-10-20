<?php
// Permission matrix. Admin has everything ('*').
const PERMS = [
    'admin'           => ['*'],
    'records_officer' => ['dashboard', 'inventory.view', 'inventory.edit', 'tracking.update', 'class.request', 'scan.upload', 'custody.view', 'custody.manage',
                          'retention.request', 'reports.view', 'search', 'search.export', 'tasks.assign', 'records.viewall', 'qr'],
    'division_staff'  => ['dashboard', 'inventory.view', 'scan.upload', 'custody.view', 'search', 'qr'],
    'verifier'        => ['dashboard', 'inventory.view', 'verify', 'search', 'records.viewall', 'qr'],
    'viewer'          => ['dashboard', 'search'],
];

function role(): string { return $_SESSION['role'] ?? ''; }
function can(string $perm): bool {
    $list = PERMS[role()] ?? [];
    return in_array('*', $list, true) || in_array($perm, $list, true);
}
function require_login(): void {
    if (empty($_SESSION['uid'])) redirect('login.php');
}
function require_perm(string $perm): void {
    require_login();
    if (!can($perm)) {
        audit('access_denied', 'page', basename($_SERVER['SCRIPT_NAME']), "needs $perm");
        http_response_code(403);
        page_top('Access denied');
        echo '<div class="card"><h2>Access denied</h2><p>Your role does not have permission to open this page.</p></div>';
        page_bottom();
        exit;
    }
}

// Visibility of a single record, and the matching SQL filter for lists.
function can_view_record(array $r): bool {
    if (can('records.viewall') || $r['access_level'] === 'open') return true;
    return role() === 'division_staff' && (int)$r['division_id'] === (int)($_SESSION['division_id'] ?? 0);
}
function visible_where(string $a = 'r'): array {
    if (can('records.viewall')) return ['1=1', []];
    if (role() === 'division_staff') return ["($a.access_level='open' OR $a.division_id=?)", [(int)($_SESSION['division_id'] ?? 0)]];
    return ["$a.access_level='open'", []];
}

function audit(string $action, ?string $entity = null, $entityId = null, ?string $details = null, ?string $username = null): void {
    try {
        insert('audit_logs', [
            'user_id' => $_SESSION['uid'] ?? null, 'username' => $username ?? ($_SESSION['username'] ?? null), 'action' => $action,
            'entity' => $entity, 'entity_id' => $entityId === null ? null : (string)$entityId,
            'details' => $details === null ? null : mb_substr($details, 0, 500), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { error_log('audit failed: ' . $e->getMessage()); }
}

// Returns an error message, or null on success.
function login_user(string $username, string $password): ?string {
    $c = cfg()['app'];
    $u = one('SELECT * FROM users WHERE username=?', [$username]);
    if (!$u) { audit('login_failed', 'user', null, 'unknown username', $username); return 'Invalid username or password.'; }
    if (!$u['is_active']) { audit('login_blocked', 'user', $u['id'], 'inactive account', $username); return 'This account is deactivated. Contact the administrator.'; }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        audit('login_blocked', 'user', $u['id'], 'account locked', $username);
        return 'Account temporarily locked. Try again later.';
    }
    if (!password_verify($password, $u['password_hash'])) {
        $n = (int)$u['failed_attempts'] + 1;
        $lock = $n >= $c['max_failed'] ? date('Y-m-d H:i:s', time() + $c['lock_minutes'] * 60) : null;
        q('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?', [$lock ? 0 : $n, $lock, $u['id']]);
        audit('login_failed', 'user', $u['id'], $lock ? 'account locked after repeated failures' : "attempt $n", $username);
        return 'Invalid username or password.';
    }
    q('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?', [$u['id']]);
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['full_name'], 'role' => $u['role'],
                 'division_id' => $u['division_id'], 'last' => time(), 'csrf' => bin2hex(random_bytes(32))];
    audit('login', 'user', $u['id']);
    return null;
}
function logout_user(): void {
    audit('logout');
    $_SESSION = [];
    session_destroy();
}

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">'; }
function csrf_verify(): void {
    $t = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}
