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

// NULL when empty
$min_drop = isset($_POST['min_drop_g_24h']) && $_POST['min_drop_g_24h'] !== '' ? floatval($_POST['min_drop_g_24h']) : null;
$min_rise = isset($_POST['min_rise_g_24h']) && $_POST['min_rise_g_24h'] !== '' ? floatval($_POST['min_rise_g_24h']) : null;

// Upsert
$s = db()->prepare('SELECT 1 FROM vcely_device_settings WHERE device_id=?');
$s->execute([$did]);
if ($s->fetch()){
  $u = db()->prepare('UPDATE vcely_device_settings SET enable_alerts=?, min_drop_g_24h=?, min_rise_g_24h=? WHERE device_id=?');
  $u->execute([$enable, $min_drop, $min_rise, $did]);
} else {
  $i = db()->prepare('INSERT INTO vcely_device_settings (device_id, enable_alerts, min_drop_g_24h, min_rise_g_24h) VALUES (?,?,?,?)');
  $i->execute([$did, $enable, $min_drop, $min_rise]);
}

echo json_encode(['ok'=>true]);
?>