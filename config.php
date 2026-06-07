<?php
declare(strict_types=1);

function env_config(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $fallback : $value;
}

define('DB_HOST', env_config('MERPATOOLS_DB_HOST', '192.168.1.4'));
define('DB_NAME', env_config('MERPATOOLS_DB_NAME', 'trackpigeon'));
define('DB_USER', env_config('MERPATOOLS_DB_USER', 'root'));
define('DB_PASS', env_config('MERPATOOLS_DB_PASS', 'root123'));
define('GOOGLE_CLIENT_ID', env_config('MERPATOOLS_GOOGLE_CLIENT_ID'));

const APP_NAME = 'TrackPigeon';
const APP_TAGLINE = 'Lomba Adil, Data Akurat, Komunitas Kuat';
const UPLOAD_DIR = __DIR__ . '/uploads';
const APP_TIMEZONE = 'Asia/Jakarta';
const GPS_CLOCKING_MAX_DISTANCE_METER = 100.0;
const GPS_CLOCKING_MAX_ACCURACY_METER = 100.0;
const UPLOAD_MAX_SOURCE_BYTES = 8388608; // 8 MB
const PHOTO_WEBP_MAX_SIZE = 900;
const PHOTO_WEBP_QUALITY = 72;
const CLUB_LOGO_WEBP_MAX_SIZE = 512;
const CLUB_LOGO_WEBP_QUALITY = 72;

date_default_timezone_set(APP_TIMEZONE);
session_start();

function db(bool $withDatabase = true): PDO
{
    $database = $withDatabase ? ';dbname=' . DB_NAME : '';
    $dsn = 'mysql:host=' . DB_HOST . $database . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function install_database(): void
{
    $pdo = db(false);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NULL UNIQUE,
            email VARCHAR(190) NULL UNIQUE,
            google_id VARCHAR(255) NULL UNIQUE,
            avatar VARCHAR(500) NULL,
            password VARCHAR(255) NULL,
            role ENUM('superadmin','club_admin','member','device','super_admin','community_admin') NOT NULL DEFAULT 'member',
            admin_approval_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
            admin_requested_at DATETIME NULL,
            admin_approved_at DATETIME NULL,
            admin_approved_by INT NULL,
            plan ENUM('free','premium') NOT NULL DEFAULT 'free',
            plan_expires_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            email_verified TINYINT(1) NOT NULL DEFAULT 0,
            email_verified_at DATETIME NULL,
            nama_kandang VARCHAR(140) NOT NULL,
            nama_pemilik VARCHAR(140) NULL,
            lat_kandang DECIMAL(12,8) NULL,
            lon_kandang DECIMAL(12,8) NULL,
            api_key VARCHAR(64) NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS burung (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            nomor_ring VARCHAR(80) NOT NULL,
            rfid_tag VARCHAR(32) NULL UNIQUE,
            nama_burung VARCHAR(120) NULL,
            warna VARCHAR(80) NOT NULL,
            jenis_kelamin VARCHAR(20) NULL,
            tanggal_lahir DATE NULL,
            bloodline VARCHAR(160) NULL,
            induk_jantan VARCHAR(120) NULL,
            induk_betina VARCHAR(120) NULL,
            berat_gram DECIMAL(8,2) NULL,
            foto VARCHAR(255) NULL,
            ukuran_file INT NULL,
            status ENUM('aktif','hilang','pensiun','terjual') NOT NULL DEFAULT 'aktif',
            tanggal_status DATE NULL,
            catatan TEXT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS latihan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            nama_sesi VARCHAR(140) NULL,
            nama_titik_lepas VARCHAR(140) NOT NULL,
            lat_lepas DECIMAL(12,8) NOT NULL,
            lon_lepas DECIMAL(12,8) NOT NULL,
            jarak_meter FLOAT NOT NULL,
            jam_lepas DATETIME NOT NULL,
            status ENUM('berlangsung','selesai') NOT NULL DEFAULT 'berlangsung',
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clubs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            owner_user_id INT NULL,
            admin_id INT NULL,
            city VARCHAR(120) NULL,
            province VARCHAR(120) NULL,
            logo VARCHAR(255) NULL,
            description TEXT NULL,
            approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
            approved_by INT NULL,
            approved_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_clubs_owner (owner_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS club_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('admin','member') NOT NULL DEFAULT 'member',
            status ENUM('pending','approved','banned') NOT NULL DEFAULT 'approved',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_club_user (club_id, user_id),
            INDEX idx_club_members_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS races (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NULL,
            name VARCHAR(150) NOT NULL,
            type ENUM('latihan_bersama','lomba','latihan_mandiri') NOT NULL DEFAULT 'lomba',
            release_point VARCHAR(150) NOT NULL,
            release_lat DECIMAL(12,8) NOT NULL,
            release_lng DECIMAL(12,8) NOT NULL,
            release_datetime DATETIME NULL,
            actual_release_datetime DATETIME NULL,
            status ENUM('draft','registration','basketing','released','finished','cancelled') NOT NULL DEFAULT 'registration',
            max_participants INT NULL,
            entry_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            prize_info TEXT NULL,
            sponsor_info TEXT NULL,
            notes TEXT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_races_club_status (club_id, status),
            INDEX idx_races_creator (created_by),
            INDEX idx_races_release (release_datetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS race_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            race_id INT NOT NULL,
            user_id INT NOT NULL,
            bird_id INT NOT NULL,
            loft_lat DECIMAL(12,8) NULL,
            loft_lng DECIMAL(12,8) NULL,
            distance_km DECIMAL(9,3) NULL,
            registration_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
            basketing_datetime DATETIME NULL,
            basketing_verified TINYINT(1) NOT NULL DEFAULT 0,
            basketing_verified_by INT NULL,
            payment_status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
            status ENUM('registered','pending','approved','rejected','basketed','basketing','flying','released','arrived','clocked','did_not_arrive','dnf','disqualified') NOT NULL DEFAULT 'pending',
            basketed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_race_bird (race_id, bird_id),
            INDEX idx_race_reg_user (user_id),
            INDEX idx_race_reg_status (race_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clockings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            race_id INT NOT NULL,
            registration_id INT NOT NULL,
            user_id INT NOT NULL,
            bird_id INT NOT NULL,
            arrival_datetime DATETIME NOT NULL,
            flight_seconds INT NOT NULL,
            distance_meter DECIMAL(12,2) NOT NULL,
            speed_mpm DECIMAL(10,2) NOT NULL,
            koefisien DECIMAL(10,2) NOT NULL,
            method ENUM('manual','ets') NOT NULL DEFAULT 'manual',
            device_id INT NULL,
            ets_device_id INT NULL,
            rfid_tag VARCHAR(64) NULL,
            clocking_lat DECIMAL(12,8) NULL,
            clocking_lng DECIMAL(12,8) NULL,
            server_time DATETIME NOT NULL,
            ip_address VARCHAR(45) NULL,
            device_fingerprint VARCHAR(255) NULL,
            server_verified_at DATETIME(3) NULL,
            is_valid TINYINT(1) NOT NULL DEFAULT 1,
            invalidation_reason TEXT NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_clocking_registration (registration_id),
            INDEX idx_clockings_race_speed (race_id, speed_mpm),
            INDEX idx_clockings_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manual_clocking_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            race_id INT NOT NULL,
            registration_id INT NULL,
            user_id INT NOT NULL,
            bird_id INT NULL,
            attempted_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
            result ENUM('accepted','rejected') NOT NULL,
            reason VARCHAR(255) NULL,
            reject_reason VARCHAR(255) NULL,
            server_time DATETIME(3) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_manual_attempts_race (race_id),
            INDEX idx_manual_attempts_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ets_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            owner_id INT NULL,
            serial_number VARCHAR(120) NOT NULL,
            device_name VARCHAR(140) NOT NULL,
            device_token VARCHAR(128) NOT NULL,
            secret_key VARCHAR(128) NULL,
            status ENUM('online','offline','maintenance','revoked') NOT NULL DEFAULT 'offline',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            firmware_version VARCHAR(60) NULL,
            last_sync_at DATETIME NULL,
            last_ip VARCHAR(45) NULL,
            registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_ets_serial (serial_number),
            UNIQUE KEY uniq_ets_token (device_token),
            INDEX idx_ets_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rfid_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bird_id INT NULL,
            rfid_tag VARCHAR(64) NOT NULL,
            status ENUM('active','inactive','lost') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_rfid_user_tag (user_id, rfid_tag),
            INDEX idx_rfid_bird (bird_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ets_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NULL,
            user_id INT NULL,
            race_id INT NULL,
            bird_id INT NULL,
            rfid_tag VARCHAR(64) NULL,
            event_type VARCHAR(80) NOT NULL,
            status ENUM('accepted','rejected','info') NOT NULL DEFAULT 'info',
            message VARCHAR(500) NOT NULL,
            payload_hash VARCHAR(64) NULL,
            raw_payload TEXT NULL,
            payload LONGTEXT NULL,
            received_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
            processed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ets_logs_device (device_id),
            INDEX idx_ets_logs_user (user_id),
            INDEX idx_ets_logs_race (race_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(80) NOT NULL,
            title VARCHAR(160) NOT NULL,
            body VARCHAR(500) NULL,
            link VARCHAR(255) NULL,
            read_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user_read (user_id, read_at),
            INDEX idx_notifications_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sponsors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            contact_name VARCHAR(120) NULL,
            phone VARCHAR(40) NULL,
            logo VARCHAR(255) NULL,
            website VARCHAR(255) NULL,
            offer_text VARCHAR(500) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sponsors_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS race_sponsors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            race_id INT NOT NULL,
            sponsor_id INT NOT NULL,
            package_type ENUM('title','gold','silver','bronze') NOT NULL DEFAULT 'silver',
            banner_url VARCHAR(255) NULL,
            exposure_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_race_sponsor (race_id, sponsor_id),
            INDEX idx_race_sponsors_sponsor (sponsor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS race_standings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            race_id INT NOT NULL,
            registration_id INT NOT NULL,
            rank_position INT UNSIGNED NULL,
            speed_mpm DECIMAL(10,2) NULL,
            koef DECIMAL(12,6) NULL,
            calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_race_standing_registration (registration_id),
            INDEX idx_race_standings_race (race_id, rank_position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            club_id INT NULL,
            type ENUM('training','race','showcase','marketplace','discussion') NOT NULL DEFAULT 'discussion',
            content TEXT NOT NULL,
            media_url VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_posts_user (user_id),
            INDEX idx_posts_club (club_id),
            INDEX idx_posts_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comments_post (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_uid VARCHAR(120) NOT NULL,
            name VARCHAR(140) NOT NULL,
            api_key_hash VARCHAR(255) NULL,
            status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
            last_seen_at DATETIME NULL,
            firmware_version VARCHAR(60) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_device_uid (device_uid),
            INDEX idx_devices_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS device_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NULL,
            user_id INT NULL,
            level ENUM('info','warning','error') NOT NULL DEFAULT 'info',
            message VARCHAR(500) NOT NULL,
            context LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_logs_device (device_id),
            INDEX idx_device_logs_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ets_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bird_id INT NULL,
            latihan_id INT NULL,
            device_id INT NULL,
            rfid_tag VARCHAR(64) NOT NULL,
            payload LONGTEXT NULL,
            checked_in_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ets_user_time (user_id, checked_in_at),
            INDEX idx_ets_rfid (rfid_tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            metadata LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS detail_latihan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            latihan_id INT NOT NULL,
            burung_id INT NOT NULL,
            jam_tiba DATETIME NULL,
            waktu_tempuh_menit FLOAT NULL,
            kecepatan_mpm FLOAT NULL,
            status_sampai ENUM('0','1') NOT NULL DEFAULT '0',
            metode_checkin ENUM('manual','rfid') NOT NULL DEFAULT 'manual',
            koefisien FLOAT NULL,
            poin INT NULL,
            CONSTRAINT fk_detail_latihan FOREIGN KEY (latihan_id) REFERENCES latihan(id) ON DELETE CASCADE,
            CONSTRAINT fk_detail_burung FOREIGN KEY (burung_id) REFERENCES burung(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_latihan_burung (latihan_id, burung_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!column_exists($pdo, 'users', 'nama_pemilik')) {
        $pdo->exec('ALTER TABLE users ADD nama_pemilik VARCHAR(140) NULL AFTER nama_kandang');
    }
    if (!column_exists($pdo, 'users', 'email')) {
        $pdo->exec('ALTER TABLE users ADD email VARCHAR(190) NULL UNIQUE AFTER username');
    }
    if (!column_exists($pdo, 'users', 'google_id')) {
        $pdo->exec('ALTER TABLE users ADD google_id VARCHAR(255) NULL UNIQUE AFTER email');
    }
    if (!column_exists($pdo, 'users', 'avatar')) {
        $pdo->exec('ALTER TABLE users ADD avatar VARCHAR(500) NULL AFTER google_id');
    }
    if (!column_exists($pdo, 'users', 'role')) {
        $pdo->exec("ALTER TABLE users ADD role ENUM('superadmin','club_admin','member','device','super_admin','community_admin') NOT NULL DEFAULT 'member' AFTER password");
    } else {
        try {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('superadmin','club_admin','member','device','super_admin','community_admin') NOT NULL DEFAULT 'member'");
        } catch (Throwable) {
            // Keep legacy installs alive if their MySQL variant rejects enum changes.
        }
    }
    if (!column_exists($pdo, 'users', 'email_verified_at')) {
        $pdo->exec('ALTER TABLE users ADD email_verified_at DATETIME NULL AFTER role');
    }
    if (!column_exists($pdo, 'users', 'admin_approval_status')) {
        $pdo->exec("ALTER TABLE users ADD admin_approval_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none' AFTER role");
    }
    if (!column_exists($pdo, 'users', 'admin_requested_at')) {
        $pdo->exec('ALTER TABLE users ADD admin_requested_at DATETIME NULL AFTER admin_approval_status');
    }
    if (!column_exists($pdo, 'users', 'admin_approved_at')) {
        $pdo->exec('ALTER TABLE users ADD admin_approved_at DATETIME NULL AFTER admin_requested_at');
    }
    if (!column_exists($pdo, 'users', 'admin_approved_by')) {
        $pdo->exec('ALTER TABLE users ADD admin_approved_by INT NULL AFTER admin_approved_at');
    }
    try {
        $pdo->exec("UPDATE users SET admin_approval_status = 'approved' WHERE role IN ('club_admin','community_admin','superadmin','super_admin') AND admin_approval_status = 'none'");
        $pdo->exec("
            UPDATE users
            SET role = 'superadmin', admin_approval_status = 'approved', admin_approved_at = COALESCE(admin_approved_at, NOW())
            WHERE id = (SELECT first_user_id FROM (SELECT MIN(id) AS first_user_id FROM users) bootstrap)
              AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM users WHERE role IN ('superadmin','super_admin') LIMIT 1) existing_super_admin)
        ");
    } catch (Throwable) {
        // Legacy tables without the new columns are handled by the checks above.
    }
    try {
        $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(80) NULL');
        $pdo->exec('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
    } catch (Throwable) {
        // Older MySQL variants may reject no-op MODIFY operations; existing installs can continue.
    }
    if (!column_exists($pdo, 'users', 'lat_kandang')) {
        $pdo->exec('ALTER TABLE users ADD lat_kandang DECIMAL(12,8) NULL AFTER nama_pemilik');
    }
    if (!column_exists($pdo, 'users', 'lon_kandang')) {
        $pdo->exec('ALTER TABLE users ADD lon_kandang DECIMAL(12,8) NULL AFTER lat_kandang');
    }
    if (!column_exists($pdo, 'users', 'api_key')) {
        $pdo->exec('ALTER TABLE users ADD api_key VARCHAR(64) NULL UNIQUE AFTER lon_kandang');
    }
    if (!column_exists($pdo, 'users', 'phone')) {
        $pdo->exec('ALTER TABLE users ADD phone VARCHAR(30) NULL AFTER email_verified_at');
    }
    if (!column_exists($pdo, 'users', 'address')) {
        $pdo->exec('ALTER TABLE users ADD address TEXT NULL AFTER phone');
    }
    if (!column_exists($pdo, 'users', 'plan')) {
        $pdo->exec("ALTER TABLE users ADD plan ENUM('free','premium') NOT NULL DEFAULT 'free' AFTER role");
    }
    if (!column_exists($pdo, 'users', 'plan_expires_at')) {
        $pdo->exec('ALTER TABLE users ADD plan_expires_at DATETIME NULL AFTER plan');
    }
    if (!column_exists($pdo, 'users', 'is_active')) {
        $pdo->exec('ALTER TABLE users ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER plan_expires_at');
    }
    if (!column_exists($pdo, 'users', 'email_verified')) {
        $pdo->exec('ALTER TABLE users ADD email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    }
    if (!column_exists($pdo, 'burung', 'user_id')) {
        $pdo->exec('ALTER TABLE burung ADD user_id INT NULL AFTER id');
    }
    if (!column_exists($pdo, 'burung', 'rfid_tag')) {
        $pdo->exec('ALTER TABLE burung ADD rfid_tag VARCHAR(32) NULL UNIQUE AFTER nomor_ring');
    }
    if (!column_exists($pdo, 'burung', 'nama_burung')) {
        $pdo->exec('ALTER TABLE burung ADD nama_burung VARCHAR(120) NULL AFTER rfid_tag');
    }
    if (!column_exists($pdo, 'burung', 'tanggal_lahir')) {
        $pdo->exec('ALTER TABLE burung ADD tanggal_lahir DATE NULL AFTER jenis_kelamin');
    }
    if (!column_exists($pdo, 'burung', 'bloodline')) {
        $pdo->exec('ALTER TABLE burung ADD bloodline VARCHAR(160) NULL AFTER tanggal_lahir');
    }
    if (!column_exists($pdo, 'burung', 'induk_jantan')) {
        $pdo->exec('ALTER TABLE burung ADD induk_jantan VARCHAR(120) NULL AFTER bloodline');
    }
    if (!column_exists($pdo, 'burung', 'induk_betina')) {
        $pdo->exec('ALTER TABLE burung ADD induk_betina VARCHAR(120) NULL AFTER induk_jantan');
    }
    if (!column_exists($pdo, 'burung', 'berat_gram')) {
        $pdo->exec('ALTER TABLE burung ADD berat_gram DECIMAL(8,2) NULL AFTER induk_betina');
    }
    if (!column_exists($pdo, 'burung', 'aktif')) {
        $pdo->exec('ALTER TABLE burung ADD aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER foto');
    }
    if (!column_exists($pdo, 'burung', 'ukuran_file')) {
        $pdo->exec('ALTER TABLE burung ADD ukuran_file INT NULL AFTER foto');
    }
    if (!column_exists($pdo, 'burung', 'status')) {
        $pdo->exec("ALTER TABLE burung ADD status ENUM('aktif','hilang','pensiun','terjual') NOT NULL DEFAULT 'aktif' AFTER ukuran_file");
    }
    if (!column_exists($pdo, 'burung', 'tanggal_status')) {
        $pdo->exec('ALTER TABLE burung ADD tanggal_status DATE NULL AFTER status');
    }
    if (!column_exists($pdo, 'burung', 'catatan')) {
        $pdo->exec('ALTER TABLE burung ADD catatan TEXT NULL AFTER tanggal_status');
    }
    if (!column_exists($pdo, 'latihan', 'user_id')) {
        $pdo->exec('ALTER TABLE latihan ADD user_id INT NULL AFTER id');
    }
    if (!column_exists($pdo, 'latihan', 'nama_sesi')) {
        $pdo->exec('ALTER TABLE latihan ADD nama_sesi VARCHAR(140) NULL AFTER user_id');
    }
    if (!column_exists($pdo, 'latihan', 'is_public')) {
        $pdo->exec('ALTER TABLE latihan ADD is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
    }
    if (!column_exists($pdo, 'detail_latihan', 'metode_checkin')) {
        $pdo->exec("ALTER TABLE detail_latihan ADD metode_checkin ENUM('manual','rfid') NOT NULL DEFAULT 'manual' AFTER status_sampai");
    }
    if (!column_exists($pdo, 'detail_latihan', 'koefisien')) {
        $pdo->exec('ALTER TABLE detail_latihan ADD koefisien FLOAT NULL AFTER metode_checkin');
    }
    if (!column_exists($pdo, 'detail_latihan', 'poin')) {
        $pdo->exec('ALTER TABLE detail_latihan ADD poin INT NULL AFTER koefisien');
    }
    if (!column_exists($pdo, 'clubs', 'province')) {
        $pdo->exec('ALTER TABLE clubs ADD province VARCHAR(120) NULL AFTER city');
    }
    if (!column_exists($pdo, 'clubs', 'admin_id')) {
        $pdo->exec('ALTER TABLE clubs ADD admin_id INT NULL AFTER owner_user_id');
    }
    if (!column_exists($pdo, 'clubs', 'logo')) {
        $pdo->exec('ALTER TABLE clubs ADD logo VARCHAR(255) NULL AFTER province');
    }
    if (!column_exists($pdo, 'clubs', 'approval_status')) {
        $pdo->exec("ALTER TABLE clubs ADD approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER description");
    }
    if (!column_exists($pdo, 'clubs', 'approved_by')) {
        $pdo->exec('ALTER TABLE clubs ADD approved_by INT NULL AFTER approval_status');
    }
    if (!column_exists($pdo, 'clubs', 'approved_at')) {
        $pdo->exec('ALTER TABLE clubs ADD approved_at DATETIME NULL AFTER approved_by');
    }
    if (!column_exists($pdo, 'clubs', 'is_active')) {
        $pdo->exec('ALTER TABLE clubs ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER approved_at');
    }
    if (!column_exists($pdo, 'club_members', 'status')) {
        $pdo->exec("ALTER TABLE club_members ADD status ENUM('pending','approved','banned') NOT NULL DEFAULT 'approved' AFTER role");
    }
    try {
        $pdo->exec('UPDATE clubs SET admin_id = owner_user_id WHERE admin_id IS NULL AND owner_user_id IS NOT NULL');
        $pdo->exec("UPDATE clubs SET approval_status = 'approved', approved_at = COALESCE(approved_at, created_at) WHERE is_active = 1 AND approval_status = 'pending'");
    } catch (Throwable) {
        // Backfill is best-effort for legacy databases.
    }

    if (!column_exists($pdo, 'race_registrations', 'registration_datetime')) {
        $pdo->exec('ALTER TABLE race_registrations ADD registration_datetime DATETIME DEFAULT CURRENT_TIMESTAMP AFTER distance_km');
    }
    if (!column_exists($pdo, 'race_registrations', 'basketing_datetime')) {
        $pdo->exec('ALTER TABLE race_registrations ADD basketing_datetime DATETIME NULL AFTER registration_datetime');
    }
    if (!column_exists($pdo, 'race_registrations', 'basketing_verified')) {
        $pdo->exec('ALTER TABLE race_registrations ADD basketing_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER basketing_datetime');
    }
    if (!column_exists($pdo, 'race_registrations', 'basketing_verified_by')) {
        $pdo->exec('ALTER TABLE race_registrations ADD basketing_verified_by INT NULL AFTER basketing_verified');
    }
    if (!column_exists($pdo, 'race_registrations', 'payment_status')) {
        $pdo->exec("ALTER TABLE race_registrations ADD payment_status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid' AFTER basketing_verified_by");
    }
    try {
        $pdo->exec("ALTER TABLE race_registrations MODIFY status ENUM('registered','pending','approved','rejected','basketed','basketing','flying','released','arrived','clocked','did_not_arrive','dnf','disqualified') NOT NULL DEFAULT 'pending'");
    } catch (Throwable) {
        // Existing status values can keep their current enum if modification is blocked.
    }

    foreach ([
        'ets_device_id' => 'ALTER TABLE clockings ADD ets_device_id INT NULL AFTER device_id',
        'clocking_lat' => 'ALTER TABLE clockings ADD clocking_lat DECIMAL(12,8) NULL AFTER rfid_tag',
        'clocking_lng' => 'ALTER TABLE clockings ADD clocking_lng DECIMAL(12,8) NULL AFTER clocking_lat',
        'device_fingerprint' => 'ALTER TABLE clockings ADD device_fingerprint VARCHAR(255) NULL AFTER ip_address',
        'server_verified_at' => 'ALTER TABLE clockings ADD server_verified_at DATETIME(3) NULL AFTER device_fingerprint',
        'is_valid' => 'ALTER TABLE clockings ADD is_valid TINYINT(1) NOT NULL DEFAULT 1 AFTER server_verified_at',
        'invalidation_reason' => 'ALTER TABLE clockings ADD invalidation_reason TEXT NULL AFTER is_valid',
    ] as $column => $sql) {
        if (!column_exists($pdo, 'clockings', $column)) {
            $pdo->exec($sql);
        }
    }

    if (!column_exists($pdo, 'manual_clocking_attempts', 'attempted_at')) {
        $pdo->exec('ALTER TABLE manual_clocking_attempts ADD attempted_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) AFTER bird_id');
    }
    if (!column_exists($pdo, 'manual_clocking_attempts', 'reject_reason')) {
        $pdo->exec('ALTER TABLE manual_clocking_attempts ADD reject_reason VARCHAR(255) NULL AFTER reason');
    }

    foreach ([
        'owner_id' => 'ALTER TABLE ets_devices ADD owner_id INT NULL AFTER user_id',
        'is_active' => 'ALTER TABLE ets_devices ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
        'last_ip' => 'ALTER TABLE ets_devices ADD last_ip VARCHAR(45) NULL AFTER last_sync_at',
        'registered_at' => 'ALTER TABLE ets_devices ADD registered_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER last_ip',
    ] as $column => $sql) {
        if (!column_exists($pdo, 'ets_devices', $column)) {
            $pdo->exec($sql);
        }
    }
    try {
        $pdo->exec('UPDATE ets_devices SET owner_id = user_id WHERE owner_id IS NULL');
    } catch (Throwable) {
        // Optional PRD alias column.
    }

    foreach ([
        'payload_hash' => 'ALTER TABLE ets_logs ADD payload_hash VARCHAR(64) NULL AFTER message',
        'raw_payload' => 'ALTER TABLE ets_logs ADD raw_payload TEXT NULL AFTER payload_hash',
        'received_at' => 'ALTER TABLE ets_logs ADD received_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) AFTER payload',
        'processed' => 'ALTER TABLE ets_logs ADD processed TINYINT(1) NOT NULL DEFAULT 0 AFTER received_at',
    ] as $column => $sql) {
        if (!column_exists($pdo, 'ets_logs', $column)) {
            $pdo->exec($sql);
        }
    }

    if (!column_exists($pdo, 'sponsors', 'website')) {
        $pdo->exec('ALTER TABLE sponsors ADD website VARCHAR(255) NULL AFTER logo');
    }
    if (!column_exists($pdo, 'race_sponsors', 'package_type')) {
        $pdo->exec("ALTER TABLE race_sponsors ADD package_type ENUM('title','gold','silver','bronze') NOT NULL DEFAULT 'silver' AFTER sponsor_id");
    }
    if (!column_exists($pdo, 'race_sponsors', 'banner_url')) {
        $pdo->exec('ALTER TABLE race_sponsors ADD banner_url VARCHAR(255) NULL AFTER package_type');
    }

    if (!index_exists($pdo, 'burung', 'uniq_user_ring')) {
        try {
            if (index_exists($pdo, 'burung', 'nomor_ring')) {
                $pdo->exec('ALTER TABLE burung DROP INDEX nomor_ring');
            }
            $pdo->exec('ALTER TABLE burung ADD UNIQUE KEY uniq_user_ring (user_id, nomor_ring)');
        } catch (Throwable) {
            // Keep the app usable even if a legacy duplicate blocks index creation.
        }
    }
    if (!index_exists($pdo, 'burung', 'idx_burung_user')) {
        $pdo->exec('ALTER TABLE burung ADD INDEX idx_burung_user (user_id)');
    }
    if (!index_exists($pdo, 'latihan', 'idx_latihan_user')) {
        $pdo->exec('ALTER TABLE latihan ADD INDEX idx_latihan_user (user_id)');
    }
    if (!index_exists($pdo, 'latihan', 'idx_public_status')) {
        $pdo->exec('ALTER TABLE latihan ADD INDEX idx_public_status (is_public, status)');
    }
    if (!index_exists($pdo, 'burung', 'idx_rfid_tag')) {
        try {
            $pdo->exec('ALTER TABLE burung ADD INDEX idx_rfid_tag (rfid_tag)');
        } catch (Throwable) {
            // Existing unique constraints may already cover RFID lookups.
        }
    }
    if (!index_exists($pdo, 'detail_latihan', 'idx_koefisien')) {
        $pdo->exec('ALTER TABLE detail_latihan ADD INDEX idx_koefisien (koefisien)');
    }

    $legacyCount = (int)$pdo->query('SELECT COUNT(*) FROM burung WHERE user_id IS NULL')->fetchColumn();
    if ($legacyCount > 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO users (username, password, nama_kandang, nama_pemilik, lat_kandang, lon_kandang)
            SELECT 'admin', ?, COALESCE((SELECT nama FROM kandang WHERE id = 1), 'Kandang Utama'), 'Pemilik Kandang',
                   (SELECT latitude FROM kandang WHERE id = 1), (SELECT longitude FROM kandang WHERE id = 1)
            WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin')
        ")->execute([$password]);
        $adminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
        $pdo->prepare('UPDATE burung SET user_id = ? WHERE user_id IS NULL')->execute([$adminId]);
        $pdo->prepare('UPDATE latihan SET user_id = ? WHERE user_id IS NULL')->execute([$adminId]);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    if (!headers_sent()) {
        header('Location: ' . $path);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($path) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function normalize_role(?string $role): string
{
    return match ($role) {
        'super_admin', 'superadmin' => 'superadmin',
        'community_admin', 'club_admin' => 'club_admin',
        default => 'member',
    };
}

function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function require_any_role(array $roles): void
{
    $allowed = array_map('normalize_role', $roles);
    $current = current_user_role();
    if ($current === null || !in_array($current, $allowed, true)) {
        redirect('index.php?page=login&error=unauthorized');
    }
}

function require_role(string $role): void
{
    require_any_role([$role]);
}

function is_club_admin(): bool
{
    return current_user_role() === 'club_admin';
}

function is_super_admin(): bool
{
    return current_user_role() === 'superadmin';
}

function is_member(): bool
{
    return current_user_role() === 'member';
}

function user_is_club_admin(PDO $pdo, int $userId, ?int $clubId = null): bool
{
    if (is_super_admin()) {
        return true;
    }

    $sql = "
        SELECT COUNT(*)
        FROM club_members cm
        JOIN clubs c ON c.id = cm.club_id
        WHERE cm.user_id = ? AND cm.role = 'admin' AND cm.status = 'approved' AND c.is_active = 1 AND c.approval_status = 'approved'
    ";
    $params = [$userId];
    if ($clubId !== null) {
        $sql .= ' AND cm.club_id = ?';
        $params[] = $clubId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function user_can_manage_race(PDO $pdo, int $raceId, int $userId): ?array
{
    $stmt = $pdo->prepare('
        SELECT r.*, c.name AS club_name
        FROM races r
        LEFT JOIN clubs c ON c.id = r.club_id
        WHERE r.id = ?
    ');
    $stmt->execute([$raceId]);
    $race = $stmt->fetch();
    if (!$race) {
        return null;
    }

    if (is_super_admin() || (int)$race['created_by'] === $userId) {
        return $race;
    }

    if (!empty($race['club_id']) && user_is_club_admin($pdo, $userId, (int)$race['club_id'])) {
        return $race;
    }

    return null;
}

function manual_clocking_cooldown_seconds(PDO $pdo, int $userId, int $birdId, ?DateTimeInterface $now = null): int
{
    $stmt = $pdo->prepare('
        SELECT COALESCE(attempted_at, server_time, created_at) AS last_attempt
        FROM manual_clocking_attempts
        WHERE user_id = ? AND bird_id = ?
        ORDER BY COALESCE(attempted_at, server_time, created_at) DESC
        LIMIT 1
    ');
    $stmt->execute([$userId, $birdId]);
    $last = $stmt->fetchColumn();
    if (!$last) {
        return 0;
    }

    $current = $now ? $now->getTimestamp() : time();
    $elapsed = $current - app_datetime((string)$last)->getTimestamp();
    return max(0, 30 - $elapsed);
}

function require_login(): int
{
    $id = current_user_id();
    if (!$id) {
        redirect('index.php?page=login');
    }
    return $id;
}

function current_user(PDO $pdo): ?array
{
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        return null;
    }
    if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        return null;
    }
    $_SESSION['user_role'] = normalize_role($user['role'] ?? 'member');
    return $user;
}

function profile_complete(?array $user): bool
{
    return $user && trim((string)($user['nama_pemilik'] ?? '')) !== ''
        && $user['lat_kandang'] !== null && $user['lon_kandang'] !== null;
}

function slugify_value(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string)$slug, '-') ?: 'trackpigeon';
}

function client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $ip = trim(explode(',', (string)$candidate)[0]);
        if ($ip !== '') {
            return substr($ip, 0, 45);
        }
    }
    return 'unknown';
}

function log_audit(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable) {
        // Audit logging should never block the main user action.
    }
}

function notify_user(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $title, $body, $link]);
}

function notify_super_admins(PDO $pdo, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('superadmin','super_admin') AND COALESCE(is_active, 1) = 1");
    foreach ($stmt->fetchAll() as $row) {
        notify_user($pdo, (int)$row['id'], $type, $title, $body, $link);
    }
}

function notify_club_admins(PDO $pdo, int $clubId, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM users u
        JOIN club_members cm ON cm.user_id = u.id
        JOIN clubs c ON c.id = cm.club_id
        WHERE cm.club_id = ?
          AND cm.role = 'admin'
          AND cm.status = 'approved'
          AND c.is_active = 1
          AND COALESCE(u.is_active, 1) = 1
    ");
    $stmt->execute([$clubId]);
    foreach ($stmt->fetchAll() as $row) {
        notify_user($pdo, (int)$row['id'], $type, $title, $body, $link);
    }
}

function user_is_approved_club_member(PDO $pdo, int $userId, int $clubId): bool
{
    if (is_super_admin()) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM club_members cm
        JOIN clubs c ON c.id = cm.club_id
        WHERE cm.user_id = ?
          AND cm.club_id = ?
          AND cm.status = 'approved'
          AND c.is_active = 1
          AND c.approval_status = 'approved'
    ");
    $stmt->execute([$userId, $clubId]);
    return (int)$stmt->fetchColumn() > 0;
}

function haversine_meter(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

function nullable_float(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }
    $normalized = str_replace(',', '.', trim((string)$value));
    return is_numeric($normalized) ? (float)$normalized : null;
}

function valid_lat_lng(?float $lat, ?float $lng): bool
{
    return $lat !== null && $lng !== null
        && $lat >= -90 && $lat <= 90
        && $lng >= -180 && $lng <= 180;
}

function validate_clocking_gps(?float $clockLat, ?float $clockLng, ?float $accuracyMeter, ?float $loftLat, ?float $loftLng, bool $requireAccuracy = false): array
{
    if (!valid_lat_lng($loftLat, $loftLng)) {
        return [
            'ok' => false,
            'message' => 'Koordinat kandang belum valid. Lengkapi Profil Kandang lebih dulu.',
            'distance_meter' => null,
            'accuracy_meter' => $accuracyMeter,
        ];
    }
    if (!valid_lat_lng($clockLat, $clockLng)) {
        return [
            'ok' => false,
            'message' => 'GPS clocking wajib aktif. Izinkan lokasi akurat sebelum clocking.',
            'distance_meter' => null,
            'accuracy_meter' => $accuracyMeter,
        ];
    }
    if ($accuracyMeter !== null && $accuracyMeter <= 0) {
        return [
            'ok' => false,
            'message' => 'Akurasi GPS tidak valid. Ambil ulang lokasi.',
            'distance_meter' => null,
            'accuracy_meter' => $accuracyMeter,
        ];
    }
    if ($requireAccuracy && $accuracyMeter === null) {
        return [
            'ok' => false,
            'message' => 'Akurasi GPS wajib dikirim untuk clocking manual.',
            'distance_meter' => null,
            'accuracy_meter' => null,
        ];
    }
    if ($accuracyMeter !== null && $accuracyMeter > GPS_CLOCKING_MAX_ACCURACY_METER) {
        return [
            'ok' => false,
            'message' => 'Akurasi GPS ' . number_format($accuracyMeter, 0, ',', '.') . ' m belum cukup. Maksimal ' . number_format(GPS_CLOCKING_MAX_ACCURACY_METER, 0, ',', '.') . ' m.',
            'distance_meter' => null,
            'accuracy_meter' => $accuracyMeter,
        ];
    }

    $distance = haversine_meter($clockLat, $clockLng, $loftLat, $loftLng);
    if ($distance > GPS_CLOCKING_MAX_DISTANCE_METER) {
        return [
            'ok' => false,
            'message' => 'Clocking ditolak. Posisi GPS berjarak ' . number_format($distance, 0, ',', '.') . ' m dari koordinat kandang, maksimal ' . number_format(GPS_CLOCKING_MAX_DISTANCE_METER, 0, ',', '.') . ' m.',
            'distance_meter' => $distance,
            'accuracy_meter' => $accuracyMeter,
        ];
    }

    return [
        'ok' => true,
        'message' => 'GPS valid.',
        'distance_meter' => $distance,
        'accuracy_meter' => $accuracyMeter,
    ];
}

function recalculate_race_standings(PDO $pdo, int $raceId): void
{
    $stmt = $pdo->prepare('
        SELECT rr.id AS registration_id, c.speed_mpm, c.koefisien
        FROM race_registrations rr
        JOIN clockings c ON c.registration_id = rr.id
        WHERE rr.race_id = ? AND COALESCE(c.is_valid, 1) = 1
        ORDER BY c.speed_mpm DESC, c.arrival_datetime ASC
    ');
    $stmt->execute([$raceId]);
    $rows = $stmt->fetchAll();

    $upsert = $pdo->prepare('
        INSERT INTO race_standings (race_id, registration_id, rank_position, speed_mpm, koef, calculated_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            rank_position = VALUES(rank_position),
            speed_mpm = VALUES(speed_mpm),
            koef = VALUES(koef),
            calculated_at = VALUES(calculated_at)
    ');
    foreach ($rows as $index => $row) {
        $upsert->execute([
            $raceId,
            (int)$row['registration_id'],
            $index + 1,
            $row['speed_mpm'] !== null ? (float)$row['speed_mpm'] : null,
            $row['koefisien'] !== null ? (float)$row['koefisien'] : null,
        ]);
    }
}

function api_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function app_datetime(?string $value = null): DateTimeImmutable
{
    return new DateTimeImmutable($value ?? 'now', new DateTimeZone(APP_TIMEZONE));
}

function format_duration(float $minutes): string
{
    $totalSeconds = (int)round($minutes * 60);
    $hours = intdiv($totalSeconds, 3600);
    $remaining = $totalSeconds % 3600;
    $mins = intdiv($remaining, 60);
    $seconds = $remaining % 60;

    if ($hours > 0) {
        return sprintf('%d jam %d menit %d detik', $hours, $mins, $seconds);
    }
    if ($mins > 0) {
        return sprintf('%d menit %d detik', $mins, $seconds);
    }
    return sprintf('%d detik', $seconds);
}

function delete_uploaded_photo(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }
    $target = __DIR__ . '/' . $path;
    if (is_file($target)) {
        @unlink($target);
    }
}

function safe_photo_name(string $label, string $prefix = 'media'): string
{
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($label));
    $base = trim((string)$base, '_') ?: $prefix;
    return $prefix . '_' . $base . '_' . bin2hex(random_bytes(5)) . '.webp';
}

function image_resource_from_upload(string $tmpName, string $mime)
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmpName),
        'image/png' => imagecreatefrompng($tmpName),
        'image/webp' => imagecreatefromwebp($tmpName),
        default => false,
    };
}

function save_webp_upload(array $file, string $label, int $maxSize = PHOTO_WEBP_MAX_SIZE, int $quality = PHOTO_WEBP_QUALITY, string $prefix = 'photo'): array
{
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File upload tidak valid.');
    }
    if ((int)($file['size'] ?? 0) > UPLOAD_MAX_SOURCE_BYTES) {
        throw new RuntimeException('Ukuran file maksimal 8MB sebelum kompresi.');
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('Ekstensi GD PHP wajib aktif untuk konversi WebP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $source = image_resource_from_upload($file['tmp_name'], $mime);
    if (!$source) {
        throw new RuntimeException('Foto tidak dapat dibaca.');
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width > $maxSize || $height > $maxSize) {
        $scale = min($maxSize / $width, $maxSize / $height);
        $newWidth = max(1, (int)round($width * $scale));
        $newHeight = max(1, (int)round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);
        $source = $resized;
    }

    $name = safe_photo_name($label, $prefix);
    $target = UPLOAD_DIR . '/' . $name;
    if (!imagewebp($source, $target, max(45, min(90, $quality)))) {
        imagedestroy($source);
        throw new RuntimeException('Foto tidak dapat dikonversi ke WebP.');
    }
    imagedestroy($source);

    return [
        'path' => 'uploads/' . $name,
        'size' => filesize($target) ?: null,
        'original_size' => (int)($file['size'] ?? 0),
    ];
}

function upload_photo(array $file, string $ring, ?string $current = null, ?int $currentSize = null, int $maxSize = PHOTO_WEBP_MAX_SIZE, int $quality = PHOTO_WEBP_QUALITY): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => $current, 'size' => $currentSize, 'original_size' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal.');
    }

    $uploaded = save_webp_upload($file, $ring, $maxSize, $quality, 'photo');
    if ($current && $current !== $uploaded['path']) {
        delete_uploaded_photo($current);
    }
    return $uploaded;
}

function upload_club_logo(array $file, string $clubName, ?string $current = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $current;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload logo klub gagal.');
    }

    $uploaded = save_webp_upload($file, $clubName, CLUB_LOGO_WEBP_MAX_SIZE, CLUB_LOGO_WEBP_QUALITY, 'club');
    if ($current && $current !== $uploaded['path']) {
        delete_uploaded_photo($current);
    }
    return $uploaded['path'];
}

try {
    install_database();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>' . APP_NAME . ' belum bisa tersambung ke MySQL</h1>';
    echo '<p>Periksa konfigurasi di <code>config.php</code>: host, database, user, dan password.</p>';
    echo '<pre>' . h($e->getMessage()) . '</pre>';
    exit;
}
