<?php
/**
 * BeeScale – Instantní alerty při rychlém skoku hmotnosti
 * -------------------------------------------------------
 * Používá nastavení ze `vcely_device_settings`:
 *  - instant_alert_enabled (TINYINT 0/1)
 *  - instant_delta_g (INT, práh v gramech)
 *  - instant_window_min (INT, okno v minutách)
 *  - instant_cooldown_min (INT, cooldown v minutách)
 *
 * Závislosti: db_row(), db_value(), db_exec()
 */

if (!function_exists('vcely_alerts_instant_handle')) {
    function vcely_alerts_instant_handle(int $device_id, ?float $weight_g = null, ?int $ts_epoch = null): void
    {
        if ($device_id <= 0) return;

        // 1) Nastavení zařízení z vcely_device_settings
        $cfg = db_row(
            "SELECT instant_alert_enabled,
                    instant_delta_g,
                    instant_window_min,
                    instant_cooldown_min
               FROM vcely_device_settings
              WHERE device_id = ?",
            [$device_id]
        );
        if (!$cfg || intval($cfg['instant_alert_enabled']) !== 1) return;

        $thr   = max(100, intval($cfg['instant_delta_g']));
        $win   = max(1,   intval($cfg['instant_window_min']));
        $cdMin = max(0,   intval($cfg['instant_cooldown_min']));

        // 2) Aktuální hodnota – buď z parametru, nebo poslední záznam
        if ($weight_g === null || $ts_epoch === null) {
            $curr = db_row(
                "SELECT weight_g, COALESCE(ts, created_at) AS t
                   FROM vcely_readings
                  WHERE device_id = ?
                  ORDER BY id DESC
                  LIMIT 1",
                [$device_id]
            );
            if (!$curr) return;
            if ($weight_g === null) $weight_g = floatval($curr['weight_g']);
            if ($ts_epoch === null) $ts_epoch = is_numeric($curr['t']) ? intval($curr['t']) : strtotime($curr['t']);
        }
        if ($ts_epoch === null) $ts_epoch = time();

        // 3) Najdi referenční bod na začátku okna
        $since = date('Y-m-d H:i:s', $ts_epoch - $win * 60);
        $prev = db_row(
            "SELECT weight_g, COALESCE(ts, created_at) AS t
               FROM vcely_readings
              WHERE device_id = ?
                AND COALESCE(ts, created_at) >= ?
              ORDER BY COALESCE(ts, created_at) ASC
              LIMIT 1",
            [$device_id, $since]
        );
        if (!$prev) return; // v okně není k čemu přirovnat

        $delta = floatval($weight_g) - floatval($prev['weight_g']);
        if (abs($delta) < $thr) return; // pod prahem

        // 4) Cooldown – nečastěji než…
        $lastAt = db_value(
            "SELECT MAX(created_at)
               FROM vcely_alerts
              WHERE device_id = ?
                AND type = 'instant_delta'",
            [$device_id]
        );
        if ($lastAt && strtotime($lastAt) > ($ts_epoch - $cdMin * 60)) {
            return; // ještě běží cooldown
        }

        // 5) Zapiš alert
        $title = ($delta < 0 ? 'Rychlý úbytek hmotnosti' : 'Rychlý nárůst hmotnosti');
        $msg = sprintf(
            "Δ%.1f g za posledních %d min (z %.1f g na %.1f g).",
            $delta, $win, floatval($prev['weight_g']), floatval($weight_g)
        );

        db_exec(
            "INSERT INTO vcely_alerts (device_id, type, severity, title, message)
             VALUES (?, 'instant_delta', 'warning', ?, ?)",
            [$device_id, $title, $msg]
        );

        // 6) Odeslání notifikace (pokud existuje lib/notify.php s notify_device())
        $notify = __DIR__ . '/notify.php';
        if (file_exists($notify)) {
            require_once $notify;
            if (function_exists('notify_device')) {
                @notify_device($device_id, $title, $msg);
                return;
            }
        }
        // Fallback: e-mail owner/editor (pokud máš mail nastaven)
        if (function_exists('mail')) {
            $ownerEmail = db_value(
                "SELECT u.email
                   FROM vcely_users u
                   JOIN vcely_device_users du ON du.user_id = u.id
                  WHERE du.device_id = ?
                    AND du.role IN ('owner','editor')
                  ORDER BY du.role='owner' DESC
                  LIMIT 1",
                [$device_id]
            );
            if ($ownerEmail) {
                @mail($ownerEmail, "[BeeScale] $title", $msg);
            }
        }
    }
}
?>