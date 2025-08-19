-- phpMyAdmin SQL Dump
-- version 3.5.FORPSI
-- http://www.phpmyadmin.net
--
-- Počítač: 185.129.138.45
-- Vygenerováno: Úte 19. srp 2025, 02:32
-- Verze MySQL: 8.0.26-16
-- Verze PHP: 5.2.17

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Databáze: `f181241`
--

DELIMITER $$
--
-- Procedury
--
CREATE DEFINER=`f181241`@`%` PROCEDURE `add_col_if_missing`(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_tail VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @s = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_col, ' ', p_tail);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

CREATE DEFINER=`f181241`@`%` PROCEDURE `add_fk_if_missing`(IN p_table VARCHAR(64), IN p_fkname VARCHAR(64), IN p_col VARCHAR(64))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = p_fkname
  ) THEN
    SET @s = CONCAT('ALTER TABLE ', p_table, ' ADD CONSTRAINT ', p_fkname,
                    ' FOREIGN KEY (', p_col, ') REFERENCES ip_accounts(account_id) ON DELETE CASCADE');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_alerts`
--

CREATE TABLE IF NOT EXISTS `vcely_alerts` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delta_g` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_alert_subscriptions`
--

CREATE TABLE IF NOT EXISTS `vcely_alert_subscriptions` (
  `device_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_devices`
--

CREATE TABLE IF NOT EXISTS `vcely_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_device_keys`
--

CREATE TABLE IF NOT EXISTS `vcely_device_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `api_key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_device_settings`
--

CREATE TABLE IF NOT EXISTS `vcely_device_settings` (
  `device_id` int NOT NULL,
  `enable_alerts` tinyint(1) DEFAULT '1',
  `min_drop_g_24h` double DEFAULT '500',
  `min_rise_g_24h` double DEFAULT '500',
  PRIMARY KEY (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_device_shares`
--

CREATE TABLE IF NOT EXISTS `vcely_device_shares` (
  `device_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('viewer','editor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer',
  PRIMARY KEY (`device_id`,`user_id`),
  KEY `fk_vcely_shares_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_password_resets`
--

CREATE TABLE IF NOT EXISTS `vcely_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_readings`
--

CREATE TABLE IF NOT EXISTS `vcely_readings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `weight_g` double DEFAULT NULL,
  `temp_c` double DEFAULT NULL,
  `hum_pct` double DEFAULT NULL,
  `seq` int DEFAULT NULL,
  `uptime_ms` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vcely_dev_ts` (`device_id`,`ts`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `vcely_users`
--

CREATE TABLE IF NOT EXISTS `vcely_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passhash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3 ;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `vcely_alerts`
--
ALTER TABLE `vcely_alerts`
  ADD CONSTRAINT `vcely_alerts_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_alert_subscriptions`
--
ALTER TABLE `vcely_alert_subscriptions`
  ADD CONSTRAINT `vcely_alert_subscriptions_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vcely_alert_subscriptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `vcely_users` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_devices`
--
ALTER TABLE `vcely_devices`
  ADD CONSTRAINT `vcely_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vcely_users` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_device_keys`
--
ALTER TABLE `vcely_device_keys`
  ADD CONSTRAINT `vcely_device_keys_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_device_settings`
--
ALTER TABLE `vcely_device_settings`
  ADD CONSTRAINT `vcely_device_settings_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_device_shares`
--
ALTER TABLE `vcely_device_shares`
  ADD CONSTRAINT `fk_vcely_shares_device` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vcely_shares_user` FOREIGN KEY (`user_id`) REFERENCES `vcely_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vcely_device_shares_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vcely_device_shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `vcely_users` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_password_resets`
--
ALTER TABLE `vcely_password_resets`
  ADD CONSTRAINT `vcely_password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vcely_users` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `vcely_readings`
--
ALTER TABLE `vcely_readings`
  ADD CONSTRAINT `vcely_readings_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `vcely_devices` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
