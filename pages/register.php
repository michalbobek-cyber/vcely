<?php
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
  if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pass)>=6){
    try { register_user($email,$pass); login($email,$pass); header('Location: '.BASE_URL.'/dashboard'); exit; }
    catch (Throwable $e) { $err='Email už existuje.'; }
  } else { $err='Zadejte platný email a heslo min. 6 znaků.'; }
}
header_html('Registrace'); ?>
<h2>Registrace</h2>
<?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
<form method="post">
  <label>Email <input type="email" name="email" required></label>
  <label>Heslo <input type="password" name="password" required></label>
  <button class="btn">Registrovat</button>
</form>
<?php footer_html(); ?>