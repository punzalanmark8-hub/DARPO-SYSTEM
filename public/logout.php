<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { logout_user(); redirect('login.php'); }
page_top('Sign out');
?>
<form method="post" class="card"><?= csrf_field() ?><p>Sign out of DARPO LAGUNA e-Records?</p><button>Sign out</button> <a class="btn alt" href="index.php">Cancel</a></form>
<?php page_bottom();
