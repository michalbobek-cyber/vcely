<?php
require_once __DIR__.'/../lib/db.php';
$uid=current_user()['id'];
$s=db()->prepare('SELECT id,name,location FROM vcely_devices WHERE user_id=? ORDER BY id'); $s->execute([$uid]); $devices=$s->fetchAll();
header_html('Přehled'); ?>
<h2>Přehled</h2>
<div class="grid">
<?php foreach ($devices as $d): ?>
  <?php
    $st=db()->prepare('SELECT weight_g,temp_c,hum_pct,ts FROM vcely_readings WHERE device_id=? ORDER BY id DESC LIMIT 1'); $st->execute([$d['id']]); $last=$st->fetch();
    $r24=db()->prepare('SELECT weight_g FROM vcely_readings WHERE device_id=? AND ts <= NOW() - INTERVAL 24 HOUR ORDER BY id DESC LIMIT 1'); $r24->execute([$d['id']]); $x24=$r24->fetch();
    $r7=db()->prepare('SELECT weight_g FROM vcely_readings WHERE device_id=? AND ts <= NOW() - INTERVAL 7 DAY ORDER BY id DESC LIMIT 1'); $r7->execute([$d['id']]); $x7=$r7->fetch();
    $d24 = ($last && $x24) ? number_format($last['weight_g'] - $x24['weight_g'], 1) : null;
    $d7  = ($last && $x7) ? number_format($last['weight_g'] - $x7['weight_g'], 1) : null;
  ?>
  <div class="card">
    <h3><?= h($d['name']) ?> <small>#<?= h($d['id']) ?></small></h3>
    <p class="muted"><?= h($d['location']) ?></p>
    <p><b><?= h($last['weight_g'] ?? '-') ?> g</b></p>
    <p>Teplota: <?= h($last['temp_c'] ?? '-') ?> °C | Vlhkost: <?= h($last['hum_pct'] ?? '-') ?> %</p>
    <p class="muted"><?= h($last['ts'] ?? '-') ?></p>
    <p>Změna 24 h: <?= $d24 ?? '-' ?> g | 7 dní: <?= $d7 ?? '-' ?> g</p>
    <p><a class="btn" href="<?= BASE_URL ?>/device?id=<?= $d['id'] ?>">Detail</a></p>
  </div>
<?php endforeach; ?>
</div>
<?php footer_html(); ?>