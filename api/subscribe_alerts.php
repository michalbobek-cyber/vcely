<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json');
require_login();
$uid = current_user()['id'];
$did = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$sub = isset($_POST['subscribe']) ? intval($_POST['subscribe']) : 0;
if ($did<=0){ http_response_code(400); echo json_encode(['error'=>'device_id']); exit; }
if (!user_can_access_device($uid,$did,'viewer')){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
if ($sub){
  $q = db()->prepare('INSERT INTO vcely_alert_subscriptions (device_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)');
  $q->execute([$did,$uid]);
  echo json_encode(['ok'=>true,'subscribed'=>1]);
} else {
  $q = db()->prepare('DELETE FROM vcely_alert_subscriptions WHERE device_id=? AND user_id=?');
  $q->execute([$did,$uid]);
  echo json_encode(['ok'=>true,'subscribed'=>0]);
}
?>