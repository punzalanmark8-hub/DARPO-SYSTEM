<?php
const STATUSES = [
    'inventoried' => 'Inventoried', 'scanning' => 'Scanning',
    'for_verification' => 'For Verification', 'digitized' => 'Digitized', 'archived' => 'Archived',
];
const ROLES = [
    'admin' => 'Administrator', 'records_officer' => 'Records Officer', 'division_staff' => 'Division Staff',
    'verifier' => 'Verifier', 'viewer' => 'Authorized Viewer',
];

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post(string $k, string $d = ''): string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function get(string $k, string $d = ''): string { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function redirect(string $url) { header('Location: ' . $url); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function fmt_dt(?string $d): string { return $d ? date('M j, Y g:i A', strtotime($d)) : '—'; }
function fmt_d(?string $d): string { return $d ? date('M j, Y', strtotime($d)) : '—'; }
function status_label(string $s): string { return STATUSES[$s] ?? ($s === 'for_scanning' ? 'For Scanning' : $s); }   // for_scanning was removed; old history rows may still mention it

function badge(string $text, string $cls = ''): string { return '<span class="badge ' . e($cls) . '">' . e($text) . '</span>'; }
function status_badge(string $s): string { return badge(status_label($s), 's-' . $s); }
function access_badge(string $a): string { return badge(ucfirst($a), $a === 'open' ? 'ok' : 'bad'); }
function flag_badge(string $f): string { return $f === 'ok' ? '' : badge(ucfirst($f), 'bad'); }

function setting(string $k, ?string $default = null): ?string {
    static $s;
    if ($s === null) { $s = []; foreach (all('SELECT skey,svalue FROM settings') as $r) $s[$r['skey']] = $r['svalue']; }
    return $s[$k] ?? $default;
}

// Race-free sequence generator (no explicit transaction needed)
function next_counter(string $name, int $year): int {
    q('INSERT INTO counters(name,yr,val) VALUES(?,?,LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE val=LAST_INSERT_ID(val+1)', [$name, $year]);
    return (int)val('SELECT LAST_INSERT_ID()');
}

function add_history(int $recordId, string $action, ?string $from = null, ?string $to = null, ?string $remarks = null): void {
    insert('record_history', ['record_id' => $recordId, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
        'remarks' => $remarks, 'user_id' => $_SESSION['uid'] ?? null]);
}

function notify(int $userId, string $msg, ?string $link = null): void {
    $msg = mb_substr($msg, 0, 300);
    // The same unread message is never stacked twice (stops daily reminders from piling up)
    if (val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0 AND message=?', [$userId, $msg])) return;
    insert('notifications', ['user_id' => $userId, 'message' => $msg, 'link' => $link]);
}
// Active user ids of one role.
function role_ids(string $role): array {
    return array_map(fn($u) => (int)$u['id'], all('SELECT id FROM users WHERE role=? AND is_active=1', [$role]));
}
// Notify a list of users once each. The person who triggered the action is skipped, because they already know.
function notify_users(array $ids, string $msg, ?string $link = null): void {
    $me = (int)($_SESSION['uid'] ?? 0);
    foreach (array_unique(array_map('intval', $ids)) as $id) if ($id > 0 && $id !== $me) notify($id, $msg, $link);
}
// Notify everyone holding any of these roles (each person once).
function notify_roles(array $roles, string $msg, ?string $link = null): void {
    $ids = [];
    foreach ($roles as $r) $ids = array_merge($ids, role_ids($r));
    notify_users($ids, $msg, $link);
}
// Notify only the people holding this role (and never the person who triggered the action).
function notify_role(string $role, string $msg, ?string $link = null): void {
    notify_users(role_ids($role), $msg, $link);
}

// Optional OCR: uses pdftotext / tesseract if installed on the server. Returns '' when unsupported.
function extract_text(string $path, string $ext): string {
    if (!function_exists('shell_exec') || @filesize($path) > 30 * 1048576) return '';   // OCR on very large files can run for minutes
    @set_time_limit(180);
    $ext = strtolower($ext);
    $out = '';
    if ($ext === 'pdf') $out = (string)@shell_exec('pdftotext -q ' . escapeshellarg($path) . ' - 2>/dev/null');
    elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'tif', 'tiff'], true)) $out = (string)@shell_exec('tesseract ' . escapeshellarg($path) . ' stdout 2>/dev/null');
    return mb_substr(trim($out), 0, 1000000);
}

function pdf_page_count(string $path): int {
    // Read in chunks so large scans never need to fit in memory
    $h = @fopen($path, 'rb'); if (!$h) return 1;
    $n = 0; $tail = '';
    while (!feof($h)) {
        $chunk = fread($h, 1048576); if ($chunk === false || $chunk === '') break;
        $buf = $tail . $chunk;
        $skip = strlen($tail);
        if (preg_match_all('/\/Type\s*\/Page(?![s\w])/', $buf, $m, PREG_OFFSET_CAPTURE))
            foreach ($m[0] as $hit) if ($hit[1] + strlen($hit[0]) > $skip) $n++;   // ignore markers already counted in the previous chunk
        $tail = substr($buf, -32);
        $buf = null;
    }
    fclose($h);
    return max(1, $n);
}

// Marks overdue loans and notifies the borrower and the releasing officer once.
function refresh_overdue(): void {
    q("UPDATE custody SET status='overdue' WHERE status='out' AND due_date < CURDATE()");
    $rows = all("SELECT c.id,c.slip_no,c.borrower_id,c.released_by,r.inventory_no FROM custody c JOIN records r ON r.id=c.record_id
                 WHERE c.status='overdue' AND c.overdue_notified=0");
    foreach ($rows as $c) {
        $m = "Overdue return: {$c['inventory_no']} (slip {$c['slip_no']})";
        notify((int)$c['borrower_id'], $m, 'custody.php');
        if ((int)$c['released_by'] !== (int)$c['borrower_id']) notify((int)$c['released_by'], $m, 'custody.php');
        q('UPDATE custody SET overdue_notified=1 WHERE id=?', [$c['id']]);
    }
}

function validate_password(string $p): ?string {
    $min = cfg()['app']['min_password'];
    if (strlen($p) < $min) return "Password must be at least $min characters.";
    if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p)) return 'Password needs upper-case, lower-case and a digit.';
    return null;
}

function csv_download(string $filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
    fputcsv($o, $headers);
    foreach ($rows as $r) fputcsv($o, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, array_values($r))); // blocks CSV formula injection
    exit;
}

// ---- Physical storage locations (set up by the administrator in Settings) ----
const STORAGE_TYPES = ['filing_cabinet' => 'Filing Cabinet', 'document_holder' => 'Document Boxes / Folders / Binders', 'storage_shelf' => 'Records Storage Shelves'];
const STORAGE_SHORT = ['filing_cabinet' => 'Filing Cabinet', 'document_holder' => 'Document holder', 'storage_shelf' => 'Storage Shelf'];

// Creates the storage tables and columns on an existing database (runs once per login session; safe to repeat).
function ensure_storage_schema(): void {
    try {
        q("CREATE TABLE IF NOT EXISTS storage_categories(
            id INT AUTO_INCREMENT PRIMARY KEY, storage_type VARCHAR(20) NOT NULL, name VARCHAR(80) NOT NULL, code VARCHAR(10) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY uq_storage_cat(storage_type,name)) ENGINE=InnoDB");
        foreach (['storage_type' => 'VARCHAR(20) NULL', 'storage_category_id' => 'INT NULL', 'storage_no' => 'INT NULL'] as $col => $def) {
            if (!val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='records' AND column_name=?", [$col]))
                q("ALTER TABLE records ADD COLUMN $col $def");
        }
        // Every storage category gets its own QR token (scanning it lists the scanned documents kept in that storage)
        if (!val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='storage_categories' AND column_name='qr_token'"))
            q('ALTER TABLE storage_categories ADD COLUMN qr_token CHAR(16) NULL, ADD UNIQUE KEY uq_storage_cat_qr(qr_token)');
        if (!val('SELECT COUNT(*) FROM storage_categories WHERE storage_type=?', ['document_holder'])) {
            foreach ([['Document Box', 'DB'], ['Folder', 'FD'], ['Binder', 'BD']] as [$n, $c])
                q('INSERT IGNORE INTO storage_categories(storage_type,name,code) VALUES(?,?,?)', ['document_holder', $n, $c]);
        }
        foreach (all('SELECT id FROM storage_categories WHERE qr_token IS NULL') as $row)
            q('UPDATE storage_categories SET qr_token=? WHERE id=?', [bin2hex(random_bytes(8)), $row['id']]);
    } catch (Throwable $e) { error_log('ensure_storage_schema: ' . $e->getMessage()); }
}
// Highest cabinet / holder number the administrator allows for a storage type.
function storage_max(string $type): int {
    $def = ['filing_cabinet' => 10, 'document_holder' => 50, 'storage_shelf' => 10][$type] ?? 10;
    return max(1, min(9999, (int)setting("max_$type", (string)$def)));
}
// All storage categories keyed by id (cached for the request).
function storage_cats(): array {
    static $m;
    if ($m === null) { $m = []; try { foreach (all('SELECT * FROM storage_categories ORDER BY name') as $c) $m[(int)$c['id']] = $c; } catch (Throwable $e) {} }
    return $m;
}
function storage_config(): array {
    $out = [];
    foreach (STORAGE_TYPES as $k => $label) {
        $cats = [];
        foreach (storage_cats() as $c) if ($c['storage_type'] === $k && $c['is_active']) $cats[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'code' => $c['code']];
        $out[$k] = ['label' => STORAGE_SHORT[$k], 'max' => storage_max($k), 'holder' => $k === 'document_holder', 'cats' => $cats];
    }
    return $out;
}
// Validates the storage fields of a POST. Returns [columns to save, error message or null].
function storage_from_post(): array {
    $none = ['storage_type' => null, 'storage_category_id' => null, 'storage_no' => null];
    $t = post('storage_type');
    if ($t === '') return [$none, null];
    if (!isset(STORAGE_TYPES[$t])) return [$none, 'Choose a valid physical storage.'];
    $max = storage_max($t); $no = (int)post('storage_no'); $cid = (int)post('storage_category_id');
    if ($no < 1 || $no > $max) return [$none, ($t === 'document_holder' ? 'Choose the No.' : 'Choose the cabinet number') . " (1 to $max)."];
    $cat = $cid ? one('SELECT * FROM storage_categories WHERE id=? AND storage_type=? AND is_active=1', [$cid, $t]) : null;
    if ($cid && !$cat) return [$none, 'That category is not available for this storage.'];
    if (!$cat && ($t === 'document_holder' || val('SELECT COUNT(*) FROM storage_categories WHERE storage_type=? AND is_active=1', [$t])))
        return [$none, 'Choose a category for this storage.'];
    return [['storage_type' => $t, 'storage_category_id' => $cat ? (int)$cat['id'] : null, 'storage_no' => $no], null];
}
// Human readable location. Boxes, folders and binders read as CODE-NO (for example FD-2).
function storage_label(array $r): ?string {
    $t = $r['storage_type'] ?? null;
    if (!$t || !isset(STORAGE_TYPES[$t])) return null;
    $cat = storage_cats()[(int)($r['storage_category_id'] ?? 0)] ?? null;
    $no = (int)($r['storage_no'] ?? 0);
    if ($t === 'document_holder') return ($cat ? ($cat['code'] ?: $cat['name']) : 'Holder') . '-' . $no;
    return implode(' / ', array_filter([STORAGE_SHORT[$t], 'Cabinet ' . $no, $cat['name'] ?? '']));
}
// The dropdowns: Physical storage, then cabinet number / No., then category. Used on the register form and on the record page.
function storage_picker(array $cur = []): string {
    static $once = false;
    $t = (string)($cur['storage_type'] ?? '');
    $h = '<div class="stpick" data-no="' . (int)($cur['storage_no'] ?? 0) . '" data-cat="' . (int)($cur['storage_category_id'] ?? 0) . '">';
    $h .= '<label>Physical storage <select name="storage_type" class="st-type"><option value="">Not assigned yet</option>';
    foreach (STORAGE_TYPES as $k => $v) $h .= '<option value="' . $k . '"' . ($t === $k ? ' selected' : '') . '>' . e($v) . '</option>';
    $h .= '</select></label>';
    $h .= '<label class="st-numw" hidden><span class="st-numl">Cabinet number</span> <select name="storage_no" class="st-no" disabled></select></label>';
    $h .= '<label class="st-catw" hidden>Category <select name="storage_category_id" class="st-cat" disabled></select></label>';
    $h .= '<span class="st-prev muted span2"></span></div>';
    if (!$once) {
        $once = true;
        $js = <<<'JS'
(function(){
function opt(v,t,s){var o=document.createElement('option');o.value=v;o.textContent=t;if(s)o.selected=true;return o;}
function cur(b){var c=__ST[b.querySelector('.st-type').value];return c||null;}
function preview(b){
  var c=cur(b),pv=b.querySelector('.st-prev'),no=b.querySelector('.st-no').value,id=b.querySelector('.st-cat').value,cat=null;
  if(!c||!no){pv.textContent='';return;}
  c.cats.forEach(function(x){if(String(x.id)===id)cat=x;});
  if(c.holder){pv.textContent=cat?'Location name: '+(cat.code||cat.name)+'-'+no:'';}
  else pv.textContent='Location: '+c.label+' / Cabinet '+no+(cat?' / '+cat.name:'');
}
function sync(b,initial){
  var c=cur(b),nw=b.querySelector('.st-numw'),no=b.querySelector('.st-no'),cw=b.querySelector('.st-catw'),ca=b.querySelector('.st-cat');
  var kn=initial?b.getAttribute('data-no'):'',kc=initial?b.getAttribute('data-cat'):'';
  no.textContent='';ca.textContent='';
  if(!c){nw.hidden=true;cw.hidden=true;no.disabled=true;ca.disabled=true;no.required=false;ca.required=false;preview(b);return;}
  nw.hidden=false;cw.hidden=false;no.disabled=false;ca.disabled=false;no.required=true;ca.required=c.holder||c.cats.length>0;
  b.querySelector('.st-numl').textContent=c.holder?'No.':'Cabinet number';
  no.appendChild(opt('','Select'));
  for(var i=1;i<=c.max;i++)no.appendChild(opt(i,i,String(i)===String(kn)));
  ca.appendChild(opt('',c.cats.length?'Select category':'No category yet'));
  c.cats.forEach(function(x){ca.appendChild(opt(x.id,c.holder&&x.code?x.name+' ('+x.code+')':x.name,String(x.id)===String(kc)));});
  preview(b);
}
function wire(){
  Array.prototype.forEach.call(document.querySelectorAll('.stpick'),function(b){
    if(b._w)return;b._w=1;
    b.addEventListener('change',function(e){if(e.target.classList.contains('st-type'))sync(b,false);else preview(b);});
    var f=b.closest('form');if(f)f.addEventListener('reset',function(){setTimeout(function(){sync(b,true);},0);});
    sync(b,true);
  });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',wire);else wire();
})();
JS;
        $h .= '<script>var __ST=' . json_encode(storage_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';' . $js . '</script>';
    }
    return $h;
}