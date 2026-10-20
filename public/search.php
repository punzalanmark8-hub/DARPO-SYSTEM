<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('search');
$f = ['q' => get('q'), 'text' => get('text'), 'division_id' => (int)get('division_id'), 'category_id' => (int)get('category_id'), 'status' => get('status'),
      'access' => get('access'), 'year' => (int)get('year'), 'cabinet' => get('cabinet'), 'shelf' => get('shelf'), 'box' => get('box')];
[$vw, $vp] = visible_where('r');
$where = [$vw]; $par = $vp;
if ($f['q'] !== '') { $where[] = '(r.title LIKE ? OR r.ref_no LIKE ? OR r.inventory_no LIKE ?)'; array_push($par, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }
if ($f['text'] !== '') { $where[] = 'EXISTS (SELECT 1 FROM digital_files x WHERE x.record_id=r.id AND x.is_current=1 AND x.ocr_text LIKE ?)'; $par[] = "%{$f['text']}%"; }
if ($f['division_id']) { $where[] = 'r.division_id=?'; $par[] = $f['division_id']; }
if ($f['category_id']) { $where[] = 'r.category_id=?'; $par[] = $f['category_id']; }
if (isset(STATUSES[$f['status']])) { $where[] = 'r.status=?'; $par[] = $f['status']; }
if (in_array($f['access'], ['open', 'restricted'], true)) { $where[] = 'r.access_level=?'; $par[] = $f['access']; }
if ($f['year']) { $where[] = 'r.doc_year=?'; $par[] = $f['year']; }
foreach (['cabinet', 'shelf', 'box'] as $k) if ($f[$k] !== '') { $where[] = "r.$k=?"; $par[] = $f[$k]; }
$sql = 'SELECT r.*, d.name division, c.name category, (SELECT id FROM digital_files WHERE record_id=r.id AND is_current=1 LIMIT 1) fid
        FROM records r JOIN divisions d ON d.id=r.division_id JOIN categories c ON c.id=r.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.doc_year DESC, r.id DESC LIMIT 500';
$rows = all($sql, $par);
$searched = array_filter($f);
if ($searched) audit('search', null, null, json_encode($searched, JSON_UNESCAPED_UNICODE));
if (get('export') === '1' && can('search.export')) {
    audit('search_export', null, null, count($rows) . ' rows');
    csv_download('search-results.csv', ['Inventory no', 'Title', 'Reference', 'Division', 'Type', 'Year', 'Location', 'Status', 'Access'],
        array_map(fn($r) => [$r['inventory_no'], $r['title'], $r['ref_no'], $r['division'], $r['category'], $r['doc_year'], location_text($r), status_label($r['status']), $r['access_level']], $rows));
}
$divs = all('SELECT id,name FROM divisions WHERE is_active=1 ORDER BY name'); $cats = all('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name');
page_top('Search and retrieval');
?>
<?php $advN = count(array_filter([$f['text'], $f['division_id'], $f['category_id'], $f['status'], $f['access'], $f['year']])); ?>
<form class="trkbar" method="get" role="search">
  <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search title, reference or inventory no." aria-label="Search">
  <details class="srfilters"><summary class="btn alt">Filters<?php if ($advN): ?> <span class="n"><?= $advN ?></span><?php endif; ?></summary>
    <div class="srpanel">
      <label class="span2">Text inside scans (OCR) <input name="text" value="<?= e($f['text']) ?>"></label>
      <label>Division <select name="division_id"><?= select_options($divs, 'id', 'name', $f['division_id'], true) ?></select></label>
      <label>Category <select name="category_id"><?= select_options($cats, 'id', 'name', $f['category_id'], true) ?></select></label>
      <label>Status <select name="status"><?= status_options($f['status']) ?></select></label>
      <label>Access <select name="access"><option value="">All</option><option value="open" <?= $f['access'] === 'open' ? 'selected' : '' ?>>Open</option><option value="restricted" <?= $f['access'] === 'restricted' ? 'selected' : '' ?>>Restricted</option></select></label>
      <label>Year <input type="number" name="year" value="<?= $f['year'] ?: '' ?>"></label>
      <div class="span2 sract"><a class="btn alt" href="search.php">Clear</a><button>Apply</button></div>
    </div>
  </details>
  <button>Search</button>
  <?php if (can('search.export')): ?><button class="alt" name="export" value="1">Export CSV</button><?php endif; ?>
  <input type="hidden" name="cabinet" value="<?= e($f['cabinet']) ?>"><input type="hidden" name="shelf" value="<?= e($f['shelf']) ?>"><input type="hidden" name="box" value="<?= e($f['box']) ?>">
</form>
<script>
document.addEventListener('click', function (e) { var d = document.querySelector('.srfilters[open]'); if (d && !d.contains(e.target)) d.removeAttribute('open'); });
</script>
<div class="card tw"><p class="muted"><?= count($rows) ?> result(s). You only see records your role may access. OCR text search works for scans processed with pdftotext or tesseract on the server.</p>
<table><tr><th>Inventory no.</th><th>Title</th><th>Reference</th><th>Division</th><th>Year</th><th>Physical location</th><th>Status</th><th>Access</th><th>Digital copy</th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="record.php?id=<?= $r['id'] ?>"><?= e($r['inventory_no']) ?></a></td><td><?= e($r['title']) ?></td><td><?= e($r['ref_no'] ?? '') ?></td>
<td><?= e($r['division']) ?></td><td><?= $r['doc_year'] ?></td><td><?= e(location_text($r)) ?> <?= flag_badge($r['condition_flag']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= access_badge($r['access_level']) ?></td>
<td><?= $r['fid'] ? '<a target="_blank" href="download.php?id=' . $r['fid'] . '&mode=view">Open</a>' : '<span class="muted">None</span>' ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="muted">No results. Try fewer filters.</td></tr><?php endif; ?></table></div>
<?php page_bottom();