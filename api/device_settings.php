<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json');
require_login();

$uid = current_user()['id'];
$did = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;

if ($did<=0){ http_response_code(400); echo json_encode(['error'=>'device_id']); exit; }
if (!user_can_access_device($uid,$did,'editor')){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$enable = isset($_POST['enable_alerts']) ? 1 : 0;

// 24h prahy – NULL když prázdné
$min_drop = isset($_POST['min_drop_g_24h']) && $_POST['min_drop_g_24h'] !== '' ? floatval($_POST['min_drop_g_24h']) : null;
$min_rise = isset($_POST['min_rise_g_24h']) && $_POST['min_rise_g_24h'] !== '' ? floatval($_POST['min_rise_g_24h']) : null;

// === NOVÉ: instantní alerty (rychlý skok)
$instant_alert_enabled = isset($_POST['instant_alert_enabled']) ? 1 : 0;
$instant_delta_g       = isset($_POST['instant_delta_g']) && $_POST['instant_delta_g'] !== '' ? intval($_POST['instant_delta_g']) : 3000;
$instant_window_min    = isset($_POST['instant_window_min']) && $_POST['instant_window_min'] !== '' ? intval($_POST['instant_window_min']) : 10;
$instant_cooldown_min  = isset($_POST['instant_cooldown_min']) && $_POST['instant_cooldown_min'] !== '' ? intval($_POST['instant_cooldown_min']) : 60;

// bezpečné spodní meze
$instant_delta_g      = max(100, $instant_delta_g);     // min 100 g kvůli šumu
$instant_window_min   = max(1,   $instant_window_min);  // aspoň 1 min
$instant_cooldown_min = max(0,   $instant_cooldown_min);// >=0

// Upsert
$s = db()->prepare('SELECT 1 FROM vcely_device_settings WHERE device_id=?');
$s->execute([$did]);
if ($s->fetch()){
  $u = db()->prepare('UPDATE vcely_device_settings
                         SET enable_alerts=?,
                             min_drop_g_24h=?,
                             min_rise_g_24h=?,
                             instant_alert_enabled=?,
                             instant_delta_g=?,
                             instant_window_min=?,
                             instant_cooldown_min=?
                       WHERE device_id=?');
  $u->execute([
      $enable, $min_drop, $min_rise,
      $instant_alert_enabled, $instant_delta_g, $instant_window_min, $instant_cooldown_min,
      $did
  ]);
} else {
  $i = db()->prepare('INSERT INTO vcely_device_settings
        (device_id, enable_alerts, min_drop_g_24h, min_rise_g_24h,
         instant_alert_enabled, instant_delta_g, instant_window_min, instant_cooldown_min)
        VALUES (?,?,?,?,?,?,?,?)');
  $i->execute([
      $did, $enable, $min_drop, $min_rise,
      $instant_alert_enabled, $instant_delta_g, $instant_window_min, $instant_cooldown_min
  ]);
}

echo json_encode(['ok'=>true]);
?>