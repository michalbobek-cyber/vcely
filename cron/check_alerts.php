<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
function send_mail_now($to,$subject,$body){ $headers='From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>'; @mail($to,$subject,$body,$headers); }
$devs = db()->query('SELECT d.id, d.name, s.enable_alerts, s.min_drop_g_24h, s.min_rise_g_24h FROM vcely_devices d LEFT JOIN vcely_device_settings s ON s.device_id=d.id')->fetchAll();
foreach ($devs as $d){
  if (empty($d['enable_alerts'])) continue;
  $id=(int)$d['id'];
  $st=db()->prepare('SELECT weight_g,ts FROM vcely_readings WHERE device_id=? ORDER BY id DESC LIMIT 1'); $st->execute([$id]); $last=$st->fetch();
  if (!$last) continue;
  $r24=db()->prepare('SELECT weight_g,ts FROM vcely_readings WHERE device_id=? AND ts <= NOW() - INTERVAL 24 HOUR ORDER BY id DESC LIMIT 1'); $r24->execute([$id]); $ref=$r24->fetch();
  if (!$ref) continue;
  $delta = $last['weight_g'] - $ref['weight_g'];
  $type = null; $msg = null;
  if ($d['min_drop_g_24h']!==null && $delta <= -abs($d['min_drop_g_24h'])){ $type='drop_24h'; $msg='Rychlý úbytek za 24h: '.number_format($delta,1).' g'; }
  elseif ($d['min_rise_g_24h']!==null && $delta >= abs($d['min_rise_g_24h'])){ $type='rise_24h'; $msg='Rychlý nárůst za 24h: '.number_format($delta,1).' g'; }
  if ($type){
    $dup=db()->prepare('SELECT id FROM vcely_alerts WHERE device_id=? AND type=? AND created_at >= NOW() - INTERVAL 1 HOUR ORDER BY id DESC LIMIT 1');
    $dup->execute([$id,$type]);
    if (!$dup->fetch()){
      $ins=db()->prepare('INSERT INTO vcely_alerts (device_id,type,message,delta_g) VALUES (?,?,?,?)'); $ins->execute([$id,$type,$msg,$delta]);
      $subs=db()->prepare('SELECT u.email FROM vcely_alert_subscriptions s JOIN vcely_users u ON u.id=s.user_id WHERE s.device_id=?'); $subs->execute([$id]); $recipients=$subs->fetchAll();
      if ($recipients){
        $owner=db()->prepare('SELECT u.email,d.name FROM vcely_devices d JOIN vcely_users u ON u.id=d.user_id WHERE d.id=?'); $owner->execute([$id]); $own=$owner->fetch();
        $subject='[BeeScale] '.$msg;
        $body="Zařízení: ".$own['name']." (ID ".$id.")\n".$msg."\n\nDetail: ".BASE_URL."/device?id=".$id."\n";
        foreach($recipients as $r){ send_mail_now($r['email'], $subject, $body); }
      }
    }
  }
}
echo "OK\n";
?>