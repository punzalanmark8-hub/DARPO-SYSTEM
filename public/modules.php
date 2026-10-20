<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('users.manage');

$reg = module_managed();
$types = ['role' => 'By role', 'division' => 'By division', 'user' => 'By user'];

// The role / division / user being edited (administrators cannot be restricted, so they are not listed)
function mm_target(string $type, string $id): ?array {
    if ($type === 'role')
        return ($id !== 'admin' && isset(ROLES[$id])) ? ['id' => $id, 'label' => ROLES[$id], 'role' => $id, 'division' => null, 'uid' => null] : null;
    if ($type === 'division') {
        $d = one('SELECT id, code, name FROM divisions WHERE id=?', [(int)$id]);
        return $d ? ['id' => (string)$d['id'], 'label' => $d['code'] . ' - ' . $d['name'], 'role' => null, 'division' => (int)$d['id'], 'uid' => null] : null;
    }
    if ($type === 'user') {
        $u = one('SELECT id, username, full_name, role, division_id FROM users WHERE id=?', [(int)$id]);
        return ($u && $u['role'] !== 'admin') ? ['id' => (string)$u['id'], 'label' => $u['full_name'] . ' (' . $u['username'] . ')', 'role' => $u['role'],
                'division' => $u['division_id'] !== null ? (int)$u['division_id'] : null, 'uid' => (int)$u['id']] : null;
    }
    return null;
}

// Role and user pages show every module as Allowed or Not allowed (what the person has right now).
// A division has no single role behind it, so it keeps the three columns (Allowed / Role decides / Blocked).
function mm_two_zone(string $type): bool { return $type !== 'division'; }
// What a module would be WITHOUT this scope's own override (the level below it): role default, or role/division settings for a user.
function mm_baseline(string $type, array $t): array {
    $out = [];
    if ($type === 'user') {
        foreach (module_resolve($t['role'], module_layers($t['role'], $t['division'], $t['uid']), ['role', 'division']) as $k => $r) $out[$k] = (bool)$r['on'];
    } else {
        foreach (module_managed() as $k => $m) $out[$k] = role_default_on((string)$t['role'], $m['perm']);
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = post('scope'); $id = post('id'); $act = post('action');
    $t = isset($types[$type]) ? mm_target($type, $id) : null;
    if (!$t) {
        flash('err', 'Choose a valid role, division or user first.');
    } elseif ($act === 'reset') {
        q('DELETE FROM module_access WHERE scope_type=? AND scope_id=?', [$type, $t['id']]);
        audit('modules_reset', $type, $t['id'], $t['label']);
        flash('ok', 'Module access for ' . $t['label'] . ' was reset to the default.');
    } elseif ($act === 'save') {
        $pick = fn(string $k) => array_values(array_unique(array_filter(array_map('trim', explode(',', post($k))), fn($m) => isset($reg[$m]))));
        $grant = $pick('grant'); $deny = array_values(array_diff($pick('deny'), $grant));
        if (mm_two_zone($type)) {
            // Every module arrives as Allowed or Not allowed. Store only what differs from the level below, so
            // later changes to the role / division still flow through to everything left unchanged.
            if (count($grant) + count($deny) !== count($reg)) {
                flash('err', 'The selection could not be read. Reload the page and try again.');
                redirect('modules.php?scope=' . urlencode($type) . '&id=' . urlencode($id));
            }
            $base = mm_baseline($type, $t); $g2 = []; $d2 = [];
            foreach ($reg as $k => $m) {
                $want = in_array($k, $grant, true);
                if ($want !== ($base[$k] ?? false)) { if ($want) $g2[] = $k; else $d2[] = $k; }
            }
            $grant = $g2; $deny = $d2;
        }
        $pdo = db();
        try {
            $pdo->beginTransaction();
            q('DELETE FROM module_access WHERE scope_type=? AND scope_id=?', [$type, $t['id']]);
            foreach ($grant as $m) q('INSERT INTO module_access(scope_type,scope_id,module,allowed,updated_by) VALUES(?,?,?,1,?)', [$type, $t['id'], $m, $_SESSION['uid']]);
            foreach ($deny as $m)  q('INSERT INTO module_access(scope_type,scope_id,module,allowed,updated_by) VALUES(?,?,?,0,?)', [$type, $t['id'], $m, $_SESSION['uid']]);
            $pdo->commit();
            audit('modules_updated', $type, $t['id'], mb_substr($t['label'] . ' | allowed: ' . ($grant ? implode(',', $grant) : '-') . ' | blocked: ' . ($deny ? implode(',', $deny) : '-'), 0, 480));
            flash('ok', 'Module access saved for ' . $t['label'] . '. It takes effect on their next page.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('modules save: ' . $e->getMessage());
            flash('err', 'Could not save the module access. Please try again.');
        }
    }
    redirect('modules.php?scope=' . urlencode($type) . '&id=' . urlencode($id));
}

// ---- Which role / division / user is shown ----
$type = isset($types[get('scope')]) ? get('scope') : 'role';
$options = [];   // id => label
if ($type === 'role') {
    foreach (ROLES as $k => $v) if ($k !== 'admin') $options[$k] = $v;
} elseif ($type === 'division') {
    foreach (all('SELECT id, code, name FROM divisions WHERE is_active=1 ORDER BY code') as $d) $options[$d['id']] = $d['code'] . ' - ' . $d['name'];
} else {
    foreach (all("SELECT id, username, full_name, role FROM users WHERE role<>'admin' AND is_active=1 ORDER BY full_name") as $u)
        $options[$u['id']] = $u['full_name'] . ' (' . $u['username'] . ') - ' . (ROLES[$u['role']] ?? $u['role']);
}
$id = (string)get('id');
if (!isset($options[$id]) && !isset($options[(int)$id])) $id = $options ? (string)array_key_first($options) : '';
$t = $id !== '' ? mm_target($type, $id) : null;

// ---- Current settings of that scope ----
$zoneOf = array_fill_keys(array_keys($reg), 'default');
$hint = array_fill_keys(array_keys($reg), '');
$effective = null; $people = null; $two = mm_two_zone($type);
if ($t) {
    foreach (all('SELECT module, allowed FROM module_access WHERE scope_type=? AND scope_id=?', [$type, $t['id']]) as $r)
        if (isset($zoneOf[$r['module']])) $zoneOf[$r['module']] = (int)$r['allowed'] ? 'grant' : 'deny';
    if ($type === 'role') {
        foreach ($reg as $k => $m) $hint[$k] = $zoneOf[$k] === 'default' ? 'Role default' : 'Changed by admin';
        $base = mm_baseline($type, $t);
        foreach ($reg as $k => $m) if ($zoneOf[$k] === 'default') $zoneOf[$k] = $base[$k] ? 'grant' : 'deny';
        $people = (int)val('SELECT COUNT(*) FROM users WHERE role=? AND is_active=1', [$t['role']]);
    } elseif ($type === 'division') {
        // which roles of the people in this division normally have each module
        $roles = array_column(all("SELECT DISTINCT role FROM users WHERE division_id=? AND is_active=1 AND role<>'admin'", [$t['division']]), 'role');
        foreach ($reg as $k => $m) {
            $has = array_filter($roles, fn($r) => role_default_on($r, $m['perm']));
            $hint[$k] = $has ? 'Normally: ' . implode(', ', array_map(fn($r) => ROLES[$r] ?? $r, $has)) : 'Role decides';
        }
        $people = (int)val("SELECT COUNT(*) FROM users WHERE division_id=? AND is_active=1 AND role<>'admin'", [$t['division']]);
    } else {
        $layers = module_layers($t['role'], $t['division'], $t['uid']);
        $effective = module_resolve($t['role'], $layers);
        foreach ($effective as $k => $r) {
            $zoneOf[$k] = $r['on'] ? 'grant' : 'deny';
            $hint[$k] = $r['src'] === 'default' ? 'Role default' : 'Set by ' . $r['src'];
        }
    }
}

$zones = $two ? [
    'grant' => ['Allowed',     'Can open these modules now',      'Drop modules here to switch them on'],
    'deny'  => ['Not allowed', 'Hidden and refused for now',      'Drop modules here to switch them off'],
] : [
    'grant'   => ['Allowed',  'Always open, even if the role normally lacks it', 'Drop modules here to switch them on'],
    'default' => ['Role decides', 'Follows each person\'s normal role rules',    'Nothing here'],
    'deny'    => ['Blocked',  'Hidden and refused, even if the role has it',     'Drop modules here to switch them off'],
];
$svg = fn(array $m, int $s = 26) => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $m['icon'] . '</svg>';
$tile = fn(string $k, array $m, string $h) => '<button type="button" class="mm-tile" draggable="true" data-m="' . e($k) . '" aria-label="' . e($m['label']) . '"><span class="mm-ic">' . $svg($m) . '</span><b>' . e($m['label']) . '</b><small>' . e($h) . '</small></button>';

page_top('Module access');
?>
<p class="muted" style="margin-top:-6px">Choose which modules a role, a division or a single user can use. The most specific setting wins: <b>user</b> over <b>division</b> over <b>role</b>.</p>

<div class="tabbar noprint" role="tablist" aria-label="Edit module access">
<?php foreach ($types as $k => $label): ?><a href="modules.php?scope=<?= $k ?>" role="tab"<?= $k === $type ? ' class="on" aria-selected="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</div>

<?php if (!$options): ?>
<div class="card"><p class="muted">There is nothing to configure here yet.</p></div>
<?php else: ?>
<form method="get" class="card mm-scope">
  <input type="hidden" name="scope" value="<?= e($type) ?>">
  <label style="flex:1;min-width:240px"><?= $type === 'role' ? 'Role' : ($type === 'division' ? 'Division' : 'User') ?>
    <select name="id" onchange="this.form.submit()"><?php foreach ($options as $k => $label): ?><option value="<?= e((string)$k) ?>"<?= (string)$k === (string)$id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <noscript><button>Show</button></noscript>
  <?php if ($people !== null): ?><span class="muted"><?= $people ?> active <?= $people === 1 ? 'account is' : 'accounts are' ?> affected.</span><?php endif; ?>
</form>

<?php if ($t): ?>
<form method="post" id="mmForm" class="mm">
  <?= csrf_field() ?>
  <input type="hidden" name="scope" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= e($t['id']) ?>">
  <input type="hidden" name="grant" value=""><input type="hidden" name="deny" value="">
  <p class="muted mm-help"><?= $two ? 'Each module sits where it is right now. Move it to Allowed or Not allowed to change it, then save. ' : '' ?>Drag a module into a column, or click a module and then click the column. With the keyboard, focus a module and press the Left / Right arrow keys.</p>
  <div class="mm-board"<?= $two ? ' style="grid-template-columns:repeat(2,minmax(0,1fr))"' : '' ?>>
  <?php foreach ($zones as $zk => [$zt, $zs, $ze]): ?>
    <section class="mm-zone <?= $zk ?>" data-zone="<?= $zk ?>" aria-label="<?= e($zt) ?> modules">
      <div class="mm-zh"><div><h3><?= e($zt) ?></h3><small><?= e($zs) ?></small></div><span class="mm-count">0</span></div>
      <?php echo '<div class="mm-drop" data-empty="' . e($ze) . '">'; foreach ($reg as $k => $m) if ($zoneOf[$k] === $zk) echo $tile($k, $m, $hint[$k]); echo '</div>'; ?>
    </section>
  <?php endforeach; ?>
  </div>

  <div class="card mm-bar noprint">
    <button name="action" value="save">Save changes</button>
    <button name="action" value="reset" class="alt" formnovalidate onclick="return confirm('Remove every change for this <?= e($type) ?> and go back to the defaults?')">Reset to default</button>
    <span class="mm-dirty" hidden>&#9679; Unsaved changes</span>
  </div>
  <div class="mm-live" id="mmLive" aria-live="polite"></div>
</form>

<?php if ($effective !== null): ?>
<div class="card"><h2 style="font-size:1rem">Currently available to this user</h2><div class="mm-chips">
<?php $any = false; foreach ($effective as $k => $r) if ($r['on']) { $any = true; echo '<span class="mm-chip">' . $svg($reg[$k], 15) . e($reg[$k]['label']) . ($r['src'] !== 'default' ? '<em>' . e($r['src']) . '</em>' : '') . '</span>'; }
if (!$any) echo '<span class="muted">No optional modules (Dashboard only).</span>'; ?>
</div><p class="muted" style="margin:8px 0 0;font-size:13px">The tag shows which level set it (role, division or user). Saved changes are included.</p></div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:1rem">Fixed modules</h2>
  <p class="muted" style="margin-top:0;font-size:13px">These cannot be moved. Dashboard is for everybody; the administration modules stay with administrators so nobody can hand out account or system control by mistake.</p>
  <div class="mm-fixed">
  <?php foreach (module_registry() as $k => $m) if ($m['fixed'] !== null)
      echo '<div class="mm-tile" title="' . ($m['fixed'] === 'core' ? 'Always available' : 'Administrators only') . '"><span class="mm-ic">' . $svg($m) . '</span><b>' . e($m['label']) . '</b><small>' . ($m['fixed'] === 'core' ? 'Always on' : 'Admin only') . '</small></div>'; ?>
  </div>
</div>
<p class="muted" style="font-size:13px">Allowing a module opens its page. What the person can do inside it (add, edit, approve...) still follows their role. Administrators are never restricted.</p>

<script>
(function(){
var f=document.getElementById('mmForm');if(!f)return;
var zones=[].slice.call(f.querySelectorAll('.mm-zone')),live=document.getElementById('mmLive'),dirtyEl=f.querySelector('.mm-dirty');
var sel=null,dragged=null,dirty=false,saving=false;
function drop(z){return z.querySelector('.mm-drop');}
function zoneOf(t){return t.closest('.mm-zone');}
function count(){zones.forEach(function(z){z.querySelector('.mm-count').textContent=drop(z).querySelectorAll('.mm-tile').length;});}
function say(m){live.textContent='';setTimeout(function(){live.textContent=m;},30);}
function setSel(t){if(sel)sel.classList.remove('sel');sel=(sel===t)?null:t;if(sel)sel.classList.add('sel');f.classList.toggle('mm-has-sel',!!sel);}
function move(t,z){
  if(!t||!z||zoneOf(t)===z)return;
  drop(z).appendChild(t);dirty=true;dirtyEl.hidden=false;count();
  say(t.querySelector('b').textContent+' moved to '+z.querySelector('h3').textContent);
}
f.addEventListener('dragstart',function(e){var t=e.target.closest&&e.target.closest('.mm-tile');if(!t||!f.contains(t))return;dragged=t;t.classList.add('drag');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',t.getAttribute('data-m'));}catch(x){}});
f.addEventListener('dragend',function(){if(dragged)dragged.classList.remove('drag');dragged=null;zones.forEach(function(z){z.classList.remove('over');});});
zones.forEach(function(z){
  z.addEventListener('dragover',function(e){if(!dragged)return;e.preventDefault();e.dataTransfer.dropEffect='move';z.classList.add('over');});
  z.addEventListener('dragleave',function(e){if(!z.contains(e.relatedTarget))z.classList.remove('over');});
  z.addEventListener('drop',function(e){e.preventDefault();z.classList.remove('over');move(dragged,z);});
  z.addEventListener('click',function(e){var t=e.target.closest('.mm-tile');if(t){setSel(t);return;}if(sel){move(sel,z);setSel(null);}});
});
f.addEventListener('keydown',function(e){
  var t=e.target.closest&&e.target.closest('.mm-tile');if(e.key==='Escape'&&sel){setSel(null);return;}
  if(!t||(e.key!=='ArrowLeft'&&e.key!=='ArrowRight'))return;
  e.preventDefault();var i=zones.indexOf(zoneOf(t))+(e.key==='ArrowLeft'?-1:1);
  if(i>=0&&i<zones.length){move(t,zones[i]);t.focus();}
});
function keys(name){return [].map.call(f.querySelector('.mm-zone.'+name).querySelectorAll('.mm-tile'),function(t){return t.getAttribute('data-m');}).join(',');}
f.addEventListener('submit',function(){f.elements.grant.value=keys('grant');f.elements.deny.value=keys('deny');saving=true;});
window.addEventListener('beforeunload',function(e){if(dirty&&!saving){e.preventDefault();e.returnValue='';}});
count();
})();
</script>
<?php endif; endif; ?>
<?php page_bottom();