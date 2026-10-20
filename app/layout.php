<?php
function nav_items(): array {
    // [label, file, permission]
    return [
        ['Dashboard', 'index.php', 'dashboard'],
        ['Inventory', 'inventory.php', 'inventory.view'],
        ['Tracking', 'tracking.php', 'inventory.view'],
        ['Classification', 'classification.php', 'inventory.view'],
        ['Verification', 'verification.php', 'verify'],
        ['Search', 'search.php', 'search'],
        ['QR / Barcode', 'scan.php', 'qr'],
        ['Custody', 'custody.php', 'custody.view'],
        ['Retention', 'retention.php', 'retention.request'],
        ['Reports', 'reports.php', 'reports.view'],
        ['Tasks', 'tasks.php', 'dashboard'],
        ['Users', 'users.php', 'users.manage'],
        ['Audit trail', 'audit.php', 'audit.view'],
        ['Settings', 'settings.php', 'settings.manage'],
        ['Backup', 'backup.php', 'backup.manage'],
    ];
}

function page_top(string $title): void {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $unread = !empty($_SESSION['uid']) ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$_SESSION['uid']]) : 0;
    $popNotes = !empty($_SESSION['uid']) ? all('SELECT id,message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY is_read, id DESC LIMIT 15', [$_SESSION['uid']]) : [];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | ' . e(cfg()['app']['name']) . '</title><link rel="stylesheet" href="assets/style.css?v=' . @filemtime(__DIR__ . '/../public/assets/style.css') . '"><script src="assets/app.js?v=' . @filemtime(__DIR__ . '/../public/assets/app.js') . '" defer></script></head><body>';
    echo '<header class="top"><div class="brand">' . (!empty($_SESSION['uid']) ? '<button type="button" class="menu-btn" aria-label="Menu" aria-expanded="false">&#9776;</button>' : '') . '<img class="brandlogo" src="assets/darlogo.png" alt="DAR logo"><div><strong>' . e(cfg()['app']['name']) . '</strong><small>Document Management and Tracking System</small></div></div>';
    if (!empty($_SESSION['uid'])) {
        echo '<div class="who"><div class="notif" data-csrf="' . e($_SESSION['csrf'] ?? '') . '"><button type="button" class="bell nbtn" title="Notifications" aria-haspopup="true" aria-expanded="false" aria-label="Notifications' . ($unread ? ", $unread unread" : '') . '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>' . ($unread ? '<span class="count">' . ($unread > 99 ? '99+' : $unread) . '</span>' : '') . '</button>';
        echo '<div class="npop" hidden role="dialog" aria-label="Notifications"><div class="nhead"><strong>Notifications</strong><button type="button" class="nall"' . ($unread ? '' : ' hidden') . '>Mark all as read</button></div><div class="nlist">';
        foreach ($popNotes as $n) {
            $inner = '<span class="ndot"></span><span class="ntxt">' . e($n['message']) . '<small>' . e(fmt_dt($n['created_at'])) . '</small></span>';
            $cls = 'nitem' . ($n['is_read'] ? '' : ' unread');
            echo $n['link'] ? '<a class="' . $cls . '" data-id="' . (int)$n['id'] . '" href="' . e($n['link']) . '">' . $inner . '</a>'
                            : '<div class="' . $cls . '" data-id="' . (int)$n['id'] . '">' . $inner . '</div>';
        }
        echo '<p class="nempty muted"' . ($popNotes ? ' hidden' : '') . '>You have no notifications.</p></div></div></div> ';
        echo <<<'JS'
<script>(function(){
var w=document.querySelector('.notif');if(!w)return;
var b=w.querySelector('.nbtn'),p=w.querySelector('.npop'),al=w.querySelector('.nall'),list=w.querySelector('.nlist');
function o(x){p.hidden=!x;b.setAttribute('aria-expanded',x?'true':'false');if(x)load();}
function cnt(n){var c=b.querySelector('.count');if(n>0){if(!c){c=document.createElement('span');c.className='count';b.appendChild(c);}c.textContent=n>99?'99+':n;}else if(c){c.remove();}if(al)al.hidden=n<1;}
function draw(items){
  list.textContent='';
  items.forEach(function(n){
    var el=document.createElement(n.link&&/^[\w\-.\/]+\.php/.test(n.link)?'a':'div');
    el.className='nitem'+(n.is_read?'':' unread');el.setAttribute('data-id',n.id);
    if(el.tagName==='A')el.href=n.link;
    var d=document.createElement('span');d.className='ndot';
    var t=document.createElement('span');t.className='ntxt';t.appendChild(document.createTextNode(n.message));
    var s=document.createElement('small');s.textContent=n.when;t.appendChild(s);
    el.appendChild(d);el.appendChild(t);list.appendChild(el);
  });
  if(!items.length){var e=document.createElement('p');e.className='nempty muted';e.textContent='You have no notifications.';list.appendChild(e);}
}
function apply(j){if(j&&j.ok){cnt(j.unread);draw(j.items);}}
function load(){return fetch('notifications.php',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(apply).catch(function(){});}
function mark(id){var d=new URLSearchParams({csrf:w.getAttribute('data-csrf'),id:id});return fetch('notifications.php',{method:'POST',body:d,credentials:'same-origin',keepalive:true}).then(function(r){return r.json();}).then(function(j){if(j&&j.ok)cnt(j.unread);}).catch(function(){});}
b.addEventListener('click',function(e){e.stopPropagation();o(p.hidden);});
p.addEventListener('click',function(e){e.stopPropagation();
  var it=e.target.closest('.nitem');
  if(it&&it.classList.contains('unread')){it.classList.remove('unread');mark(it.getAttribute('data-id'));}
});
if(al)al.addEventListener('click',function(){Array.prototype.forEach.call(list.querySelectorAll('.nitem'),function(it){it.classList.remove('unread');});mark(0);});
document.addEventListener('click',function(){o(false);});
document.addEventListener('keydown',function(e){if(e.key==='Escape')o(false);});
/* New notifications show up on their own (every 30 s, only while the tab is visible) */
setInterval(function(){if(!document.hidden)load();},30000);
document.addEventListener('visibilitychange',function(){if(!document.hidden)load();});
})();</script>
JS;
        echo "\n";
        echo '<span>' . e($_SESSION['name']) . ' (' . e(ROLES[$_SESSION['role']] ?? '') . ')</span> ';
        echo '<a href="account.php" class="bell ibtn" title="Account" aria-label="Account"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a> ';
        echo '<a href="logout.php" class="bell ibtn out" title="Sign out" aria-label="Sign out"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></a></div>';
    }
    echo '</header><div class="shell">';
    if (!empty($_SESSION['uid'])) {
        echo '<nav>';
        foreach (nav_items() as [$label, $file, $perm]) {
            if (can($perm)) echo '<a href="' . $file . '"' . ($file === $cur ? ' class="on"' : '') . '>' . e($label) . '</a>';
        }
        echo '</nav><div class="scrim"></div>';
    }
    echo '<main><h1>' . e($title) . '</h1>';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="flash ' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
}
function page_bottom(): void { echo '</main></div></body></html>'; }

// Reusable <select> builders
function select_options(array $rows, string $valueKey, string $labelKey, $selected = null, bool $blank = false): string {
    $h = $blank ? '<option value="">All</option>' : '';
    foreach ($rows as $r) {
        $h .= '<option value="' . e($r[$valueKey]) . '"' . ((string)$selected === (string)$r[$valueKey] ? ' selected' : '') . '>' . e($r[$labelKey]) . '</option>';
    }
    return $h;
}
function status_options($selected = '', bool $blank = true): string {
    $h = $blank ? '<option value="">All</option>' : '';
    foreach (STATUSES as $k => $v) $h .= '<option value="' . $k . '"' . ($selected === $k ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h;
}
function location_text(array $r): string {
    if ($l = storage_label($r)) return $l;   // admin-defined storage (cabinet no. + category, or CODE-NO such as FD-2)
    $p = array_filter([!empty($r['cabinet']) ? 'Cabinet ' . $r['cabinet'] : '', !empty($r['shelf']) ? 'Shelf ' . $r['shelf'] : '',
                       !empty($r['box']) ? 'Box ' . $r['box'] : '', !empty($r['folder']) ? 'Folder ' . $r['folder'] : '']);
    return $p ? implode(' / ', $p) : 'Not recorded';
}
/* Tabbed page layout, same look as System configuration (settings.php).
   tabs_begin('Label', ['id' => 'Tab name', ...]); tab_open('id'); ...; tab_close(); ... tabs_end(); */
function tabs_begin(string $label, array $tabs, bool $wide = false): void {
    echo '<div class="tabbed' . ($wide ? ' wide' : '') . '"><div class="tabbar noprint" role="tablist" aria-label="' . e($label) . '">';
    foreach ($tabs as $id => $name) echo '<a href="#tab-' . e($id) . '" data-tab="' . e($id) . '" role="tab">' . e($name) . '</a>';
    echo '</div>';
}
function tab_open(string $id): void { echo '<section class="tabpane" id="tab-' . e($id) . '">'; }
function tab_close(): void { echo '</section>'; }
function tabs_end(): void {
    echo <<<'HTML'
</div>
<script>
(function(){var w=document.querySelector('.tabbed');if(!w)return;w.classList.add('js');
var key='tab:'+location.pathname,tabs=[].slice.call(w.querySelectorAll('.tabbar a')),panes=[].slice.call(w.querySelectorAll('.tabpane'));
function show(id,save){var found=panes.some(function(p){return p.id==='tab-'+id;});if(!found)id=tabs[0].getAttribute('data-tab');
panes.forEach(function(p){p.classList.toggle('on',p.id==='tab-'+id);});
tabs.forEach(function(a){var on=a.getAttribute('data-tab')===id;a.classList.toggle('on',on);a.setAttribute('aria-selected',on?'true':'false');});
if(save){try{sessionStorage.setItem(key,id);}catch(e){}if(history.replaceState)history.replaceState(null,'','#tab-'+id);}}
tabs.forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();show(a.getAttribute('data-tab'),true);});});
var h=location.hash.replace('#tab-',''),s=null;try{s=sessionStorage.getItem(key);}catch(e){}
show(h||s||'',false);})();
</script>
HTML;
}