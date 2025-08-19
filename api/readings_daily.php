<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
$did = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
$days = isset($_GET['days']) ? max(1, min(3650, intval($_GET['days']))) : null; // null = all
if ($did <= 0){ http_response_code(400); echo json_encode(['error'=>'device_id']); exit; }

$cond = '';
$params = [$did];
if ($days !== null){
  $cond = ' AND ts >= NOW() - INTERVAL ? DAY';
  $params[] = $days;
}

$sql = 'SELECT DATE(ts) AS day,
               AVG(weight_g) AS weight_avg_g,
               AVG(temp_c)   AS temp_avg_c,
               AVG(hum_pct)  AS hum_avg_pct,
               COUNT(*)      AS samples
        FROM vcely_readings
        WHERE device_id = ?' . $cond . '
        GROUP BY DATE(ts)
        ORDER BY day ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
?>