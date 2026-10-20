<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('dashboard');
refresh_overdue();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'create' && can('tasks.assign')) {
        $to = one('SELECT * FROM users WHERE id=? AND is_active=1', [(int)post('assigned_to')]);
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('due_date')) ? post('due_date') : null;
        if (!$to || post('title') === '') flash('err', 'Task title and assignee are required.');
        else {
            $id = insert('tasks', ['title' => post('title'), 'details' => post('details') ?: null, 'record_id' => (int)post('record_id') ?: null, 'assigned_to' => $to['id'],
                'division_id' => $to['division_id'], 'due_date' => $due, 'created_by' => $_SESSION['uid']]);
            if ((int)$to['id'] !== (int)$_SESSION['uid']) notify((int)$to['id'], 'New task: ' . post('title') . ($due ? " (due $due)" : ''), 'tasks.php');
            audit('task_assigned', 'task', $id, post('title') . ' -> ' . $to['username']);
            flash('ok', 'Task assigned.');
        }
    } elseif ($act === 'done') {
        q("UPDATE tasks SET status='done', completed_at=NOW() WHERE id=? AND (assigned_to=? OR ?=1)", [(int)post('id'), $_SESSION['uid'], can('tasks.assign') ? 1 : 0]);
        flash('ok', 'Task completed.');
    } elseif ($act === 'read') {
        q('UPDATE notifications SET is_read=1 WHERE user_id=? AND (id=? OR ?=0)', [$_SESSION['uid'], (int)post('id'), (int)post('id')]);
    }
    redirect('tasks.php');
}
$tasks = all("SELECT t.*, u.full_name assignee, r.inventory_no FROM tasks t JOIN users u ON u.id=t.assigned_to LEFT JOIN records r ON r.id=t.record_id
              WHERE t.status='open' AND (t.assigned_to=? OR ?=1) ORDER BY t.due_date IS NULL, t.due_date LIMIT 100", [$_SESSION['uid'], can('tasks.assign') ? 1 : 0]);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$notes = !$isAdmin ? [] : all('SELECT * FROM notifications WHERE user_id=? ORDER BY is_read, id DESC LIMIT 40', [$_SESSION['uid']]);
$users = all('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name');
page_top($isAdmin ? 'Tasks and notifications' : 'Tasks');
?>
<?php $tabs = ['open' => 'Open tasks']; if (can('tasks.assign')) $tabs['assign'] = 'Assign a task'; if ($isAdmin) $tabs['notes'] = 'Notifications'; tabs_begin('Tasks', $tabs); tab_open('open'); ?>
<div class="card tw"><h2>Open tasks</h2><table><tr><th>Task</th><th>Assigned to</th><th>Deadline</th><th></th></tr>
<?php foreach ($tasks as $t): $late = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?><tr><td><?= e($t['title']) ?> <span class="muted"><?= e($t['details']) ?></span></td><td><?= e($t['assignee']) ?></td>
<td><?= fmt_d($t['due_date']) ?> <?= $late ? badge('overdue', 'bad') : '' ?></td>
<td><form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="sm alt">Mark done</button></form></td></tr>
<?php endforeach; if (!$tasks): ?><tr><td colspan="4" class="muted">No open tasks.</td></tr><?php endif; ?></table></div>
<?php tab_close(); ?>
<?php if (can('tasks.assign')): tab_open('assign'); ?>
<div class="card"><h2>Assign a task</h2><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<label>Task <input name="title" required></label><label>Assign to <select name="assigned_to"><?= select_options($users, 'id', 'full_name') ?></select></label>
<label>Deadline <input type="date" name="due_date"></label><label>Details <input name="details"></label><button>Assign task</button></form></div>
<?php tab_close(); endif; ?>
<?php if ($isAdmin): tab_open('notes'); ?><div class="card"><h2>Notifications</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="0"><button class="sm alt">Mark all as read</button></form>
<?php foreach ($notes as $n): ?><p><?= $n['is_read'] ? '' : badge('new', 'ok') ?> <?= $n['link'] ? '<a href="' . e($n['link']) . '">' . e($n['message']) . '</a>' : e($n['message']) ?> <span class="muted"><?= fmt_dt($n['created_at']) ?></span></p>
<?php endforeach; if (!$notes): ?><p class="muted">You have no notifications.</p><?php endif; ?></div><?php tab_close(); endif; ?>
<?php tabs_end(); page_bottom();