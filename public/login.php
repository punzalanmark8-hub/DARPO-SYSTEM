<?php
define('PUBLIC_PAGE', true);
require __DIR__ . '/../app/bootstrap.php';
if (!empty($_SESSION['uid'])) redirect('index.php');
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = $_SESSION['next'] ?? '';   // login_user() resets the session, so keep the scanned link first
    $err = login_user(post('username'), (string)($_POST['password'] ?? ''));
    if (!$err) redirect(preg_match('/^scan\.php\?[A-Za-z0-9=&_.%-]*$/', $next) ? $next : 'index.php');
}
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in | <?= e(cfg()['app']['name']) ?></title>
<style>
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:#fff;color:#1b2a25;font:15px/1.5 "Public Sans",system-ui,-apple-system,"Segoe UI",sans-serif;display:flex;align-items:center;justify-content:center;padding:20px}
/* DAR logo as a faded full-page background */
.bg{position:fixed;inset:0;background:url('assets/darlogo.jpg') center / min(92vmin,860px) no-repeat;opacity:.10;pointer-events:none;z-index:0}
.box{position:relative;z-index:1;width:100%;max-width:400px;background:rgba(255,255,255,.92);border:1px solid #d6ddd8;border-radius:10px;padding:28px 26px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
h1{margin:0;font-size:22px;color:#1f6b4a;text-align:center}
.sub{margin:2px 0 20px;text-align:center;color:#5d6b66;font-size:13px}
form{display:grid;gap:14px}
label{display:flex;flex-direction:column;gap:4px;font-size:13px;color:#5d6b66}
input{font:inherit;color:#1b2a25;background:#fff;border:1px solid #c5cfc9;border-radius:6px;padding:10px 11px}
input:focus{outline:2px solid #1f6b4a;outline-offset:1px;border-color:#1f6b4a}
button{font:inherit;background:#1f6b4a;color:#fff;border:0;border-radius:6px;padding:11px;cursor:pointer;font-weight:600}
button:hover{background:#185a3e}
.msg{padding:9px 12px;border-radius:6px;margin-bottom:14px;font-size:13.5px;background:#f8e1de;color:#b3392e}
.msg.warn{background:#f6ecd6;color:#8a6414}.msg.ok{background:#e1efe7;color:#1f6b4a}
.note{margin:18px 0 0;text-align:center;color:#5d6b66;font-size:12.5px}

/* interactions (background above is unchanged) */
.box{animation:in .45s cubic-bezier(.2,.7,.2,1) both}
@keyframes in{from{opacity:0;transform:translateY(14px) scale(.98)}}
.box.shake{animation:shake .4s}
@keyframes shake{20%,60%{transform:translateX(-7px)}40%,80%{transform:translateX(7px)}}
input,button{transition:border-color .18s,box-shadow .18s,background-color .18s,transform .15s}
input:hover{border-color:#a9b8b0}
input:focus{box-shadow:0 0 0 3px rgba(31,107,74,.18);outline:0}
button:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,107,74,.28)}
button:active{transform:scale(.98);box-shadow:none}
.pw{position:relative;display:block}.pw input{width:100%;padding-right:62px}
.pw .tg{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;color:#1f6b4a;padding:5px 8px;font-size:12px;font-weight:600;box-shadow:none}
.pw .tg:hover{background:#e1efe7;transform:translateY(-50%);box-shadow:none}
.caps{display:none;font-size:12px;color:#8a6414;margin-top:2px}.caps.on{display:block}
button.go.loading{opacity:.85;pointer-events:none}
button.go.loading::after{content:"";display:inline-block;width:12px;height:12px;margin-left:8px;vertical-align:-1px;border:2px solid #fff;border-right-color:transparent;border-radius:50%;animation:sp .7s linear infinite}
@keyframes sp{to{transform:rotate(360deg)}}
@media(max-width:420px){body{padding:12px}.box{padding:22px 18px}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="bg" aria-hidden="true"></div>
<main class="box">
  <h1>DARPO LAGUNA e-Records</h1>
  <p class="sub">Document Management and Tracking System</p>
  <?php foreach ($flashes as [$t, $m]): ?><div class="msg <?= $t === 'err' ? '' : e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
  <?php if ($err): ?><div class="msg"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus></label>
    <label>Password <span class="pw"><input type="password" name="password" id="pw" required><button type="button" class="tg" aria-label="Show password">Show</button></span><span class="caps" id="caps">Caps Lock is on</span></label>
    <button class="go">Sign in</button>
  </form>
  <p class="note">Authorized DAR personnel only. All activity is logged.</p>
</main>
<script>
(function(){
var pw=document.getElementById('pw'),tg=document.querySelector('.tg'),caps=document.getElementById('caps'),f=document.querySelector('form'),box=document.querySelector('.box');
tg.addEventListener('click',function(){var h=pw.type==='password';pw.type=h?'text':'password';tg.textContent=h?'Hide':'Show';tg.setAttribute('aria-label',h?'Hide password':'Show password');pw.focus();});
function c(e){if(e.getModifierState)caps.classList.toggle('on',e.getModifierState('CapsLock'));}
pw.addEventListener('keydown',c);pw.addEventListener('keyup',c);pw.addEventListener('blur',function(){caps.classList.remove('on');});
f.addEventListener('submit',function(e){var b=f.querySelector('.go');if(b.classList.contains('loading')){e.preventDefault();return;}b.classList.add('loading');});
window.addEventListener('pageshow',function(){var b=f.querySelector('.go');b.classList.remove('loading');});
if(document.querySelector('.msg:not(.warn):not(.ok)')){box.classList.add('shake');pw.value='';}
})();
</script>
</body>
</html>