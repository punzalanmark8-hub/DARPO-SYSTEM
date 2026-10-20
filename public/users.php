<?php
require __DIR__ . '/../app/bootstrap.php';
require_perm('users.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $uid = (int)post('id');
    if ($act === 'create') {
        $sur = post('surname'); $first = post('first_name'); $mi = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', post('middle_initial')), 0, 1));
        $bd = post('birthdate'); $bdt = DateTime::createFromFormat('Y-m-d', $bd); $email = post('email');
        // Temporary password = Lastname + year of birth (e.g. Santos1990); the user must change it at first login
        $pw = ucfirst(strtolower(preg_replace('/[^A-Za-z0-9]/', '', $sur))) . ($bdt ? $bdt->format('Y') : '');
        if ($sur === '' || $first === '' || !isset(ROLES[post('role')])) flash('err', 'Surname, first name and role are required.');
        elseif (!$bdt || $bdt->format('Y-m-d') !== $bd || $bd > date('Y-m-d')) flash('err', 'Enter a valid birthdate.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('err', 'Enter a valid email address.');
        else {
            // Username = Firstname + day of birth (e.g. juan07); a number is added if it is already taken
            $base = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $first)) . $bdt->format('d'); $name = $base;
            for ($n = 2; one('SELECT id FROM users WHERE username=?', [$name]); $n++) $name = $base . $n;
            $full = $first . ($mi !== '' ? " $mi." : '') . " $sur";
            $id = insert('users', ['username' => $name, 'surname' => $sur, 'first_name' => $first, 'middle_initial' => $mi ?: null, 'birthdate' => $bd, 'full_name' => $full,
                'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'role' => post('role'), 'division_id' => (int)post('division_id') ?: null]);
            audit('user_created', 'user', $id, $name . ' as ' . post('role'));
            flash('ok', "Account created. Username: $name, temporary password: $pw (Lastname + year of birth). The user must change it at first login.");
        }
    } elseif ($act === 'update' && $uid) {
        if (!isset(ROLES[post('role')])) { flash('err', 'Invalid role.'); redirect('users.php'); }
        if ($uid === (int)$_SESSION['uid'] && post('role') !== 'admin') { flash('err', 'You cannot remove your own administrator role.'); redirect('users.php'); }
        q('UPDATE users SET role=?, division_id=? WHERE id=?', [post('role'), (int)post('division_id') ?: null, $uid]);
        audit('user_role_changed', 'user', $uid, post('role') . ' / division ' . post('division_id'));
        flash('ok', 'User updated.');
    } elseif ($act === 'toggle' && $uid) {
        if ($uid === (int)$_SESSION['uid']) flash('err', 'You cannot deactivate your own account.');
        else { q('UPDATE users SET is_active=1-is_active WHERE id=?', [$uid]); audit('user_toggled', 'user', $uid); flash('ok', 'Account status changed.'); }
    } elseif ($act === 'unlock' && $uid) {
        q('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?', [$uid]); audit('user_unlocked', 'user', $uid); flash('ok', 'Account unlocked.');
    } elseif ($act === 'reset' && $uid) {
        $pw = (string)($_POST['password'] ?? '');
        if ($err = validate_password($pw)) flash('err', $err);
        else { q('UPDATE users SET password_hash=?, must_change_password=1, failed_attempts=0, locked_until=NULL WHERE id=?', [password_hash($pw, PASSWORD_BCRYPT), $uid]); audit('password_reset', 'user', $uid); flash('ok', 'Temporary password set.'); }
    }
    redirect('users.php');
}
$users = all('SELECT u.*, d.name division FROM users u LEFT JOIN divisions d ON d.id=u.division_id ORDER BY u.is_active DESC, u.username');
$divs = all('SELECT id,name FROM divisions WHERE is_active=1 ORDER BY name');
$roleOpts = fn($sel) => implode('', array_map(fn($k, $v) => '<option value="' . $k . '"' . ($k === $sel ? ' selected' : '') . '>' . e($v) . '</option>', array_keys(ROLES), ROLES));
page_top('User and role management');
?>
<?php tabs_begin('Users', ['accounts' => 'User accounts', 'create' => 'Create account'], true); tab_open('create'); ?>
<div class="card"><h2>Create account</h2><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label>Surname <input name="surname" id="uSur" required></label><label>First name <input name="first_name" id="uFirst" required></label><label>Middle initial <input name="middle_initial" maxlength="1" style="width:70px"></label>
  <label>Birthdate <input type="date" name="birthdate" id="uBd" max="<?= date('Y-m-d') ?>" required></label><label>Email <input type="email" name="email" required></label>
  <label>Role <select name="role"><?= $roleOpts('division_staff') ?></select></label>
  <label>Division <select name="division_id"><option value="">None</option><?= select_options($divs, 'id', 'name') ?></select></label>
  <label>Username (auto) <input id="uUser" readonly tabindex="-1" placeholder="Firstname + day of birth"></label>
  <label>Temporary password (auto) <input id="uPass" readonly tabindex="-1" placeholder="Lastname + year of birth"></label>
  <button>Create account</button></form><p class="muted">The username is generated automatically as <strong>Firstname + day of birth</strong> (for example <code>juan07</code>) and the temporary password as <strong>Lastname + year of birth</strong> (for example <code>Santos1990</code>). The boxes above fill in by themselves and cannot be edited. They are shown once after the account is created, and the user must change it at first login.</p></div>
<script>
(function(){var f=document.getElementById('uFirst'),s=document.getElementById('uSur'),b=document.getElementById('uBd'),u=document.getElementById('uUser'),p=document.getElementById('uPass');if(!f)return;
function clean(x){return x.replace(/[^A-Za-z0-9]/g,'');}
function go(){var d=b.value.match(/^(\d{4})-\d{2}-(\d{2})$/),fn=clean(f.value).toLowerCase(),sn=clean(s.value).toLowerCase();
u.value=(fn&&d)?fn+d[2]:'';p.value=(sn&&d)?sn.charAt(0).toUpperCase()+sn.slice(1)+d[1]:'';}
['input','change'].forEach(function(e){[f,s,b].forEach(function(el){el.addEventListener(e,go);});});go();})();
</script>
<?php tab_close(); tab_open('accounts'); ?>
<div class="card tw"><h2>User accounts</h2><table><tr><th>User</th><th>Role and division</th><th>Last login</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($users as $u): $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
<tr><td><strong><?= e($u['username']) ?></strong><br><?= e($u['full_name']) ?></td>
<td><form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $u['id'] ?>">
<select name="role"><?= $roleOpts($u['role']) ?></select><select name="division_id"><option value="">None</option><?= select_options($divs, 'id', 'name', $u['division_id']) ?></select><button class="sm alt">Save</button></form></td>
<td><?= fmt_dt($u['last_login']) ?></td><td><?= $u['is_active'] ? badge('Active', 'ok') : badge('Deactivated', 'bad') ?> <?= $locked ? badge('Locked', 'bad') : '' ?></td>
<td><form method="post" class="row" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
<button name="action" value="toggle" class="sm alt"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button><?php if ($locked || $u['failed_attempts']): ?><button name="action" value="unlock" class="sm alt">Unlock</button><?php endif; ?>
<input type="password" name="password" placeholder="New temp password" autocomplete="new-password" style="width:150px"><button name="action" value="reset" class="sm alt">Reset password</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php tab_close(); tabs_end(); page_bottom();