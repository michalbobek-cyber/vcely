<?php
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??'');
  if (filter_var($email,FILTER_VALIDATE_EMAIL)){ create_reset_token($email); $msg='Pokud e-mail existuje, poslali jsme odkaz pro obnovu hesla.'; }
  else { $msg='Zadej platný e-mail.'; }
}
header_html('Zapomenuté heslo'); ?>
<h2>Zapomenuté heslo</h2>
<?php if (!empty($msg)): ?><p class="notice"><?= h($msg) ?></p><?php endif; ?>
<form method="post" class="row">
  <label>Email <input type="email" name="email" required></label>
  <button class="btn">Odeslat odkaz</button>
</form>
<?php footer_html(); ?>