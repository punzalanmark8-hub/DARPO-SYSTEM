<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = one('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) flash('err', 'Current password is incorrect.');
    elseif ($new !== (string)($_POST['confirm'] ?? '')) flash('err', 'New passwords do not match.');
    elseif ($err = validate_password($new)) flash('err', $err);
    elseif (password_verify($new, $u['password_hash'])) flash('err', 'Choose a password different from the current one.');
    else {
        q('UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), $u['id']]);
        audit('password_changed', 'user', $u['id']);
        flash('ok', 'Password updated.');
        redirect('index.php');
    }
    redirect('account.php');
}
page_top('My account');
?>
<div class="card" style="max-width:420px">
  <h2>Change password</h2>
  <p class="muted">At least <?= cfg()['app']['min_password'] ?> characters with upper-case, lower-case and a digit.</p>
  <form method="post" class="row" style="flex-direction:column;align-items:stretch">
    <?= csrf_field() ?>
    <label>Current password <input type="password" name="current" required></label>
    <label>New password <input type="password" name="new" required></label>
    <label>Confirm new password <input type="password" name="confirm" required></label>
    <button>Save password</button>
  </form>
</div>
<?php page_bottom();
