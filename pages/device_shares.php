<?php
require_once __DIR__.'/../lib/db.php';
$uid=current_user()['id']; $id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT * FROM vcely_devices WHERE id=? AND user_id=?'); $s->execute([$id,$uid]); $dev=$s->fetch();
if (!$dev){ http_response_code(403); echo 'Jen vlastník může spravovat sdílení.'; exit; }
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??''); $role=$_POST['role']??'viewer';
  if (!in_array($role,['viewer','editor'], true)) $role='viewer';
  if (filter_var($email, FILTER_VALIDATE_EMAIL)){
    $s=db()->prepare('SELECT id FROM vcely_users WHERE email=?'); $s->execute([$email]); $u=$s->fetch();
    if ($u){
      $s=db()->prepare('INSERT INTO vcely_device_shares (device_id,user_id,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)');
      $s->execute([$id,(int)$u['id'],$role]);
      $msg='Sdílení nastaveno.';
    } else { $msg='Uživatel neexistuje.'; }
  } else { $msg='Zadej platný e-mail.'; }
}
$s=db()->prepare('SELECT u.email, s.role FROM vcely_device_shares s JOIN vcely_users u ON u.id=s.user_id WHERE s.device_id=?'); $s->execute([$id]); $shares=$s->fetchAll();
header_html('Sdílení'); ?>
<h2>Sdílení: <?= h($dev['name']) ?> <small>#<?= h($dev['id']) ?></small></h2>
<?php if (!empty($msg)): ?><p class="notice"><?= h($msg) ?></p><?php endif; ?>
<form method="post" class="row">
  <label>Email uživatele <input name="email" type="email" required></label>
  <label>Role <select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option></select></label>
  <button class="btn">Uložit</button>
</form>
<h3>Aktuální přístupy</h3>
<ul>
  <?php foreach ($shares as $srow): ?>
    <li><?= h($srow['email']) ?> – <?= h($srow['role']) ?></li>
  <?php endforeach; ?>
</ul>
<?php footer_html(); ?>