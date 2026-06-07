<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');

$pdo = db();
$user = api_require_user($pdo);
$userId = (int)$user['id'];
$registrationId = api_int('registration_id');
$clockLat = nullable_float(api_value('clocking_lat', api_value('lat', api_value('latitude'))));
$clockLng = nullable_float(api_value('clocking_lng', api_value('lng', api_value('lon', api_value('longitude')))));
$gpsAccuracy = nullable_float(api_value('gps_accuracy', api_value('accuracy')));
$serverTime = app_datetime();

$reject = function (string $reason, ?array $registration = null) use ($pdo, $userId, $registrationId, $serverTime): never {
    $stmt = $pdo->prepare('
        INSERT INTO manual_clocking_attempts
            (race_id, registration_id, user_id, bird_id, result, reason, reject_reason, server_time, ip_address, user_agent)
        VALUES (?, ?, ?, ?, "rejected", ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        (int)($registration['race_id'] ?? 0),
        $registrationId ?: null,
        $userId,
        isset($registration['bird_id']) ? (int)$registration['bird_id'] : null,
        $reason,
        $reason,
        $serverTime->format('Y-m-d H:i:s'),
        client_ip(),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    api_out(['ok' => false, 'message' => $reason], 422);
};

if ($registrationId <= 0) {
    $reject('registration_id wajib dikirim.');
}

$registration = api_registration_for_clock($pdo, $registrationId, $userId);

if ($registration['race_status'] !== 'released' || empty($registration['actual_release_datetime'])) {
    $reject('Clocking hanya bisa dilakukan setelah lomba dilepas.', $registration);
}
if ((int)($registration['basketing_verified'] ?? 0) !== 1 && empty($registration['basketing_datetime']) && empty($registration['basketed_at'])) {
    $reject('Burung belum di-basketing oleh admin klub.', $registration);
}
if (!in_array($registration['status'], ['released', 'basketing', 'basketed'], true)) {
    $reject('Status burung belum valid untuk clocking.', $registration);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clockings WHERE registration_id = ?');
$stmt->execute([$registrationId]);
if ((int)$stmt->fetchColumn() > 0) {
    $reject('Burung ini sudah tercatat clocking.', $registration);
}

$cooldown = manual_clocking_cooldown_seconds($pdo, $userId, (int)$registration['bird_id'], $serverTime);
if ($cooldown > 0) {
    $reject('Tunggu ' . $cooldown . ' detik sebelum mencoba clocking ring ini lagi.', $registration);
}

$gpsCheck = validate_clocking_gps(
    $clockLat,
    $clockLng,
    $gpsAccuracy,
    nullable_float($registration['loft_lat'] ?? null),
    nullable_float($registration['loft_lng'] ?? null),
    true
);
if (!$gpsCheck['ok']) {
    $reject($gpsCheck['message'], $registration);
}

$start = app_datetime($registration['actual_release_datetime']);
$elapsedSeconds = $serverTime->getTimestamp() - $start->getTimestamp();
if ($elapsedSeconds <= 0) {
    $reject('Waktu server lebih awal dari jam lepas aktual.', $registration);
}

$distanceMeter = max(1, (float)$registration['distance_km'] * 1000);
$minutes = $elapsedSeconds / 60;
$speed = $distanceMeter / $minutes;
$koefisien = calculate_koefisien($speed);

$pdo->beginTransaction();
$stmt = $pdo->prepare('
    INSERT INTO clockings
        (race_id, registration_id, user_id, bird_id, arrival_datetime, flight_seconds, distance_meter, speed_mpm, koefisien, method, clocking_lat, clocking_lng, server_time, server_verified_at, is_valid, ip_address, user_agent)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "manual", ?, ?, ?, ?, 1, ?, ?)
');
$stmt->execute([
    (int)$registration['race_id'],
    $registrationId,
    $userId,
    (int)$registration['bird_id'],
    $serverTime->format('Y-m-d H:i:s'),
    $elapsedSeconds,
    round($distanceMeter, 2),
    round($speed, 2),
    $koefisien,
    $clockLat,
    $clockLng,
    $serverTime->format('Y-m-d H:i:s'),
    $serverTime->format('Y-m-d H:i:s.u'),
    client_ip(),
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
]);

$stmt = $pdo->prepare("UPDATE race_registrations SET status = 'clocked' WHERE id = ?");
$stmt->execute([$registrationId]);

$stmt = $pdo->prepare('
    INSERT INTO manual_clocking_attempts
        (race_id, registration_id, user_id, bird_id, result, server_time, ip_address, user_agent)
    VALUES (?, ?, ?, ?, "accepted", ?, ?, ?)
');
$stmt->execute([
    (int)$registration['race_id'],
    $registrationId,
    $userId,
    (int)$registration['bird_id'],
    $serverTime->format('Y-m-d H:i:s'),
    client_ip(),
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
]);

recalculate_race_standings($pdo, (int)$registration['race_id']);
log_audit($pdo, $userId, 'race.clock.manual.api', 'race_registration', $registrationId, [
    'speed_mpm' => round($speed, 2),
    'gps_distance_meter' => $gpsCheck['distance_meter'],
    'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
]);
$pdo->commit();

notify_user($pdo, $userId, 'race_clocked', 'Clocking tercatat', $registration['nomor_ring'] . ' tercatat di ' . $registration['name'], 'index.php?page=race&id=' . (int)$registration['race_id']);

api_out([
    'ok' => true,
    'message' => 'Clocking manual berhasil.',
    'arrival' => $serverTime->format('H:i:s'),
    'duration' => format_duration($minutes),
    'speed_mpm' => round($speed, 2),
    'koefisien' => $koefisien,
    'gps_distance_meter' => $gpsCheck['distance_meter'],
    'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
]);
