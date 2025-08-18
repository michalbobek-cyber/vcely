-- Vlož výchozí záznamy do vcely_device_settings pro zařízení, která chybí
INSERT INTO vcely_device_settings (device_id, enable_alerts, min_drop_g_24h, min_rise_g_24h)
SELECT d.id, 1, 500, 500
FROM vcely_devices d
LEFT JOIN vcely_device_settings s ON s.device_id = d.id
WHERE s.device_id IS NULL;
