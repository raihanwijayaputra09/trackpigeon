-- TrackPigeon fresh database schema
-- Import this file into MySQL/MariaDB to create a clean `trackpigeon` database.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `trackpigeon`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `trackpigeon`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `race_standings`;
DROP TABLE IF EXISTS `race_sponsors`;
DROP TABLE IF EXISTS `sponsors`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `ets_logs`;
DROP TABLE IF EXISTS `rfid_tags`;
DROP TABLE IF EXISTS `manual_clocking_attempts`;
DROP TABLE IF EXISTS `clockings`;
DROP TABLE IF EXISTS `ets_checkins`;
DROP TABLE IF EXISTS `device_logs`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `ets_devices`;
DROP TABLE IF EXISTS `race_registrations`;
DROP TABLE IF EXISTS `races`;
DROP TABLE IF EXISTS `detail_latihan`;
DROP TABLE IF EXISTS `latihan`;
DROP TABLE IF EXISTS `burung`;
DROP TABLE IF EXISTS `club_members`;
DROP TABLE IF EXISTS `clubs`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('superadmin','club_admin','member','device','super_admin','community_admin') NOT NULL DEFAULT 'member',
  `admin_approval_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `admin_requested_at` datetime DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `admin_approved_by` int(11) DEFAULT NULL,
  `plan` enum('free','premium') NOT NULL DEFAULT 'free',
  `plan_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `nama_kandang` varchar(140) NOT NULL,
  `nama_pemilik` varchar(140) DEFAULT NULL,
  `lat_kandang` decimal(12,8) DEFAULT NULL,
  `lon_kandang` decimal(12,8) DEFAULT NULL,
  `api_key` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`),
  KEY `fk_users_admin_approved_by` (`admin_approved_by`),
  CONSTRAINT `fk_users_admin_approved_by` FOREIGN KEY (`admin_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_clubs_owner` (`owner_user_id`),
  KEY `idx_clubs_admin` (`admin_id`),
  KEY `idx_clubs_status` (`approval_status`,`is_active`),
  KEY `fk_clubs_approved_by` (`approved_by`),
  CONSTRAINT `fk_clubs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clubs_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clubs_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `club_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `status` enum('pending','approved','banned') NOT NULL DEFAULT 'approved',
  `joined_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_club_user` (`club_id`,`user_id`),
  KEY `idx_club_members_user` (`user_id`),
  KEY `idx_club_members_status` (`club_id`,`status`),
  CONSTRAINT `fk_club_members_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_club_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `burung` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `nomor_ring` varchar(80) NOT NULL,
  `rfid_tag` varchar(32) DEFAULT NULL,
  `nama_burung` varchar(120) DEFAULT NULL,
  `warna` varchar(80) NOT NULL,
  `jenis_kelamin` varchar(20) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `bloodline` varchar(160) DEFAULT NULL,
  `induk_jantan` varchar(120) DEFAULT NULL,
  `induk_betina` varchar(120) DEFAULT NULL,
  `berat_gram` decimal(8,2) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `ukuran_file` int(11) DEFAULT NULL,
  `status` enum('aktif','hilang','pensiun','terjual') NOT NULL DEFAULT 'aktif',
  `tanggal_status` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_ring` (`user_id`,`nomor_ring`),
  UNIQUE KEY `uniq_burung_rfid` (`rfid_tag`),
  KEY `idx_burung_user` (`user_id`),
  KEY `idx_rfid_tag` (`rfid_tag`),
  KEY `idx_status` (`status`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_burung_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `latihan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `nama_sesi` varchar(140) DEFAULT NULL,
  `nama_titik_lepas` varchar(140) NOT NULL,
  `lat_lepas` decimal(12,8) NOT NULL,
  `lon_lepas` decimal(12,8) NOT NULL,
  `jarak_meter` float NOT NULL,
  `jam_lepas` datetime NOT NULL,
  `status` enum('berlangsung','selesai') NOT NULL DEFAULT 'berlangsung',
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_latihan_user` (`user_id`),
  KEY `idx_public_status` (`is_public`,`status`),
  CONSTRAINT `fk_latihan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `detail_latihan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `latihan_id` int(11) NOT NULL,
  `burung_id` int(11) NOT NULL,
  `jam_tiba` datetime DEFAULT NULL,
  `waktu_tempuh_menit` float DEFAULT NULL,
  `kecepatan_mpm` float DEFAULT NULL,
  `status_sampai` enum('0','1') NOT NULL DEFAULT '0',
  `metode_checkin` enum('manual','rfid') NOT NULL DEFAULT 'manual',
  `koefisien` float DEFAULT NULL,
  `poin` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_latihan_burung` (`latihan_id`,`burung_id`),
  KEY `fk_detail_burung` (`burung_id`),
  KEY `idx_koefisien` (`koefisien`),
  CONSTRAINT `fk_detail_burung` FOREIGN KEY (`burung_id`) REFERENCES `burung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_latihan` FOREIGN KEY (`latihan_id`) REFERENCES `latihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `races` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('latihan_bersama','lomba','latihan_mandiri') NOT NULL DEFAULT 'lomba',
  `release_point` varchar(150) NOT NULL,
  `release_lat` decimal(12,8) NOT NULL,
  `release_lng` decimal(12,8) NOT NULL,
  `release_datetime` datetime DEFAULT NULL,
  `actual_release_datetime` datetime DEFAULT NULL,
  `status` enum('draft','registration','basketing','released','finished','cancelled') NOT NULL DEFAULT 'registration',
  `max_participants` int(11) DEFAULT NULL,
  `entry_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `prize_info` text DEFAULT NULL,
  `sponsor_info` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_races_club_status` (`club_id`,`status`),
  KEY `idx_races_creator` (`created_by`),
  KEY `idx_races_release` (`release_datetime`),
  CONSTRAINT `fk_races_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_races_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `race_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bird_id` int(11) NOT NULL,
  `loft_lat` decimal(12,8) DEFAULT NULL,
  `loft_lng` decimal(12,8) DEFAULT NULL,
  `distance_km` decimal(9,3) DEFAULT NULL,
  `registration_datetime` datetime DEFAULT current_timestamp(),
  `basketing_datetime` datetime DEFAULT NULL,
  `basketing_verified` tinyint(1) NOT NULL DEFAULT 0,
  `basketing_verified_by` int(11) DEFAULT NULL,
  `payment_status` enum('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
  `status` enum('registered','pending','approved','rejected','basketed','basketing','flying','released','arrived','clocked','did_not_arrive','dnf','disqualified') NOT NULL DEFAULT 'pending',
  `basketed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_race_bird` (`race_id`,`bird_id`),
  KEY `idx_race_reg_user` (`user_id`),
  KEY `idx_race_reg_status` (`race_id`,`status`),
  KEY `idx_race_reg_bird` (`bird_id`),
  KEY `fk_race_reg_verified_by` (`basketing_verified_by`),
  CONSTRAINT `fk_race_reg_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_race_reg_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_race_reg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_race_reg_verified_by` FOREIGN KEY (`basketing_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ets_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `serial_number` varchar(120) NOT NULL,
  `device_name` varchar(140) NOT NULL,
  `device_token` varchar(128) NOT NULL,
  `secret_key` varchar(128) DEFAULT NULL,
  `status` enum('online','offline','maintenance','revoked') NOT NULL DEFAULT 'offline',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `firmware_version` varchar(60) DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `registered_at` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ets_serial` (`serial_number`),
  UNIQUE KEY `uniq_ets_token` (`device_token`),
  KEY `idx_ets_user` (`user_id`),
  KEY `idx_ets_owner` (`owner_id`),
  KEY `idx_ets_status` (`status`,`is_active`),
  CONSTRAINT `fk_ets_devices_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clockings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bird_id` int(11) NOT NULL,
  `arrival_datetime` datetime NOT NULL,
  `flight_seconds` int(11) NOT NULL,
  `distance_meter` decimal(12,2) NOT NULL,
  `speed_mpm` decimal(10,2) NOT NULL,
  `koefisien` decimal(10,2) NOT NULL,
  `method` enum('manual','ets') NOT NULL DEFAULT 'manual',
  `device_id` int(11) DEFAULT NULL,
  `ets_device_id` int(11) DEFAULT NULL,
  `rfid_tag` varchar(64) DEFAULT NULL,
  `clocking_lat` decimal(12,8) DEFAULT NULL,
  `clocking_lng` decimal(12,8) DEFAULT NULL,
  `server_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `server_verified_at` datetime(3) DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1,
  `invalidation_reason` text DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_clocking_registration` (`registration_id`),
  KEY `idx_clockings_race_speed` (`race_id`,`speed_mpm`),
  KEY `idx_clockings_user` (`user_id`),
  KEY `idx_clockings_bird` (`bird_id`),
  KEY `idx_clockings_device` (`device_id`),
  KEY `idx_clockings_ets_device` (`ets_device_id`),
  CONSTRAINT `fk_clockings_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clockings_device` FOREIGN KEY (`device_id`) REFERENCES `ets_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clockings_ets_device` FOREIGN KEY (`ets_device_id`) REFERENCES `ets_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clockings_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clockings_registration` FOREIGN KEY (`registration_id`) REFERENCES `race_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clockings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `manual_clocking_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `registration_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `bird_id` int(11) DEFAULT NULL,
  `attempted_at` datetime(3) DEFAULT current_timestamp(3),
  `result` enum('accepted','rejected') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `server_time` datetime(3) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manual_attempts_race` (`race_id`),
  KEY `idx_manual_attempts_user` (`user_id`),
  KEY `idx_manual_attempts_registration` (`registration_id`),
  KEY `idx_manual_attempts_bird` (`bird_id`),
  CONSTRAINT `fk_manual_attempts_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_manual_attempts_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_manual_attempts_registration` FOREIGN KEY (`registration_id`) REFERENCES `race_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_manual_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rfid_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bird_id` int(11) DEFAULT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `status` enum('active','inactive','lost') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rfid_user_tag` (`user_id`,`rfid_tag`),
  KEY `idx_rfid_bird` (`bird_id`),
  CONSTRAINT `fk_rfid_tags_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rfid_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ets_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `race_id` int(11) DEFAULT NULL,
  `bird_id` int(11) DEFAULT NULL,
  `rfid_tag` varchar(64) DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `status` enum('accepted','rejected','info') NOT NULL DEFAULT 'info',
  `message` varchar(500) NOT NULL,
  `payload_hash` varchar(64) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `received_at` datetime(3) DEFAULT current_timestamp(3),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ets_logs_device` (`device_id`),
  KEY `idx_ets_logs_user` (`user_id`),
  KEY `idx_ets_logs_race` (`race_id`),
  KEY `idx_ets_logs_bird` (`bird_id`),
  KEY `idx_ets_logs_rfid` (`rfid_tag`),
  CONSTRAINT `fk_ets_logs_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_logs_device` FOREIGN KEY (`device_id`) REFERENCES `ets_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_logs_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(80) NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`read_at`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sponsors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `contact_name` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `offer_text` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sponsors_active` (`is_active`),
  KEY `idx_sponsors_created_by` (`created_by`),
  CONSTRAINT `fk_sponsors_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `race_sponsors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `sponsor_id` int(11) NOT NULL,
  `package_type` enum('title','gold','silver','bronze') NOT NULL DEFAULT 'silver',
  `banner_url` varchar(255) DEFAULT NULL,
  `exposure_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_race_sponsor` (`race_id`,`sponsor_id`),
  KEY `idx_race_sponsors_sponsor` (`sponsor_id`),
  CONSTRAINT `fk_race_sponsors_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_race_sponsors_sponsor` FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `race_standings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `rank_position` int(10) unsigned DEFAULT NULL,
  `speed_mpm` decimal(10,2) DEFAULT NULL,
  `koef` decimal(12,6) DEFAULT NULL,
  `calculated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_race_standing_registration` (`registration_id`),
  KEY `idx_race_standings_race` (`race_id`,`rank_position`),
  CONSTRAINT `fk_race_standings_race` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_race_standings_registration` FOREIGN KEY (`registration_id`) REFERENCES `race_registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `type` enum('training','race','showcase','marketplace','discussion') NOT NULL DEFAULT 'discussion',
  `content` text NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_posts_user` (`user_id`),
  KEY `idx_posts_club` (`club_id`),
  KEY `idx_posts_type` (`type`),
  CONSTRAINT `fk_posts_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comments_post` (`post_id`),
  KEY `idx_comments_user` (`user_id`),
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_uid` varchar(120) NOT NULL,
  `name` varchar(140) NOT NULL,
  `api_key_hash` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `last_seen_at` datetime DEFAULT NULL,
  `firmware_version` varchar(60) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_device_uid` (`device_uid`),
  KEY `idx_devices_user` (`user_id`),
  CONSTRAINT `fk_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `level` enum('info','warning','error') NOT NULL DEFAULT 'info',
  `message` varchar(500) NOT NULL,
  `context` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_device_logs_device` (`device_id`),
  KEY `idx_device_logs_user` (`user_id`),
  CONSTRAINT `fk_device_logs_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_device_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ets_checkins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bird_id` int(11) DEFAULT NULL,
  `latihan_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `payload` longtext DEFAULT NULL,
  `checked_in_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ets_user_time` (`user_id`,`checked_in_at`),
  KEY `idx_ets_rfid` (`rfid_tag`),
  KEY `idx_ets_checkins_bird` (`bird_id`),
  KEY `idx_ets_checkins_latihan` (`latihan_id`),
  KEY `idx_ets_checkins_device` (`device_id`),
  CONSTRAINT `fk_ets_checkins_bird` FOREIGN KEY (`bird_id`) REFERENCES `burung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_checkins_device` FOREIGN KEY (`device_id`) REFERENCES `ets_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ets_checkins_latihan` FOREIGN KEY (`latihan_id`) REFERENCES `latihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ets_checkins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
