(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Mobile drawer */
  var menu = $('.menu-btn'), scrim = $('.scrim');
  function setNav(open) { document.body.classList.toggle('nav-open', open); if (menu) menu.setAttribute('aria-expanded', open); }
  if (menu) menu.addEventListener('click', function () { setNav(!document.body.classList.contains('nav-open')); });
  if (scrim) scrim.addEventListener('click', function () { setNav(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });


  /* Keep the active nav item in view */
  var on = $('nav a.on'); if (on && on.scrollIntoView) on.scrollIntoView({ block: 'nearest' });

  /* Dismissible flash messages (success auto-hides) */
  $$('.flash').forEach(function (f) {
    var x = document.createElement('button'); x.type = 'button'; x.className = 'x'; x.setAttribute('aria-label', 'Dismiss'); x.innerHTML = '&times;';
    function close() { f.classList.add('out'); setTimeout(function () { f.remove(); }, 380); }
    x.addEventListener('click', close); f.appendChild(x);
    if (!f.classList.contains('err') && !f.classList.contains('warn')) setTimeout(close, 6000);
  });

  /* Count-up on dashboard stats */
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduce) $$('.stat strong').forEach(function (el) {
    var end = parseInt(el.textContent.replace(/,/g, ''), 10);
    if (isNaN(end) || end < 2 || String(end) !== el.textContent.trim()) return;
    var t0 = null, dur = 700;
    (function step(t) { if (!t0) t0 = t; var p = Math.min((t - t0) / dur, 1); el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); })(performance.now());
  });

  /* Tables: live filter + click-to-sort */
  function cellText(tr, i) { var c = tr.children[i]; return c ? c.textContent.trim() : ''; }
  function cmp(a, b) {
    var na = parseFloat(a.replace(/,/g, '')), nb = parseFloat(b.replace(/,/g, ''));
    if (!isNaN(na) && !isNaN(nb) && /^[-\d.,\s]+$/.test(a + b)) return na - nb;
    var da = Date.parse(a), db = Date.parse(b);
    if (!isNaN(da) && !isNaN(db) && /\d{4}/.test(a + b)) return da - db;
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  }
  $$('.tw table').forEach(function (tbl) {
    var rows = $$('tr', tbl).filter(function (r) { return r.querySelector('td'); });
    var head = $$('tr', tbl).filter(function (r) { return r.querySelector('th'); })[0];
    if (!head || rows.length < 2 || rows.some(function (r) { return r.querySelector('form'); }) && rows.length < 2) return;
    var body = rows[0].parentNode;

    // Sorting
    $$('th', head).forEach(function (th, i) {
      if (!th.textContent.trim()) return;
      th.classList.add('sortable'); th.title = 'Click to sort'; th.tabIndex = 0;
      function sort() {
        var dir = th.classList.contains('asc') ? 'desc' : 'asc';
        $$('th', head).forEach(function (h) { h.classList.remove('asc', 'desc'); }); th.classList.add(dir);
        rows.slice().sort(function (a, b) { var r = cmp(cellText(a, i), cellText(b, i)); return dir === 'asc' ? r : -r; })
          .forEach(function (r) { body.appendChild(r); });
      }
      th.addEventListener('click', sort);
      th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); } });
    });

    // Filter box (only for longer tables)
    if (rows.length < 8) return;
    var wrap = tbl.closest('.tw'), bar = document.createElement('div'); bar.className = 'tfilter';
    bar.innerHTML = '<input type="search" placeholder="Filter this table…" aria-label="Filter table"><small></small>';
    wrap.parentNode.insertBefore(bar, wrap);
    var inp = $('input', bar), info = $('small', bar), none = document.createElement('tr');
    none.className = 'empty-hit hide'; none.innerHTML = '<td colspan="' + head.children.length + '">No matching rows.</td>'; body.appendChild(none);
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase().trim(), shown = 0;
      rows.forEach(function (r) { var m = !q || r.textContent.toLowerCase().indexOf(q) > -1; r.classList.toggle('hide', !m); if (m) shown++; });
      none.classList.toggle('hide', shown > 0);
      info.textContent = q ? shown + ' of ' + rows.length + ' shown' : '';
    });
  });

  /* Confirm dialog for destructive buttons / [data-confirm] */
  function confirmBox(msg, ok, okLabel) {
    var bg = document.createElement('div'); bg.className = 'dlg-bg'; bg.setAttribute('role', 'dialog'); bg.setAttribute('aria-modal', 'true');
    bg.innerHTML = '<div class="dlg"><h3>Please confirm</h3><p></p><div class="acts"><button type="button" class="alt c">Cancel</button><button type="button" class="danger o"></button></div></div>';
    $('p', bg).textContent = msg; $('.o', bg).textContent = okLabel || 'Confirm';
    function done(v) { document.removeEventListener('keydown', key); bg.remove(); if (v) ok(); }
    function key(e) { if (e.key === 'Escape') done(false); }
    $('.c', bg).onclick = function () { done(false); }; $('.o', bg).onclick = function () { done(true); };
    bg.addEventListener('click', function (e) { if (e.target === bg) done(false); });
    document.addEventListener('keydown', key); document.body.appendChild(bg); $('.c', bg).focus();
  }

  /* Forms: confirm + double-submit guard + loading state */
  $$('form').forEach(function (f) {
    if (f.method.toLowerCase() !== 'post' || f.hasAttribute('data-xhr')) return;
    var confirmed = false;
    f.addEventListener('submit', function (e) {
      var s = e.submitter;
      if (!confirmed && s && (s.classList.contains('danger') || s.hasAttribute('data-confirm'))) {
        e.preventDefault();
        var label = (s.textContent || 'Confirm').trim();
        confirmBox(s.getAttribute('data-confirm') || 'Are you sure you want to ' + label.toLowerCase() + ' this?', function () {
          confirmed = true; f.requestSubmit ? f.requestSubmit(s) : f.submit();
        }, label);
        return;
      }
      if (f.dataset.busy) { e.preventDefault(); return; }
      f.dataset.busy = '1';
      if (s) s.classList.add('loading'); // not disabled, so its name/value still post
      setTimeout(function () { delete f.dataset.busy; if (s) s.classList.remove('loading'); }, 15000);
    });
  });
  window.addEventListener('pageshow', function () { $$('button.loading').forEach(function (b) { b.classList.remove('loading'); }); $$('form').forEach(function (f) { delete f.dataset.busy; }); });

  /* Warn before leaving a half-filled form */
  var dirty = false;
  $$('main form[method="post"]:not([data-xhr])').forEach(function (f) {
    f.addEventListener('input', function () { dirty = true; });
    f.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* Auto-grow textareas */
  $$('textarea').forEach(function (t) {
    function g() { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 400) + 'px'; }
    t.addEventListener('input', g); g();
  });

  /* "/" focuses the first search/filter field */
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || /input|textarea|select/i.test(document.activeElement.tagName)) return;
    var t = $('main input[name="q"]') || $('.tfilter input') || $('main input[type="text"],main input[type="search"]');
    if (t) { e.preventDefault(); t.focus(); t.select(); }
  });

  /* Back to top */
  var up = document.createElement('button'); up.type = 'button'; up.className = 'totop'; up.setAttribute('aria-label', 'Back to top'); up.innerHTML = '&uarr;';
  up.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); document.body.appendChild(up);
  var tick = false;
  window.addEventListener('scroll', function () { if (tick) return; tick = true; requestAnimationFrame(function () { up.classList.toggle('show', window.scrollY > 500); tick = false; }); }, { passive: true });
})();

/* Camera capture: live preview (needs https or localhost), with the device camera app as fallback */
window.DARCamera = (function () {
  var dlg, video, msg, flip, snap, stream = null, facing = 'environment', cb = null, input = null, multi = false, shots = 0, tm = null;
  function stop() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } if (video) video.srcObject = null; }
  function deliver(f) { var fn = cb; cb = null; if (dlg && dlg.open) dlg.close(); if (fn && f) fn(f); }
  function fallbackInput() {
    if (!input) {
      input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*'; input.setAttribute('capture', 'environment'); input.hidden = true;
      input.addEventListener('change', function () { var f = input.files[0]; input.value = ''; if (f) deliver(f); });
      document.body.appendChild(input);
    }
    return input;
  }
  function note(t, withApp) {
    msg.textContent = t; msg.hidden = !t;
    var b = dlg.querySelector('.cam-app'); b.hidden = !withApp;
  }
  function build() {
    dlg = document.createElement('dialog'); dlg.className = 'camdlg';
    dlg.innerHTML = '<div class="cam-wrap"><video autoplay playsinline muted></video><p class="cam-msg" hidden></p></div>' +
      '<div class="cam-acts"><button type="button" class="alt cam-x">Cancel</button><button type="button" class="alt cam-flip">Switch camera</button>' +
      '<button type="button" class="alt cam-app" hidden>Use camera app / photo</button><button type="button" class="cam-snap">Capture</button></div>';
    document.body.appendChild(dlg);
    video = dlg.querySelector('video'); msg = dlg.querySelector('.cam-msg'); flip = dlg.querySelector('.cam-flip'); snap = dlg.querySelector('.cam-snap');
    dlg.querySelector('.cam-x').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('close', function () { stop(); });
    flip.addEventListener('click', function () { facing = facing === 'environment' ? 'user' : 'environment'; start(); });
    dlg.querySelector('.cam-app').addEventListener('click', function () { dlg.close(); fallbackInput().click(); });
    snap.addEventListener('click', function () {
      if (!video.videoWidth) return;
      var c = document.createElement('canvas'); c.width = video.videoWidth; c.height = video.videoHeight;
      c.getContext('2d').drawImage(video, 0, 0);
      c.toBlob(function (b) {
        if (!b) { note('Could not capture the image. Try again.', true); return; }
        var d = new Date(), z = function (n) { return (n < 10 ? '0' : '') + n; };
        var file = new File([b], 'scan-' + d.getFullYear() + z(d.getMonth() + 1) + z(d.getDate()) + '-' + z(d.getHours()) + z(d.getMinutes()) + z(d.getSeconds()) + '.jpg', { type: 'image/jpeg' });
        if (!multi) { deliver(file); return; }
        // Several pages of one file: keep the camera open, hand over each photo, and let the person press Done at the end
        shots++; dlg.querySelector('.cam-x').textContent = 'Done (' + shots + ')';
        note('Page ' + shots + ' captured. Place the next page, or press Done.', false);
        clearTimeout(tm); tm = setTimeout(function () { note('', false); }, 2600);
        if (cb) cb(file);
      }, 'image/jpeg', 0.92);
    });
  }
  function start() {
    stop(); note('Starting camera\u2026', false); snap.disabled = true;
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing }, width: { ideal: 2560 }, height: { ideal: 1440 } }, audio: false })
      .then(function (s) { stream = s; video.srcObject = s; note('', false); snap.disabled = false; return video.play && video.play(); })
      .catch(function (e) {
        var n = e && e.name, t = 'Could not start the camera.';
        if (n === 'NotAllowedError' || n === 'SecurityError') t = 'Camera access is blocked. Allow Camera for this site in the browser address bar or site settings, then try again.';
        else if (n === 'NotFoundError' || n === 'OverconstrainedError') t = 'No camera was found on this device.';
        else if (n === 'NotReadableError') t = 'The camera is being used by another app. Close it and try again.';
        note(t, true);
      });
  }
  return {
    open: function (callback, o) {
      cb = callback; multi = !!(o && o.multi); shots = 0;
      var live = window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
      if (!live) { fallbackInput().click(); return; }   // http://192.168.x.x etc: browsers only allow live preview on https or localhost
      if (!dlg) build();
      dlg.querySelector('.cam-x').textContent = multi ? 'Done' : 'Cancel';
      dlg.showModal(); start();
    }
  };
})();

/* Scanner capture: network scanner (eSCL, e.g. Epson) or a file from the scan-to-folder inbox. Returns a File, like DARCamera. */
window.DARScanner = (function () {
  var dlg, body, info = null, cb = null, tab = 'net', busy = false, multi = false, added = 0;
  var KEY = 'dar_scanner_prefs';
  function csrf() { var i = document.querySelector('input[name="csrf"]'); return i ? i.value : ''; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function prefs() { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
  function savePrefs(p) { try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) {} }
  function size(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function closeLabel() { return added ? 'Done (' + added + ')' : (multi ? 'Done' : 'Cancel'); }
  function deliver(blob, name) {
    var f = new File([blob], name, { type: blob.type });
    if (multi) { added++; if (cb) cb(f); return; }   // several pages of one file: the dialog stays open for the next page
    var fn = cb; cb = null; if (dlg.open) dlg.close();
    if (fn) fn(f);
  }
  // After a page was handed over in multi-page mode: redraw the dialog and tell the person what to do next
  function pageAdded(text) {
    busy = false; render();
    var s = dlg.querySelector('.sc-status'); if (!s) return;
    s.hidden = false; s.className = 'sc-status ok'; s.textContent = text;
  }
  function setBusy(on, text) {
    busy = on; var s = dlg.querySelector('.sc-status');
    s.hidden = !text; s.className = 'sc-status' + (on ? ' busy' : ' bad'); s.textContent = text || '';
    dlg.querySelectorAll('button,select').forEach(function (el) { if (!el.classList.contains('sc-x')) el.disabled = on; });
  }
  function post(data) {
    var fd = new FormData(); fd.append('csrf', csrf());
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('scanner.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      var ct = r.headers.get('Content-Type') || '';
      if (ct.indexOf('application/json') === 0) return r.json().then(function (j) { throw new Error(j.msg || 'The scanner request failed.'); });
      if (!r.ok) throw new Error('The scanner request failed (HTTP ' + r.status + ').');
      var nm = r.headers.get('X-Scan-Name') || 'scan.pdf';
      return r.blob().then(function (b) { return { blob: b, name: nm }; });
    });
  }
  function opts(list, sel) { return list.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(sel) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join(''); }

  function render() {
    var p = prefs(), tabs = '';
    var hasNet = info.scanners.length > 0, hasFolder = info.folder;
    if (!hasNet && !hasFolder) {
      body.innerHTML = '<p class="sc-empty"><strong>No scanner is set up yet.</strong><br>An administrator can add a network scanner or a scan folder under <em>Settings &rarr; Scanners</em>. Until then, use <em>Upload</em> or the camera.</p>';
      return;
    }
    if (tab === 'net' && !hasNet) tab = 'folder'; if (tab === 'folder' && !hasFolder) tab = 'net';
    tabs = '<div class="sc-tabs">' + (hasNet ? '<button type="button" data-t="net" class="' + (tab === 'net' ? 'on' : '') + '">Network scanner</button>' : '') +
      (hasFolder ? '<button type="button" data-t="folder" class="' + (tab === 'folder' ? 'on' : '') + '">Scan folder (USB)</button>' : '') + '</div>';
    var h = tabs;
    if (tab === 'net') {
      h += '<div class="sc-grid"><label>Scanner<select name="scanner">' + opts(info.scanners.map(function (s) { return [s.id, s.name]; }), p.scanner) + '</select></label>' +
        '<label>Load pages<select name="source">' + opts([['platen', 'Flatbed glass (one page)'], ['feeder', 'Document feeder (many pages)']], p.source || 'platen') + '</select></label>' +
        '<label>Color<select name="color">' + opts([['color', 'Color'], ['gray', 'Grayscale (smaller file)']], p.color || 'color') + '</select></label>' +
        '<label>Resolution<select name="dpi">' + opts([[100, '100 dpi'], [150, '150 dpi'], [200, '200 dpi'], [300, '300 dpi'], [400, '400 dpi'], [600, '600 dpi']], p.dpi || info.dpi) + '</select></label>' +
        '<label>Paper size<select name="paper">' + opts([['a4', info.paper[0] || 'A4'], ['letter', info.paper[1]], ['long', info.paper[2]], ['legal', info.paper[3]]], p.paper || 'a4') + '</select></label></div>' +
        '<p class="muted sc-note">Place the document, then press Scan. The scanner will move for about 5 to 60 seconds. Use 200 dpi for normal text documents.</p>' +
        '<div class="sc-acts"><button type="button" class="alt sc-x">' + closeLabel() + '</button><button type="button" class="sc-go">' + (multi ? 'Scan next page' : 'Scan') + '</button></div>';
    } else {
      h += (multi ? '<p class="muted sc-note">Each page you scan or pick is added to the same file. Press Done when every page is in.</p>' : '') + '<p class="muted sc-note">Scan with the Epson software and save into the scan folder. Pick the file below to attach it. The file moves to <code>_imported</code> after you pick it.</p><div class="sc-files">';
      if (!info.files.length) h += '<p class="sc-empty">No files in the scan folder yet.</p>';
      info.files.forEach(function (f) { h += '<div class="sc-file"><div><strong>' + esc(f.name) + '</strong><span class="muted">' + esc(f.time) + ' &middot; ' + size(f.size) + '</span></div><button type="button" class="sm sc-pick" data-n="' + esc(f.name) + '">Use</button></div>'; });
      h += '</div><div class="sc-acts"><button type="button" class="alt sc-x">' + closeLabel() + '</button><button type="button" class="alt sc-refresh">Refresh list</button></div>';
    }
    h += '<p class="sc-status" hidden></p>';
    body.innerHTML = h;
  }
  function load() {
    body.innerHTML = '<p class="sc-empty">Checking scanners&hellip;</p>';
    fetch('scanner.php', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); })
      .then(function (j) { info = j; render(); })
      .catch(function () { body.innerHTML = '<p class="sc-empty">Could not load the scanner options. Refresh the page and try again.</p>'; });
  }
  function build() {
    dlg = document.createElement('dialog'); dlg.className = 'sdlg scdlg';
    dlg.innerHTML = '<div class="dpad"><h3>Scan a document</h3><div class="sc-body"></div></div>';
    document.body.appendChild(dlg); body = dlg.querySelector('.sc-body');
    dlg.addEventListener('cancel', function (e) { if (busy) e.preventDefault(); });
    dlg.addEventListener('click', function (e) {
      var t = e.target;
      if (t === dlg && !busy) { dlg.close(); return; }
      if (t.closest('.sc-x') && !busy) { dlg.close(); return; }
      var tb = t.closest('.sc-tabs button'); if (tb && !busy) { tab = tb.dataset.t; render(); return; }
      if (t.closest('.sc-refresh') && !busy) { load(); return; }
      var pk = t.closest('.sc-pick');
      if (pk && !busy) {
        setBusy(true, 'Opening ' + pk.dataset.n + '…');
        post({ action: 'folder_get', name: pk.dataset.n }).then(function (r) {
          busy = false; deliver(r.blob, r.name);
          if (multi) { info.files = info.files.filter(function (x) { return x.name !== pk.dataset.n; }); pageAdded('Page ' + added + ' added. Pick the next page, or press Done.'); }
        }).catch(function (er) { setBusy(false, er.message); });
        return;
      }
      if (t.closest('.sc-go') && !busy) {
        var d = {}; ['scanner', 'source', 'color', 'dpi', 'paper'].forEach(function (k) { d[k] = body.querySelector('[name=' + k + ']').value; });
        savePrefs(d); d.action = 'scan'; if (multi) d.format = 'jpeg';   // pictures can always be joined into one PDF on the server
        setBusy(true, d.source === 'feeder' ? 'Scanning from the feeder… please wait, do not open the cover.' : 'Scanning… please wait, do not open the cover.');
        post(d).then(function (r) {
          busy = false; deliver(r.blob, r.name);
          if (multi) pageAdded('Page ' + added + ' added. Place the next page and press Scan next page, or press Done.');
        }).catch(function (er) { setBusy(false, er.message); });
      }
    });
  }
  return {
    open: function (callback, o) {
      cb = callback; multi = !!(o && o.multi); added = 0; if (!dlg) build();
      busy = false; dlg.showModal(); load();
    }
  };
})();

/* Page collector: one physical file can have several pages. The pages are listed in order (thumbnail, move up/down, remove) and mirrored
   into a hidden <input type="file" name="scan[]" multiple>, so the form posts every page and the server joins them into ONE PDF. */
window.DARPages = (function () {
  function size(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function ext(n) { return (String(n).split('.').pop() || '').toLowerCase(); }
  function mk(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  /* host: element to fill. o: { allowed: ['pdf','jpg'..], maxBytes, maxMb, maxPages, form: id of the form the pages belong to (optional),
     onChange(count), onError(message) } */
  function mount(host, o) {
    var pages = [], max = o.maxPages || 20;
    host.classList.add('pgw');
    host.innerHTML =
      '<label class="drop pg-drop"><input type="file" class="pg-pick" multiple>' +
      '<span class="dz-big">Drop the pages here</span>' +
      '<span class="dz-small"></span></label>' +
      '<div class="row pg-tools"><button type="button" class="alt sm pg-scn">Use scanner (Epson)</button> ' +
      '<button type="button" class="alt sm pg-cam">Use camera (phone / tablet)</button></div>' +
      '<input type="file" name="scan[]" class="pg-target" multiple hidden>' +
      '<div class="pg-box" hidden><div class="pg-head"><strong>Pages of this file</strong><span class="pg-sum"></span>' +
      '<button type="button" class="alt sm pg-clear">Remove all</button></div><ol class="pg-list"></ol><p class="pg-hint muted"></p></div>';
    var drop = host.querySelector('.pg-drop'), pick = host.querySelector('.pg-pick'), target = host.querySelector('.pg-target'),
        box = host.querySelector('.pg-box'), list = host.querySelector('.pg-list'), sum = host.querySelector('.pg-sum'), hint = host.querySelector('.pg-hint');
    pick.accept = '.' + o.allowed.join(',.');
    host.querySelector('.dz-small').textContent = 'or click to browse \u00b7 ' + o.allowed.join(', ') + ' \u00b7 select several files at once if the document has more than one page \u00b7 max ' + o.maxMb + ' MB each';
    if (o.form) target.setAttribute('form', o.form);

    function sync() {
      try { var dt = new DataTransfer(); pages.forEach(function (p) { dt.items.add(p.file); }); target.files = dt.files; } catch (e) {}
    }
    function drawThumbs() {
      list.textContent = '';
      pages.forEach(function (p, i) {
        var li = mk('li', 'pg-item');
        li.appendChild(mk('span', 'pg-n', String(i + 1)));
        if (p.url) { var im = new Image(); im.src = p.url; im.alt = ''; im.className = 'pg-thumb'; li.appendChild(im); }
        else li.appendChild(mk('span', 'pg-thumb pg-doc', ext(p.file.name).toUpperCase()));
        var nm = mk('span', 'pg-name'); nm.appendChild(mk('strong', null, p.file.name)); nm.appendChild(mk('small', 'muted', size(p.file.size))); li.appendChild(nm);
        var b = mk('span', 'pg-btns');
        [['up', '\u2191', 'Move page ' + (i + 1) + ' up', i === 0], ['down', '\u2193', 'Move page ' + (i + 1) + ' down', i === pages.length - 1], ['del', '\u2715', 'Remove page ' + (i + 1), false]].forEach(function (d) {
          var bt = mk('button', 'alt sm', d[1]); bt.type = 'button'; bt.dataset.a = d[0]; bt.dataset.i = String(i); bt.title = d[2]; bt.setAttribute('aria-label', d[2]); bt.disabled = d[3]; b.appendChild(bt);
        });
        li.appendChild(b); list.appendChild(li);
      });
      var n = pages.length, total = pages.reduce(function (a, p) { return a + p.file.size; }, 0);
      box.hidden = !n; sum.textContent = n + (n === 1 ? ' page' : ' pages') + ' \u00b7 ' + size(total);
      var hasPdf = pages.some(function (p) { return ext(p.file.name) === 'pdf'; });
      hint.textContent = n === 1 ? 'This file has one page so far. Add the other pages now if the document has more.'
        : 'All ' + n + ' pages are saved together as one PDF, in the order shown.' + (hasPdf ? ' Joining PDF files with pictures needs extra software on the server; if saving fails, upload every page as an image, or as one PDF.' : '');
    }
    function changed() { drawThumbs(); sync(); if (o.onChange) o.onChange(pages.length); }
    function drop1(p) { if (p.url) URL.revokeObjectURL(p.url); }

    function add(files) {
      var bad = [], full = false;
      Array.prototype.forEach.call(files, function (f) {
        var e = ext(f.name);
        if (o.allowed.indexOf(e) < 0) bad.push(f.name + ': type not allowed (allowed: ' + o.allowed.join(', ') + ')');
        else if (f.size > o.maxBytes) bad.push(f.name + ': ' + size(f.size) + ' is over the ' + o.maxMb + ' MB limit');
        else if (pages.length >= max) full = true;
        else pages.push({ file: f, url: /^image\/(png|jpe?g|gif|webp)$/.test(f.type) ? URL.createObjectURL(f) : null });
      });
      if (full) bad.push('A file can have up to ' + max + ' pages here. The extra pages were not added.');
      changed();
      if (o.onError) o.onError(bad.join(' \u00b7 '));
    }

    pick.addEventListener('change', function () { add(pick.files); pick.value = ''; });
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
    host.querySelector('.pg-scn').addEventListener('click', function () { if (window.DARScanner) DARScanner.open(function (f) { add([f]); }, { multi: true }); });
    host.querySelector('.pg-cam').addEventListener('click', function () { if (window.DARCamera) DARCamera.open(function (f) { add([f]); }, { multi: true }); });
    host.querySelector('.pg-clear').addEventListener('click', function () { api.clear(); });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-a]'); if (!b || b.disabled) return;
      var i = parseInt(b.dataset.i, 10), a = b.dataset.a;
      if (a === 'del') drop1(pages.splice(i, 1)[0]);
      else { var j = a === 'up' ? i - 1 : i + 1; if (j < 0 || j >= pages.length) return; var t = pages[i]; pages[i] = pages[j]; pages[j] = t; }
      changed();
      if (o.onError) o.onError('');
    });

    var api = {
      add: add,
      clear: function () { pages.forEach(drop1); pages = []; changed(); },
      count: function () { return pages.length; },
      files: function () { return pages.map(function (p) { return p.file; }); },
      first: function () { return pages[0] || null; },   // { file, url } so the caller can show a name and thumbnail
      total: function () { return pages.reduce(function (a, p) { return a + p.file.size; }, 0); }
    };
    changed();
    return api;
  }
  return { mount: mount, size: size };
})();