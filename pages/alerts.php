<?php
require_once __DIR__.'/../lib/db.php';
$uid=current_user()['id'];
$s=db()->prepare('SELECT d.id,d.name FROM vcely_devices d WHERE d.user_id=? ORDER BY id'); $s->execute([$uid]); $devices=$s->fetchAll();
header_html('Alerty'); ?>
<h2>Alerty</h2>
<?php foreach ($devices as $d): ?>
  <div class="card">
    <h3><?= h($d['name']) ?> <small>#<?= h($d['id']) ?></small></h3>
    <?php
      $st=db()->prepare('SELECT created_at,type,message,delta_g FROM vcely_alerts WHERE device_id=? ORDER BY id DESC LIMIT 10');
      $st->execute([$d['id']]); $alerts=$st->fetchAll();
      if (!$alerts) echo '<p class="muted">Žádné alerty</p>';
      else { echo '<ul>'; foreach($alerts as $a){ echo '<li>'.h($a['created_at']).' – '.h($a['message']).' ('.h($a['type']).')</li>'; } echo '</ul>'; }
    ?>
    <p><a class="btn" href="<?= BASE_URL ?>/device?id=<?= $d['id'] ?>">Detail</a></p>
  </div>
<?php endforeach; ?>
<?php footer_html(); ?>