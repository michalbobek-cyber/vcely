<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
$k = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!$k){ http_response_code(401); echo json_encode(['error'=>'missing_api_key']); exit; }
$s = db()->prepare('SELECT device_id FROM vcely_device_keys WHERE api_key=?'); $s->execute([$k]); $row=$s->fetch();
if (!$row){ http_response_code(403); echo json_encode(['error'=>'invalid_api_key']); exit; }
$did = (int)$row['device_id'];
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) { $raw=file_get_contents('php://input'); $d=json_decode($raw,true); } else { $d=$_POST; }
$w = isset($d['weight_g']) ? floatval($d['weight_g']) : null;
$t = isset($d['temp_c']) ? floatval($d['temp_c']) : null;
$h = isset($d['hum_pct']) ? floatval($d['hum_pct']) : null;
$seq = isset($d['seq']) ? intval($d['seq']) : null;
$up = isset($d['uptime_ms']) ? intval($d['uptime_ms']) : null;
if ($w===null && $t===null && $h===null){ http_response_code(400); echo json_encode(['error'=>'missing_fields']); exit; }
$s = db()->prepare('INSERT INTO vcely_readings (device_id,weight_g,temp_c,hum_pct,seq,uptime_ms) VALUES (?,?,?,?,?,?)');
$s->execute([$did,$w,$t,$h,$seq,$up]);
// === Instantní alerty: rychlá změna hmotnosti ================================
try {
    // 1) Načti nastavení zařízení
		$cfg = db_row(
        "SELECT instant_alert_enabled, instant_delta_g, instant_window_min, instant_cooldown_min
           FROM vcely_device_settings
          WHERE device_id = ?",
        [$did]   /* v tomto souboru proměnná s device id je $did */
    );

    if ($cfg && intval($cfg['instant_alert_enabled'])) {
        $thr   = max(100, intval($cfg['instant_delta_g']));       // min. 100 g, ať to „nebliká“ na šumu
        $win   = max(1,   intval($cfg['instant_window_min']));     // 1..∞ minut
        $cdMin = max(0,   intval($cfg['instant_cooldown_min']));   // 0..∞ minut

        // 2) Najdi referenční váhu na začátku okna
        $since = date('Y-m-d H:i:s', time() - $win * 60);
        $prev = db_row(
            "SELECT id, weight_g, COALESCE(ts, created_at) AS t
               FROM vcely_readings
              WHERE device_id = ?
                AND COALESCE(ts, created_at) >= ?
              ORDER BY COALESCE(ts, created_at) ASC
              LIMIT 1",
            [$device_id, $since]
        );

        // Aktuální váhu vem z toho, co právě ukládáš
        $w_now = isset($weight_g) ? floatval($weight_g) : null;

        if ($prev && $w_now !== null) {
            $delta = $w_now - floatval($prev['weight_g']);

            if (abs($delta) >= $thr) {
                // 3) Cooldown – kdy byl poslední instantní alert?
                $lastAt = db_value(
                    "SELECT MAX(created_at) FROM vcely_alerts
                      WHERE device_id = ? AND type = 'instant_delta'",
                    [$device_id]
                );

                $cooldown_ok = true;
                if ($lastAt) {
                    $cooldown_ok = (strtotime($lastAt) <= time() - $cdMin * 60);
                }

                if ($cooldown_ok) {
                    // 4) Ulož alert + pošli notifikaci
                    $title = ($delta < 0 ? 'Rychlý úbytek hmotnosti' : 'Rychlý nárůst hmotnosti');
                    $msg = sprintf(
                        "Δ%.1f g za posledních %d min (z %.1f g na %.1f g).",
                        $delta, $win, floatval($prev['weight_g']), $w_now
                    );

                    db_exec(
                        "INSERT INTO vcely_alerts (device_id, type, severity, title, message)
                         VALUES (?, 'instant_delta', 'warning', ?, ?)",
                        [$device_id, $title, $msg]
                    );

                    // Pokud máš v /lib/ nějaký mailer/notify, použij ho:
                    // (nahraď za svou implementaci)
                    if (file_exists(__DIR__ . '/../lib/notify.php')) {
                        require_once __DIR__ . '/../lib/notify.php';
                        if (function_exists('notify_device')) {
                            // tip: notify_device($device_id, $subject, $text)
                            notify_device($device_id, $title, $msg);
                        }
                    } elseif (function_exists('mail')) {
                        // jednoduchý fallback – pošli na e-mail vlastníka device (pokud ho máš v DB)
                        $ownerEmail = db_value(
                            "SELECT u.email
                               FROM vcely_users u
                               JOIN vcely_device_users du ON du.user_id=u.id
                              WHERE du.device_id=? AND du.role IN ('owner','editor')
                              ORDER BY du.role='owner' DESC LIMIT 1",
                            [$device_id]
                        );
                        if ($ownerEmail) {
                            @mail($ownerEmail, "[BeeScale] $title", $msg);
                        }
                    }
                } // cooldown
            } // threshold překročen
        } // je srovnávací předchozí
    } // enabled
} catch (Throwable $e) {
    // Ochranně neblokuj ingest – ale zaloguj (případně do vcely_logs)
    // error_log("instant alert failed: " . $e->getMessage());
}
// ============================================================================
// KONEC instant alertů

echo json_encode(['ok'=>true]);
?>