<?php
// Page shell: page_top() prints the header, sidebar and opens <main>; page_bottom() closes it.
// (Rebuilt: the original layout.php was overwritten with a copy of auth.php.)

function page_top(string $title): void {
    $cfg = cfg()['app'];
    $app = (string)($cfg['name'] ?? 'e-Records');
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $uid = (int)($_SESSION['uid'] ?? 0);

    // Force a password change before anything else
    if ($uid && !in_array($cur, ['account.php', 'logout.php', 'notifications.php'], true)
        && (int)(val('SELECT must_change_password FROM users WHERE id=?', [$uid]) ?: 0) === 1) {
        flash('warn', 'Please change your password before continuing.');
        redirect('account.php');
    }

    $unread = $uid ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$uid]) : 0;
    $roleName = ucwords(str_replace('_', ' ', role()));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($app) ?></title>
<link rel="icon" href="assets/darlogo.png">
<link rel="stylesheet" href="assets/style.css">
<style>header.top button.bell .count{top:-6px;right:-6px;min-width:12px;height:14px;line-height:14px;padding:0 3px;font-size:10px}header.top .who{gap:8px}</style>
</head>
<body>
<header class="top">
  <div class="brand">
    <button type="button" class="menu-btn" aria-label="Menu" aria-expanded="false">&#9776;</button>
    <img class="brandlogo" src="assets/darlogo.png" alt="">
    <div><strong><?= e($app) ?></strong><small>Records management and digitization</small></div>
  </div>
  <div class="who">
    <?php if ($uid): ?>
    <div class="notif">
      <button type="button" class="bell" id="bellBtn" aria-label="Notifications" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <span class="count" id="bellCount"<?= $unread ? '' : ' hidden' ?>><?= $unread > 99 ? '99+' : $unread ?></span>
      </button>
      <div class="npop" id="bellPop" hidden>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--ln)">
          <strong>Notifications</strong>
          <a href="#" id="bellAll" style="font-size:12px;margin:0">Mark all read</a>
        </div>
        <div class="nlist" id="bellList"><div class="muted" style="padding:14px">Loading…</div></div>
        <div style="padding:8px 14px"><a href="tasks.php" style="color:var(--g);margin:0;font-size:13px">Open tasks &rarr;</a></div>
      </div>
    </div>
    <span style="margin:0 6px 0 10px;line-height:1.25"><?= e($_SESSION['name'] ?? '') ?> <small><?= e($roleName) ?></small></span>
    <a class="ibtn" href="account.php" title="My account">Account</a>
    <a class="ibtn out" href="logout.php" title="Sign out">Sign out</a>
    <?php endif; ?>
  </div>
</header>
<div class="scrim"></div>
<div class="shell">
<nav>
<?php foreach (module_registry() as $m):
    if (!module_visible($m['file'], $m['perm'])) continue;
    // administration modules are only shown to accounts that actually hold the permission
    if ($m['fixed'] === 'admin' && !can($m['perm'])) continue; ?>
  <a href="<?= e($m['file']) ?>"<?= $cur === $m['file'] ? ' class="on"' : '' ?>><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:8px"><?= $m['icon'] ?></svg><?= e($m['label']) ?></a>
<?php endforeach; ?>
</nav>
<main>
<h1><?= e($title) ?></h1>
<?php
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $cls = in_array($type, ['err', 'warn'], true) ? ' ' . $type : '';
        echo '<div class="flash' . $cls . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

function page_bottom(): void {
    $csrf = $_SESSION['csrf'] ?? '';
    ?>
</main>
</div>
<script src="assets/app.js"></script>
<?php if (!empty($_SESSION['uid'])): ?>
<script>
(function () {
  var btn = document.getElementById('bellBtn'), pop = document.getElementById('bellPop'),
      list = document.getElementById('bellList'), cnt = document.getElementById('bellCount'),
      all = document.getElementById('bellAll'), csrf = <?= json_encode($csrf) ?>;
  if (!btn) return;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function render(d) {
    if (!d || !d.ok) return;
    cnt.textContent = d.unread > 99 ? '99+' : d.unread; cnt.hidden = !d.unread;
    list.innerHTML = d.items.length ? d.items.map(function (n) {
      return '<a class="nitem' + (n.is_read ? '' : ' unread') + '" href="' + esc(n.link || '#') + '" data-id="' + n.id + '">' +
             '<span class="ndot"></span><span>' + esc(n.message) + '<small>' + esc(n.when) + '</small></span></a>';
    }).join('') : '<div class="muted" style="padding:14px">No notifications.</div>';
  }
  function load(id) {
    var opt = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (id !== undefined) {
      var fd = new FormData(); fd.append('csrf', csrf); fd.append('id', id);
      opt.method = 'POST'; opt.body = fd;
    }
    return fetch('notifications.php', opt).then(function (r) { return r.json(); }).then(render).catch(function () {});
  }
  btn.addEventListener('click', function (e) {
    e.stopPropagation(); pop.hidden = !pop.hidden; btn.setAttribute('aria-expanded', !pop.hidden);
    if (!pop.hidden) load();
  });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
  all.addEventListener('click', function (e) { e.preventDefault(); load(0); });
  list.addEventListener('click', function (e) {
    var a = e.target.closest('a.nitem'); if (!a) return;
    if (a.getAttribute('href') === '#') { e.preventDefault(); load(a.dataset.id); return; }
    var fd = new FormData(); fd.append('csrf', csrf); fd.append('id', a.dataset.id);
    if (navigator.sendBeacon) navigator.sendBeacon('notifications.php', fd);
  });
  setInterval(function () { if (!document.hidden) load(); }, 60000);
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
}


// ---------------------------------------------------------------------------------------------
// Form and display helpers used by the pages
// ---------------------------------------------------------------------------------------------

// <option> list from rows. $all = true adds a leading "All" option (value ""). $selected is compared as text.
function select_options(array $rows, string $valKey, string $labelKey, $selected = null, bool $all = false): string {
    $out = $all ? '<option value="">All</option>' : '';
    foreach ($rows as $r) {
        $v = (string)($r[$valKey] ?? '');
        $out .= '<option value="' . e($v) . '"' . ($selected !== null && $selected !== '' && (string)$selected === $v ? ' selected' : '') . '>' . e((string)($r[$labelKey] ?? '')) . '</option>';
    }
    return $out;
}

// Status filter dropdown: "All statuses" plus every status.
function status_options($selected = ''): string {
    $out = '<option value="">All statuses</option>';
    foreach (STATUSES as $k => $label) $out .= '<option value="' . e($k) . '"' . ((string)$selected === (string)$k ? ' selected' : '') . '>' . e($label) . '</option>';
    return $out;
}

// Where a physical record is kept: the storage (cabinet / holder / shelf) if set, otherwise the older cabinet-shelf-box-folder fields.
function location_text(array $r): string {
    $s = storage_label($r);
    if ($s) return $s;
    $parts = [];
    foreach (['cabinet' => 'Cabinet', 'shelf' => 'Shelf', 'box' => 'Box', 'folder' => 'Folder'] as $k => $label)
        if (isset($r[$k]) && trim((string)$r[$k]) !== '') $parts[] = $label . ' ' . trim((string)$r[$k]);
    return $parts ? implode(' / ', $parts) : '—';
}

// ---------------------------------------------------------------------------------------------
// Tabbed pages:  tabs_begin('Title', ['key' => 'Label', ...], $wide);  tab_open('key'); ... tab_close();  tabs_end();
// The first tab in the list opens by default; the address hash (#tab-key) or the last tab used on this page wins.
// ---------------------------------------------------------------------------------------------
function tabs_begin(string $label, array $tabs, bool $wide = false): void {
    echo '<div class="tabbed' . ($wide ? ' wide' : '') . '" data-first="' . e((string)array_key_first($tabs)) . '">';
    echo '<div class="tabbar noprint" role="tablist" aria-label="' . e($label) . '">';
    foreach ($tabs as $k => $t) echo '<a href="#tab-' . e((string)$k) . '" data-tab="' . e((string)$k) . '" role="tab">' . e($t) . '</a>';
    echo '</div>';
}
function tab_open(string $key): void { echo '<section class="tabpane" id="tab-' . e($key) . '">'; }
function tab_close(): void { echo '</section>'; }
function tabs_end(): void {
    echo '</div>';
    ?>
<script>
(function(){var w=document.querySelector('.tabbed');if(!w)return;w.classList.add('js');
var tabs=[].slice.call(w.querySelectorAll('.tabbar a')),panes=[].slice.call(w.querySelectorAll('.tabpane')),first=w.getAttribute('data-first'),sk='tab:'+location.pathname;
function show(id,save){var found=panes.some(function(p){return p.id==='tab-'+id;});if(!found)id=first;
panes.forEach(function(p){p.classList.toggle('on',p.id==='tab-'+id);});
tabs.forEach(function(a){var on=a.getAttribute('data-tab')===id;a.classList.toggle('on',on);a.setAttribute('aria-selected',on?'true':'false');});
if(save){try{sessionStorage.setItem(sk,id);}catch(e){}if(history.replaceState)history.replaceState(null,'','#tab-'+id);}}
tabs.forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();show(a.getAttribute('data-tab'),true);});});
var h=location.hash.replace('#tab-',''),s=null;try{s=sessionStorage.getItem(sk);}catch(e){}
show(h||s||first,false);})();
</script>
<?php
}