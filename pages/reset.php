<?php
$email = $_GET['email'] ?? ($_POST['email'] ?? '');
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $p=$_POST['password']??''; $p2=$_POST['password2']??'';
  if ($p===$p2 && strlen($p)>=6){ $res=reset_password_with_token($email,$token,$p); if($res===true){ $ok='Heslo změněno. Můžeš se přihlásit.'; } else { $err=$res; } }
  else { $err='Hesla se neshodují nebo jsou krátká (min. 6 znaků).'; }
}
header_html('Obnovení hesla'); ?>
<h2>Obnovení hesla</h2>
<?php if (!empty($ok)): ?><p class="success"><?= h($ok) ?></p><?php endif; ?>
<?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
<form method="post" class="row">
  <input type="hidden" name="email" value="<?= h($email) ?>">
  <input type="hidden" name="token" value="<?= h($token) ?>">
  <label>Nové heslo <input type="password" name="password" required></label>
  <label>Potvrzení hesla <input type="password" name="password2" required></label>
  <button class="btn">Změnit heslo</button>
</form>
<?php footer_html(); ?>