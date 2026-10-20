<?php
// ---------------------------------------------------------------------------------------------
// Module access: which sidebar modules a role, a division or one user can open.
//
// Every module has a normal default (the role's permissions in PERMS, auth.php). The administrator
// can override that per role, per division or per user (public/modules.php). The most specific
// override wins:   user  >  division  >  role override  >  role default.
//   Allowed = open the module even if the role normally lacks it
//   Blocked = hide and refuse the module even if the role normally has it
// Administrators always keep everything. Dashboard stays on for everybody, and the administration
// modules (Users, Modules, Audit trail, Settings, Backup) can never be handed to other accounts.
// ---------------------------------------------------------------------------------------------

function module_registry(): array {
    // key => label, file, perm (default rule), fixed ('core' = always on, 'admin' = administrators only), icon (24x24 svg paths)
    return [
        'index'          => ['label' => 'Dashboard',       'file' => 'index.php',          'perm' => 'dashboard',         'fixed' => 'core',  'icon' => '<path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/>'],
        'inventory'      => ['label' => 'Inventory',       'file' => 'inventory.php',      'perm' => 'inventory.view',    'fixed' => null,    'icon' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>'],
        'tracking'       => ['label' => 'Tracking',        'file' => 'tracking.php',       'perm' => 'inventory.view',    'fixed' => null,    'icon' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
        'classification' => ['label' => 'Classification',  'file' => 'classification.php', 'perm' => 'inventory.view',    'fixed' => null,    'icon' => '<path d="M20 13l-7 7-9-9V4h7l9 9z"/><circle cx="8.5" cy="8.5" r="1.2"/>'],
        'verification'   => ['label' => 'Verification',    'file' => 'verification.php',   'perm' => 'verify',            'fixed' => null,    'icon' => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>'],
        'search'         => ['label' => 'Search',          'file' => 'search.php',         'perm' => 'search',            'fixed' => null,    'icon' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/>'],
        'scan'           => ['label' => 'QR / Barcode',    'file' => 'scan.php',           'perm' => 'qr',                'fixed' => null,    'icon' => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M7 12h10"/>'],
        'custody'        => ['label' => 'Custody',         'file' => 'custody.php',        'perm' => 'custody.view',      'fixed' => null,    'icon' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>'],
        'clientrequests' => ['label' => 'Client requests', 'file' => 'clientrequests.php', 'perm' => 'creq',              'fixed' => null,    'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'],
        'retention'      => ['label' => 'Retention',       'file' => 'retention.php',      'perm' => 'retention.request', 'fixed' => null,    'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'reports'        => ['label' => 'Reports',         'file' => 'reports.php',        'perm' => 'reports.view',      'fixed' => null,    'icon' => '<path d="M4 20V11M10 20V5M16 20v-6M2 21h20"/>'],
        'tasks'          => ['label' => 'Tasks',           'file' => 'tasks.php',          'perm' => 'dashboard',         'fixed' => null,    'icon' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 12l3 3 5-6"/>'],
        'users'          => ['label' => 'Users',           'file' => 'users.php',          'perm' => 'users.manage',      'fixed' => 'admin', 'icon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>'],
        'modules'        => ['label' => 'Modules',         'file' => 'modules.php',        'perm' => 'users.manage',      'fixed' => 'admin', 'icon' => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>'],
        'audit'          => ['label' => 'Audit trail',     'file' => 'audit.php',          'perm' => 'audit.view',        'fixed' => 'admin', 'icon' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>'],
        'settings'       => ['label' => 'Settings',        'file' => 'settings.php',       'perm' => 'settings.manage',   'fixed' => 'admin', 'icon' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>'],
        'backup'         => ['label' => 'Backup',          'file' => 'backup.php',         'perm' => 'backup.manage',     'fixed' => 'admin', 'icon' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>'],
    ];
}
// Only these can be changed by the administrator.
function module_managed(): array { return array_filter(module_registry(), fn($m) => $m['fixed'] === null); }
function module_for_file(string $file): ?string {
    foreach (module_managed() as $k => $m) if ($m['file'] === $file) return $k;
    return null;
}

// Creates the table on an existing database (runs once per login session; safe to repeat).
function ensure_module_schema(): void {
    try {
        q("CREATE TABLE IF NOT EXISTS module_access(
            scope_type VARCHAR(10) NOT NULL, scope_id VARCHAR(40) NOT NULL, module VARCHAR(40) NOT NULL, allowed TINYINT(1) NOT NULL,
            updated_by INT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(scope_type, scope_id, module)) ENGINE=InnoDB");
    } catch (Throwable $e) { error_log('ensure_module_schema: ' . $e->getMessage()); }
}

function role_default_on(string $role, string $perm): bool {
    $l = PERMS[$role] ?? [];
    return in_array('*', $l, true) || in_array($perm, $l, true);
}
// Saved overrides that apply to a person with this role / division / user id: ['role' => [module => bool], 'division' => [...], 'user' => [...]]
function module_layers(?string $role, ?int $division, ?int $uid): array {
    $out = ['role' => [], 'division' => [], 'user' => []];
    try {
        $rows = all("SELECT scope_type, module, allowed FROM module_access
                     WHERE (scope_type='role' AND scope_id=?) OR (scope_type='division' AND scope_id=?) OR (scope_type='user' AND scope_id=?)",
                    [(string)$role, (string)(int)$division, (string)(int)$uid]);
        foreach ($rows as $r) if (isset($out[$r['scope_type']])) $out[$r['scope_type']][$r['module']] = (int)$r['allowed'] === 1;
    } catch (Throwable $e) { /* table not created yet: no overrides */ }
    return $out;
}
// Result per managed module: ['on' => bool, 'src' => 'default'|'role'|'division'|'user']. $use limits which override levels count.
function module_resolve(?string $role, array $layers, array $use = ['role', 'division', 'user']): array {
    $res = [];
    foreach (module_managed() as $k => $m) {
        $on = $role !== null && role_default_on($role, $m['perm']); $src = 'default';
        foreach (['role', 'division', 'user'] as $lv) {
            if (in_array($lv, $use, true) && array_key_exists($k, $layers[$lv])) { $on = $layers[$lv][$k]; $src = $lv; }
        }
        $res[$k] = ['on' => $on, 'src' => $src];
    }
    return $res;
}
// For the signed-in user: 'default' (use the role's permission), 'grant' (opened by the administrator) or 'deny' (switched off).
function module_state(string $key): string {
    static $c = null;
    if ($c === null) {
        $c = [];
        $role = role();
        if ($role !== 'admin') {
            $div = isset($_SESSION['division_id']) && $_SESSION['division_id'] !== null ? (int)$_SESSION['division_id'] : null;
            foreach (module_resolve($role, module_layers($role, $div, (int)($_SESSION['uid'] ?? 0))) as $k => $r)
                $c[$k] = $r['src'] === 'default' ? 'default' : ($r['on'] ? 'grant' : 'deny');
        }
    }
    return $c[$key] ?? 'default';
}
// Sidebar visibility.
function module_visible(string $file, string $perm): bool {
    $k = module_for_file($file);
    if ($k !== null) {
        $s = module_state($k);
        if ($s === 'deny') return false;
        if ($s === 'grant') return true;
    }
    return can($perm);
}