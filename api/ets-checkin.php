<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInputBody = file_get_contents('php://input') ?: '';

function input_value(string $key): string
{
    static $json = null;
    global $rawInputBody;
    if ($json === null) {
        $json = json_decode($rawInputBody, true);
        if (!is_array($json)) {
            $json = [];
        }
    }
    return trim((string)($_POST[$key] ?? $_GET[$key] ?? $json[$key] ?? ''));
}

function header_value(string $name): string
{
    $target = strtolower($name);
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === $target) {
                return trim((string)$value);
            }
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverKey] ?? ''));
}

function ets_datetime(string $value): DateTimeImmutable
{
    if ($value !== '' && ctype_digit($value)) {
        $seconds = strlen($value) > 10 ? (int)floor(((int)$value) / 1000) : (int)$value;
        return (new DateTimeImmutable('@' . $seconds))->setTimezone(new DateTimeZone(APP_TIMEZONE));
    }
    return app_datetime($value !== '' ? $value : null);
}

try {
    $apiKey = input_value('api_key');
    $rfidTag = input_value('rfid_tag') ?: input_value('rfid');
    $etsToken = header_value('X-ETS-Token') ?: input_value('device_token');

    if ($etsToken !== '') {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM ets_devices WHERE device_token = ? AND status <> 'revoked' AND COALESCE(is_active, 1) = 1 LIMIT 1");
        $stmt->execute([$etsToken]);
        $device = $stmt->fetch();
        if (!$device) {
            api_out(['ok' => false, 'message' => 'Token ETS tidak valid atau sudah dicabut.'], 401);
        }

        $logEts = function (string $status, string $message, array $payload = []) use ($pdo, $device, $rfidTag, $rawInputBody): void {
            $stmt = $pdo->prepare('
                INSERT INTO ets_logs (device_id, user_id, rfid_tag, event_type, status, message, payload_hash, raw_payload, payload)
                VALUES (?, ?, ?, "clock", ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                (int)$device['id'],
                (int)$device['user_id'],
                $rfidTag ?: null,
                $status,
                $message,
                hash('sha256', $rawInputBody !== '' ? $rawInputBody : json_encode($payload, JSON_UNESCAPED_SLASHES)),
                $rawInputBody !== '' ? $rawInputBody : null,
                $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null,
            ]);
        };

        $stmt = $pdo->prepare("UPDATE ets_devices SET status = 'online', last_sync_at = NOW(), last_ip = ?, owner_id = COALESCE(owner_id, user_id) WHERE id = ?");
        $stmt->execute([client_ip(), (int)$device['id']]);

        if ($rfidTag === '') {
            $logEts('info', 'Ping ETS diterima');
            api_out(['ok' => true, 'message' => 'Ping ETS diterima.', 'server_time' => app_datetime()->format('Y-m-d H:i:s')]);
        }

        $timestampRaw = input_value('ts') ?: input_value('timestamp');
        $arrival = ets_datetime($timestampRaw);
        $serverTime = app_datetime();
        if (abs($serverTime->getTimestamp() - $arrival->getTimestamp()) > 60) {
            $logEts('rejected', 'Timestamp ETS berbeda lebih dari 60 detik.', ['ts' => $timestampRaw]);
            api_out(['ok' => false, 'message' => 'Timestamp ETS berbeda lebih dari 60 detik dari server.'], 422);
        }

        $hmac = input_value('hmac') ?: input_value('signature');
        $nonce = input_value('nonce');
        if (!empty($device['secret_key'])) {
            $signedPayload = implode('|', [
                input_value('serial') ?: $device['serial_number'],
                $rfidTag,
                $timestampRaw,
                $nonce,
            ]);
            $expected = hash_hmac('sha256', $signedPayload, (string)$device['secret_key']);
            if ($hmac === '' || !hash_equals($expected, $hmac)) {
                $logEts('rejected', 'HMAC signature tidak valid.', ['payload' => $signedPayload]);
                api_out(['ok' => false, 'message' => 'HMAC signature tidak valid.'], 401);
            }
        }

        $latRaw = input_value('lat') ?: input_value('latitude');
        $lngRaw = input_value('lng') ?: input_value('lon') ?: input_value('longitude');
        $accuracyRaw = input_value('gps_accuracy') ?: input_value('accuracy');
        $clockLat = nullable_float($latRaw);
        $clockLng = nullable_float($lngRaw);
        $gpsAccuracy = nullable_float($accuracyRaw);

        $stmt = $pdo->prepare('SELECT * FROM burung WHERE user_id = ? AND rfid_tag = ? AND aktif = 1 LIMIT 1');
        $stmt->execute([(int)$device['user_id'], $rfidTag]);
        $bird = $stmt->fetch();
        if (!$bird) {
            $logEts('rejected', 'RFID tag belum terdaftar.');
            api_out(['ok' => false, 'message' => 'RFID tag belum terdaftar pada user perangkat ini.'], 404);
        }

        $stmt = $pdo->prepare("
            SELECT rr.*, r.name, r.actual_release_datetime, r.club_id
            FROM race_registrations rr
            JOIN races r ON r.id = rr.race_id
            WHERE rr.user_id = ? AND rr.bird_id = ? AND r.status = 'released'
              AND rr.status IN ('released','basketing','basketed')
              AND (rr.basketing_verified = 1 OR rr.basketing_datetime IS NOT NULL OR rr.basketed_at IS NOT NULL)
              AND r.actual_release_datetime IS NOT NULL
            ORDER BY r.actual_release_datetime DESC
            LIMIT 1
        ");
        $stmt->execute([(int)$device['user_id'], (int)$bird['id']]);
        $registration = $stmt->fetch();
        if (!$registration) {
            $stmt = $pdo->prepare("
                SELECT dl.*, l.jarak_meter, l.jam_lepas
                FROM detail_latihan dl
                JOIN latihan l ON l.id = dl.latihan_id
                WHERE l.user_id = ? AND dl.burung_id = ? AND l.status = 'berlangsung'
                ORDER BY l.jam_lepas DESC
                LIMIT 1
            ");
            $stmt->execute([(int)$device['user_id'], (int)$bird['id']]);
            $training = $stmt->fetch();
            if (!$training) {
                $logEts('rejected', 'Tidak ada lomba resmi atau latihan aktif untuk RFID ini.');
                api_out(['ok' => false, 'message' => 'Tidak ada lomba resmi atau latihan aktif untuk RFID ini.'], 404);
            }

            if ($training['status_sampai'] === '1') {
                $logEts('info', 'Check-in latihan duplikat diabaikan.', ['latihan_id' => (int)$training['latihan_id']]);
                api_out(['ok' => true, 'message' => 'Merpati sudah tercatat sampai.', 'ring' => $bird['nomor_ring']]);
            }

            $start = app_datetime($training['jam_lepas']);
            $elapsedSeconds = $arrival->getTimestamp() - $start->getTimestamp();
            if ($elapsedSeconds <= 0) {
                $logEts('rejected', 'Timestamp tiba lebih awal dari jam lepas latihan.', ['latihan_id' => (int)$training['latihan_id']]);
                api_out(['ok' => false, 'message' => 'Timestamp tiba lebih awal dari jam lepas latihan.'], 422);
            }

            $minutes = $elapsedSeconds / 60;
            $speed = (float)$training['jarak_meter'] / $minutes;
            $rankStmt = $pdo->prepare("SELECT COUNT(*) FROM detail_latihan WHERE latihan_id = ? AND status_sampai = '1'");
            $rankStmt->execute([(int)$training['latihan_id']]);
            $rank = (int)$rankStmt->fetchColumn() + 1;
            $koefisien = round(max(0, $speed) / 100, 2);
            $poin = max(1, 101 - $rank);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE detail_latihan
                SET jam_tiba = ?, waktu_tempuh_menit = ?, kecepatan_mpm = ?, status_sampai = '1',
                    metode_checkin = 'rfid', koefisien = ?, poin = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $arrival->format('Y-m-d H:i:s'),
                round($minutes, 4),
                round($speed, 2),
                $koefisien,
                $poin,
                (int)$training['id'],
            ]);

            $payload = [
                'speed_mpm' => round($speed, 2),
                'koefisien' => $koefisien,
                'poin' => $poin,
                'device_serial' => $device['serial_number'],
                'lat' => $clockLat,
                'lng' => $clockLng,
            ];
            $stmt = $pdo->prepare('
                INSERT INTO ets_checkins (user_id, bird_id, latihan_id, device_id, rfid_tag, payload, checked_in_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                (int)$device['user_id'],
                (int)$bird['id'],
                (int)$training['latihan_id'],
                (int)$device['id'],
                $rfidTag,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $arrival->format('Y-m-d H:i:s'),
            ]);
            $logEts('accepted', 'Check-in latihan ETS berhasil.', ['latihan_id' => (int)$training['latihan_id'], 'speed_mpm' => round($speed, 2)]);
            $pdo->commit();

            notify_user($pdo, (int)$device['user_id'], 'training_clocked', 'Latihan tercatat ETS', $bird['nomor_ring'] . ' tercatat tiba di sesi latihan.', 'index.php?page=live&id=' . (int)$training['latihan_id']);
            api_out([
                'ok' => true,
                'message' => 'Check-in latihan RFID berhasil.',
                'ring' => $bird['nomor_ring'],
                'arrival' => $arrival->format('Y-m-d H:i:s'),
                'speed_mpm' => round($speed, 2),
                'koefisien' => $koefisien,
                'poin' => $poin,
            ]);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clockings WHERE registration_id = ?');
        $stmt->execute([(int)$registration['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $logEts('info', 'Clocking duplikat diabaikan.', ['registration_id' => (int)$registration['id']]);
            api_out(['ok' => true, 'message' => 'Burung sudah tercatat clocking.', 'ring' => $bird['nomor_ring']]);
        }

        $start = app_datetime($registration['actual_release_datetime']);
        $elapsedSeconds = $arrival->getTimestamp() - $start->getTimestamp();
        if ($elapsedSeconds <= 0) {
            $logEts('rejected', 'Timestamp tiba lebih awal dari jam lepas aktual.');
            api_out(['ok' => false, 'message' => 'Timestamp tiba lebih awal dari jam lepas aktual.'], 422);
        }

        $gpsCheck = validate_clocking_gps(
            $clockLat,
            $clockLng,
            $gpsAccuracy,
            nullable_float($registration['loft_lat'] ?? null),
            nullable_float($registration['loft_lng'] ?? null),
            false
        );
        if (!$gpsCheck['ok']) {
            $logEts('rejected', $gpsCheck['message'], [
                'registration_id' => (int)$registration['id'],
                'clocking_lat' => $clockLat,
                'clocking_lng' => $clockLng,
                'gps_accuracy_meter' => $gpsAccuracy,
                'gps_distance_meter' => $gpsCheck['distance_meter'],
            ]);
            api_out(['ok' => false, 'message' => $gpsCheck['message']], 422);
        }

        $distanceMeter = max(1, (float)$registration['distance_km'] * 1000);
        $minutes = $elapsedSeconds / 60;
        $speed = $distanceMeter / $minutes;
        $koefisien = round(max(0, $speed) / 100, 2);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('
            INSERT INTO clockings
                (race_id, registration_id, user_id, bird_id, arrival_datetime, flight_seconds, distance_meter, speed_mpm, koefisien, method, device_id, ets_device_id, rfid_tag, clocking_lat, clocking_lng, server_time, server_verified_at, is_valid, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "ets", ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ');
        $stmt->execute([
            (int)$registration['race_id'],
            (int)$registration['id'],
            (int)$device['user_id'],
            (int)$bird['id'],
            $arrival->format('Y-m-d H:i:s'),
            $elapsedSeconds,
            round($distanceMeter, 2),
            round($speed, 2),
            $koefisien,
            (int)$device['id'],
            (int)$device['id'],
            $rfidTag,
            $clockLat,
            $clockLng,
            $serverTime->format('Y-m-d H:i:s'),
            $serverTime->format('Y-m-d H:i:s.u'),
            client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'ETS Device'), 0, 255),
        ]);
        $stmt = $pdo->prepare("UPDATE race_registrations SET status = 'clocked' WHERE id = ?");
        $stmt->execute([(int)$registration['id']]);
        $logEts('accepted', 'Clocking ETS berhasil.', [
            'race_id' => (int)$registration['race_id'],
            'speed_mpm' => round($speed, 2),
            'gps_distance_meter' => $gpsCheck['distance_meter'],
            'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
        ]);
        recalculate_race_standings($pdo, (int)$registration['race_id']);
        notify_user($pdo, (int)$device['user_id'], 'ets_clocked', 'ETS mencatat kedatangan', $bird['nomor_ring'] . ' clocked di ' . $registration['name'], 'index.php?page=race&id=' . (int)$registration['race_id']);
        if (!empty($registration['club_id'])) {
            notify_club_admins($pdo, (int)$registration['club_id'], 'ets_clocked_admin', 'Clocking ETS masuk', $bird['nomor_ring'] . ' clocked di ' . $registration['name'], 'index.php?page=race&id=' . (int)$registration['race_id']);
        }
        log_audit($pdo, (int)$device['user_id'], 'race.clock.ets', 'race_registration', (int)$registration['id'], [
            'device_id' => (int)$device['id'],
            'speed_mpm' => round($speed, 2),
            'gps_distance_meter' => $gpsCheck['distance_meter'],
            'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
        ]);
        $pdo->commit();

        api_out([
            'ok' => true,
            'message' => 'Clocking ETS berhasil.',
            'ring' => $bird['nomor_ring'],
            'race_id' => (int)$registration['race_id'],
            'arrival' => $arrival->format('Y-m-d H:i:s'),
            'speed_mpm' => round($speed, 2),
            'koefisien' => $koefisien,
            'gps_distance_meter' => $gpsCheck['distance_meter'],
            'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
        ]);
    }

    if ($apiKey === '') {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $apiKey = str_replace('Bearer ', '', $value);
                    break;
                }
            }
        }
        if ($apiKey === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $apiKey = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }
        if ($apiKey === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $apiKey = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if ($apiKey === '') {
            $apiKey = header_value('X-API-Key');
        }
    }

    $apiKey = trim($apiKey);

    if ($apiKey === '' || $rfidTag === '') {
        api_out(['ok' => false, 'message' => 'api_key dan rfid_tag wajib dikirim.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE api_key = ? LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();
    if (!$user) {
        api_out(['ok' => false, 'message' => 'API key tidak valid.'], 401);
    }

    $stmt = $pdo->prepare('SELECT * FROM burung WHERE user_id = ? AND rfid_tag = ? AND aktif = 1 LIMIT 1');
    $stmt->execute([(int)$user['id'], $rfidTag]);
    $bird = $stmt->fetch();
    if (!$bird) {
        api_out(['ok' => false, 'message' => 'RFID tag belum terdaftar di kandang ini.'], 404);
    }

    $stmt = $pdo->prepare("
        SELECT dl.*, l.jarak_meter, l.jam_lepas
        FROM detail_latihan dl
        JOIN latihan l ON l.id = dl.latihan_id
        WHERE l.user_id = ? AND dl.burung_id = ? AND l.status = 'berlangsung'
        ORDER BY l.jam_lepas DESC
        LIMIT 1
    ");
    $stmt->execute([(int)$user['id'], (int)$bird['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        api_out(['ok' => false, 'message' => 'Tidak ada latihan aktif untuk RFID ini.'], 404);
    }
    if ($row['status_sampai'] === '1') {
        api_out(['ok' => true, 'message' => 'Merpati sudah tercatat sampai.', 'ring' => $bird['nomor_ring']]);
    }

    $arrival = app_datetime(input_value('timestamp') ?: null);
    $start = app_datetime($row['jam_lepas']);
    $elapsedSeconds = $arrival->getTimestamp() - $start->getTimestamp();
    if ($elapsedSeconds <= 0) {
        api_out(['ok' => false, 'message' => 'Timestamp tiba lebih awal dari jam lepas.'], 422);
    }

    $minutes = $elapsedSeconds / 60;
    $speed = (float)$row['jarak_meter'] / $minutes;
    $rankStmt = $pdo->prepare("SELECT COUNT(*) FROM detail_latihan WHERE latihan_id = ? AND status_sampai = '1'");
    $rankStmt->execute([(int)$row['latihan_id']]);
    $rank = (int)$rankStmt->fetchColumn() + 1;
    $koefisien = round(max(0, $speed) / 100, 2);
    $poin = max(1, 101 - $rank);

    $stmt = $pdo->prepare("
        UPDATE detail_latihan
        SET jam_tiba = ?, waktu_tempuh_menit = ?, kecepatan_mpm = ?, status_sampai = '1',
            metode_checkin = 'rfid', koefisien = ?, poin = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $arrival->format('Y-m-d H:i:s'),
        round($minutes, 4),
        round($speed, 2),
        $koefisien,
        $poin,
        (int)$row['id'],
    ]);

    $logStmt = $pdo->prepare('
        INSERT INTO ets_checkins (user_id, bird_id, latihan_id, rfid_tag, payload, checked_in_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $logStmt->execute([
        (int)$user['id'],
        (int)$bird['id'],
        (int)$row['latihan_id'],
        $rfidTag,
        json_encode(['speed_mpm' => round($speed, 2), 'koefisien' => $koefisien, 'poin' => $poin], JSON_THROW_ON_ERROR),
        $arrival->format('Y-m-d H:i:s'),
    ]);
    notify_user($pdo, (int)$user['id'], 'training_clocked', 'Latihan tercatat ETS', $bird['nomor_ring'] . ' tercatat tiba di sesi latihan.', 'index.php?page=live&id=' . (int)$row['latihan_id']);

    api_out([
        'ok' => true,
        'message' => 'Check-in RFID berhasil.',
        'ring' => $bird['nomor_ring'],
        'arrival' => $arrival->format('Y-m-d H:i:s'),
        'speed_mpm' => round($speed, 2),
        'koefisien' => $koefisien,
        'poin' => $poin,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_out(['ok' => false, 'message' => $e->getMessage()], 500);
}
