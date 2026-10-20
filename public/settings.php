<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('settings.manage');
require_once __DIR__ . '/../app/scanlib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'division' && post('code') !== '' && post('name') !== '') {
        if (one('SELECT id FROM divisions WHERE code=?', [strtoupper(post('code'))])) flash('err', 'Division code already exists.');
        else { $id = insert('divisions', ['code' => strtoupper(post('code')), 'name' => post('name')]); audit('division_added', 'division', $id, post('name')); flash('ok', 'Division added.'); }
    } elseif ($act === 'section' && post('name') !== '' && (int)post('division_id')) {
        $id = insert('sections', ['division_id' => (int)post('division_id'), 'name' => post('name')]); audit('section_added', 'section', $id, post('name')); flash('ok', 'Section added.');
    } elseif ($act === 'category' && post('name') !== '' && post('prefix') !== '') {
        if (one('SELECT id FROM categories WHERE name=?', [post('name')])) flash('err', 'Category already exists.');
        else {
            $id = insert('categories', ['name' => post('name'), 'prefix' => strtoupper(post('prefix')), 'default_access' => post('default_access') === 'open' ? 'open' : 'restricted', 'naming_convention' => post('naming') ?: null]);
            audit('category_added', 'category', $id, post('name')); flash('ok', 'Category added.');
        }
    } elseif ($act === 'toggle' && in_array(post('table'), ['divisions', 'sections', 'categories', 'storage_categories'], true)) {
        q('UPDATE ' . post('table') . ' SET is_active=1-is_active WHERE id=?', [(int)post('id')]); audit('config_toggled', post('table'), (int)post('id')); flash('ok', 'Updated.');
    } elseif ($act === 'storage_limits') {
        foreach (array_keys(STORAGE_TYPES) as $t) q('INSERT INTO settings(skey,svalue) VALUES(?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', ["max_$t", (string)max(1, min(9999, (int)post("max_$t")))]);
        audit('settings_changed', 'settings', null, 'storage limits'); flash('ok', 'Storage limits saved.');
    } elseif ($act === 'storage_cat') {
        $t = post('storage_type'); $n = post('name'); $c = strtoupper(post('code'));
        if (!isset(STORAGE_TYPES[$t]) || $n === '') flash('err', 'Choose the storage and enter a category name.');
        elseif ($t === 'document_holder' && $c === '') flash('err', 'Boxes, folders and binders need a code (for example FD).');
        elseif ($c !== '' && !preg_match('/^[A-Z0-9]{1,6}$/', $c)) flash('err', 'The code can only use letters and numbers (up to 6).');
        elseif (one('SELECT id FROM storage_categories WHERE storage_type=? AND name=?', [$t, $n])) flash('err', 'That category already exists for this storage.');
        elseif ($c !== '' && one('SELECT id FROM storage_categories WHERE storage_type=? AND code=?', [$t, $c])) flash('err', 'That code is already used.');
        else { $id = insert('storage_categories', ['storage_type' => $t, 'name' => $n, 'code' => $c ?: null]); audit('storage_category_added', 'storage_category', $id, STORAGE_TYPES[$t] . ': ' . $n); flash('ok', 'Storage category added.'); }
    } elseif ($act === 'scanners') {
        $lines = array_filter(array_map('trim', preg_split('/\R/', post('scanners'))), fn($l) => $l !== '');
        $folder = rtrim(post('scan_folder'), "/\\ ");
        $dpi = in_array((int)post('scan_dpi'), [100, 150, 200, 300, 400, 600], true) ? post('scan_dpi') : '200';
        foreach (['scanners' => implode("\n", $lines), 'scan_folder' => $folder, 'scan_dpi' => $dpi] as $k => $v)
            q('INSERT INTO settings(skey,svalue) VALUES(?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', [$k, $v]);
        audit('settings_changed', 'settings', null, 'scanner options');
        $msgs = [];
        if ($folder !== '') { $r = realpath($folder); $msgs[] = ($r && is_dir($r) && is_readable($r)) ? 'Scan folder found.' : 'Warning: the server cannot read that scan folder.'; }
        flash('ok', 'Scanner options saved. ' . implode(' ', $msgs));
    } elseif ($act === 'scanner_test') {
        $s = scanner_list()[(int)post('scanner')] ?? null;
        if (!$s) flash('err', 'Save the scanner list first.');
        else { $c = escl_caps($s['base']); flash($c['ok'] ? 'ok' : 'err', $c['ok'] ? $s['name'] . ' is reachable. Flatbed: ' . ($c['platen'] ? 'yes' : 'no') . ', document feeder: ' . ($c['feeder'] ? 'yes' : 'no') . ', direct PDF: ' . ($c['pdf'] ? 'yes' : 'no (JPEG will be used)') . '.' : $c['msg']); }
    } elseif ($act === 'settings') {
        foreach (['allowed_ext', 'max_upload_mb', 'loan_days', 'backup_frequency', 'retention_review_days', 'ref_format'] as $k) {
            $v = post($k);
            if ($k === 'allowed_ext') $v = implode(',', array_intersect(array_map('trim', explode(',', strtolower($v))), ['pdf', 'png', 'jpg', 'jpeg', 'tif', 'tiff']));
            if ($k === 'backup_frequency' && !in_array($v, ['daily', 'weekly', 'monthly'], true)) $v = 'daily';
            if (in_array($k, ['max_upload_mb', 'loan_days', 'retention_review_days'], true)) $v = (string)max(1, (int)$v);
            if ($v !== '') q('INSERT INTO settings(skey,svalue) VALUES(?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', [$k, $v]);
        }
        audit('settings_changed', 'settings'); flash('ok', 'Settings saved.');
    }
    redirect('settings.php');
}
$divs = all('SELECT * FROM divisions ORDER BY code'); $secs = all('SELECT s.*, d.code FROM sections s JOIN divisions d ON d.id=s.division_id ORDER BY d.code, s.name');
$cats = all('SELECT * FROM categories ORDER BY name');
$toggle = fn($t, $r) => '<form method="post" style="margin:0">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="table" value="' . $t . '"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="sm alt">' . ($r['is_active'] ? 'Deactivate' : 'Activate') . '</button></form>';
page_top('System configuration');
?>
<div class="tabbed"><div class="tabbar noprint" role="tablist" aria-label="System configuration"><a href="#tab-options" data-tab="options" role="tab">System options</a><a href="#tab-scanners" data-tab="scanners" role="tab">Scanners</a><a href="#tab-storage" data-tab="storage" role="tab">Physical storage locations</a><a href="#tab-storagecats" data-tab="storagecats" role="tab">Storage categories</a><a href="#tab-divisions" data-tab="divisions" role="tab">Divisions</a><a href="#tab-sections" data-tab="sections" role="tab">Sections</a><a href="#tab-doccats" data-tab="doccats" role="tab">Document categories</a></div>
<section class="tabpane" id="tab-options">
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><h2>System options</h2><div class="row">
<label>Allowed file types <input name="allowed_ext" value="<?= e(setting('allowed_ext')) ?>"></label><label>Max upload (MB) <input type="number" name="max_upload_mb" value="<?= e(setting('max_upload_mb')) ?>" style="width:90px"></label>
<label>Default loan days <input type="number" name="loan_days" value="<?= e(setting('loan_days')) ?>" style="width:90px"></label><label>Retention review window (days) <input type="number" name="retention_review_days" value="<?= e(setting('retention_review_days')) ?>" style="width:90px"></label>
<label>Backup schedule <select name="backup_frequency"><?php foreach (['daily', 'weekly', 'monthly'] as $o): ?><option <?= setting('backup_frequency') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></label>
<label>Reference number format <input name="ref_format" value="<?= e(setting('ref_format')) ?>"></label><button>Save</button></div></form>
</section>
<section class="tabpane" id="tab-scanners">
<div class="card"><h2>Scanners</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="scanners">
<p class="muted" style="margin-top:0">Staff use these from the <strong>Use scanner</strong> button when uploading a scan. Two ways to connect an Epson (or any other) scanner:</p>
<div class="fld"><strong>1. Network scanner</strong>
<p class="hint">(Epson WorkForce, EcoTank, DS and ES network models; any scanner that supports eSCL / AirScan). One per line, as <code>Name | IP address</code>. The server must be on the same network as the scanner.</p>
<textarea name="scanners" id="scannersBox" aria-label="Network scanners" rows="3" placeholder="Epson DS-530 II | 192.168.1.50&#10;Epson L6270 Records Room | 192.168.1.51"><?= e(setting('scanners', '')) ?></textarea></div>
<div class="fld"><strong>2. Scan folder</strong>
<p class="hint">(USB scanners). In Epson Scan 2, Epson ScanSmart or Document Capture Pro, set the scan to save as PDF into this folder on the server (full path, for example <code>C:\scans\inbox</code> or <code>/srv/scans</code>). Files then appear in the scanner window for one-click import.</p>
<input name="scan_folder" aria-label="Scan folder path" value="<?= e(setting('scan_folder', '')) ?>" placeholder="C:\scans\inbox"></div>
<div class="row"><label>Default resolution (dpi) <select name="scan_dpi"><?php foreach ([100, 150, 200, 300, 400, 600] as $d): ?><option <?= (int)setting('scan_dpi', '200') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></label><button>Save scanner options</button></div></form>
<?php $sl = scanner_list(); if ($sl): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="scanner_test"><label>Test connection <select name="scanner"><?php foreach ($sl as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label><button class="alt">Test</button></form><?php endif; ?></div>
</section>
<section class="tabpane" id="tab-storage">
<div class="card"><h2>Physical storage locations</h2>
<p class="muted" style="margin-top:0">When a file is registered, staff pick the <strong>Physical storage</strong>, then the cabinet number (Filing Cabinet and Records Storage Shelves) or the No. (Boxes / Folders / Binders), then the category. A folder with No. 2 becomes <strong>FD-2</strong>.</p>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="storage_limits">
<?php foreach (STORAGE_TYPES as $k => $v): ?><label><?= e($v) ?>: highest <?= $k === 'document_holder' ? 'No.' : 'cabinet number' ?> <input type="number" min="1" max="9999" name="max_<?= $k ?>" value="<?= storage_max($k) ?>" style="width:90px"></label><?php endforeach; ?>
<button>Save limits</button></form></div>
</section>
<section class="tabpane" id="tab-storagecats">
<?php $scats = all("SELECT * FROM storage_categories ORDER BY FIELD(storage_type,'filing_cabinet','document_holder','storage_shelf'), name"); ?>
<div class="card tw"><h2>Storage categories</h2><table><tr><th>Physical storage</th><th>Category</th><th>Code</th><th></th></tr>
<?php foreach ($scats as $c): ?><tr><td><?= e(STORAGE_TYPES[$c['storage_type']] ?? $c['storage_type']) ?></td><td><?= e($c['name']) ?> <?= $c['is_active'] ? '' : badge('inactive', 'bad') ?></td><td><?= e($c['code'] ?? '—') ?></td><td><?= $toggle('storage_categories', $c) ?></td></tr>
<?php endforeach; if (!$scats): ?><tr><td colspan="4" class="muted">No categories yet.</td></tr><?php endif; ?></table>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="storage_cat">
<label>Physical storage <select name="storage_type"><?php foreach (STORAGE_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
<label>Category name <input name="name" required></label><label>Code (needed for boxes, folders, binders) <input name="code" maxlength="6" style="width:90px" placeholder="FD"></label><button class="alt">Add category</button></form></div>
</section>
<section class="tabpane" id="tab-divisions">
<div class="card tw"><h2>Divisions</h2><table><tr><th>Code</th><th>Name</th><th></th></tr><?php foreach ($divs as $d): ?><tr><td><?= e($d['code']) ?></td><td><?= e($d['name']) ?> <?= $d['is_active'] ? '' : badge('inactive', 'bad') ?></td><td><?= $toggle('divisions', $d) ?></td></tr><?php endforeach; ?></table>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="division"><label>Code <input name="code" required style="width:90px"></label><label>Name <input name="name" required></label><button class="alt">Add division</button></form></div>
</section>
<section class="tabpane" id="tab-sections">
<div class="card tw"><h2>Sections</h2><table><tr><th>Division</th><th>Section</th><th></th></tr><?php foreach ($secs as $s): ?><tr><td><?= e($s['code']) ?></td><td><?= e($s['name']) ?> <?= $s['is_active'] ? '' : badge('inactive', 'bad') ?></td><td><?= $toggle('sections', $s) ?></td></tr><?php endforeach; ?></table>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="section"><label>Division <select name="division_id"><?= select_options($divs, 'id', 'name') ?></select></label><label>Section name <input name="name" required></label><button class="alt">Add section</button></form></div>
</section>
<section class="tabpane" id="tab-doccats">
<div class="card tw"><h2>Document categories</h2><table><tr><th>Name</th><th>Prefix</th><th>Default access</th><th>Naming convention</th><th></th></tr><?php foreach ($cats as $c): ?><tr><td><?= e($c['name']) ?> <?= $c['is_active'] ? '' : badge('inactive', 'bad') ?></td><td><?= e($c['prefix']) ?></td><td><?= access_badge($c['default_access']) ?></td><td><?= e($c['naming_convention']) ?></td><td><?= $toggle('categories', $c) ?></td></tr><?php endforeach; ?></table>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="category"><label>Name <input name="name" required></label><label>Prefix <input name="prefix" required style="width:90px"></label>
<label>Default access <select name="default_access"><option value="restricted">Restricted</option><option value="open">Open</option></select></label><label>Naming convention <input name="naming"></label><button class="alt">Add category</button></form></div>
</section></div>
<script>
(function(){var w=document.querySelector('.tabbed');if(!w)return;w.classList.add('js');
var tabs=[].slice.call(w.querySelectorAll('.tabbar a')),panes=[].slice.call(w.querySelectorAll('.tabpane'));
function show(id,save){var found=false;panes.forEach(function(p){var on=p.id==='tab-'+id;if(on)found=true;});
if(!found)id=panes[0].id.slice(4);
panes.forEach(function(p){p.classList.toggle('on',p.id==='tab-'+id);});
tabs.forEach(function(a){var on=a.getAttribute('data-tab')===id;a.classList.toggle('on',on);a.setAttribute('aria-selected',on?'true':'false');});
if(save){try{sessionStorage.setItem('cfgTab',id);}catch(e){}if(history.replaceState)history.replaceState(null,'','#tab-'+id);}}
tabs.forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();show(a.getAttribute('data-tab'),true);});});
var h=location.hash.replace('#tab-',''),s=null;try{s=sessionStorage.getItem('cfgTab');}catch(e){}
show(h||s||'options',false);})();
</script>
<?php page_bottom();