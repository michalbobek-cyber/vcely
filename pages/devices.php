<?php
require_once __DIR__.'/../lib/db.php';
$uid=current_user()['id'];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create'){
  $name=trim($_POST['name']??''); $loc=trim($_POST['location']??'');
  if ($name) {
    $s=db()->prepare('INSERT INTO vcely_devices (user_id,name,location) VALUES (?,?,?)'); $s->execute([$uid,$name,$loc]);
    $did=db()->lastInsertId();
    $key=bin2hex(random_bytes(16));
    $s=db()->prepare('INSERT INTO vcely_device_keys (device_id,api_key) VALUES (?,?)'); $s->execute([$did,$key]);
    $s=db()->prepare('INSERT INTO vcely_device_settings (device_id,enable_alerts,min_drop_g_24h,min_rise_g_24h) VALUES (?,1,500,500)'); $s->execute([$did]);
    header('Location: '.BASE_URL.'/devices'); exit;
  }
}
$s=db()->prepare('SELECT d.id,d.name,d.location,dk.api_key FROM vcely_devices d LEFT JOIN vcely_device_keys dk ON dk.device_id=d.id WHERE d.user_id=?'); $s->execute([$uid]); $rows=$s->fetchAll();
header_html('Moje váhy'); ?>
<h2>Moje váhy</h2>
<form method="post" class="row">
  <input type="hidden" name="action" value="create">
  <label>Název <input name="name" required></label>
  <label>Lokalita <input name="location"></label>
  <button class="btn">Přidat váhu</button>
</form>
<div class="grid">
<?php foreach ($rows as $r): ?>
  <div class="card">
    <h3><?= h($r['name']) ?> <small>#<?= h($r['id']) ?></small></h3>
    <p class="muted"><?= h($r['location']) ?></p>
    <details><summary>Ingest URL</summary><p><code><?= BASE_URL ?>/api/ingest?key=<?= h($r['api_key']) ?></code></p></details>
    <p class="row">
      <a class="btn" href="<?= BASE_URL ?>/device?id=<?= $r['id'] ?>">Detail</a>
      <a class="btn" href="<?= BASE_URL ?>/device/shares?id=<?= $r['id'] ?>">Sdílení</a>
    </p>
  </div>
<?php endforeach; ?>
</div>
<?php footer_html(); ?>