<?php
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
  if (login($email,$pass)) { header('Location: '.BASE_URL.'/dashboard'); exit; } else { $err='Neplatné přihlašovací údaje.'; }
}
header_html('Přihlášení'); ?>
<h2>Přihlášení</h2>
<?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
<form method="post">
  <label>Email <input type="email" name="email" required></label>
  <label>Heslo <input type="password" name="password" required></label>
  <button class="btn">Přihlásit</button>
</form>
<?php footer_html(); ?>