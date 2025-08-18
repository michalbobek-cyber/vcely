<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

require_login();
$uid = current_user()['id'];

$did = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
if ($did<=0){ http_response_code(400); echo 'device_id'; exit; }

// pouze owner/editor
if (!user_can_access_device($uid,$did,'editor')){ http_response_code(403); echo 'forbidden'; exit; }

$enable = isset($_POST['enable_alerts']) ? 1 : 0;
$drop_s = trim($_POST['min_drop_g_24h'] ?? '');
$rise_s = trim($_POST['min_rise_g_24h'] ?? '');
$drop = ($drop_s === '') ? null : floatval($drop_s);
$rise = ($rise_s === '') ? null : floatval($rise_s);

$q = db()->prepare('INSERT INTO vcely_device_settings (device_id,enable_alerts,min_drop_g_24h,min_rise_g_24h) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enable_alerts=VALUES(enable_alerts),min_drop_g_24h=VALUES(min_drop_g_24h),min_rise_g_24h=VALUES(min_rise_g_24h)');
$q->execute([$did,$enable,$drop,$rise]);

if (!empty($_POST['redirect'])){
  header('Location: '.$_POST['redirect']);
  exit;
}

header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
?>