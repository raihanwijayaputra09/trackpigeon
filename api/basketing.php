<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');

$pdo = db();
$user = api_require_user($pdo);
$userId = (int)$user['id'];
$raceId = api_int('race_id');
$registrationId = api_int('registration_id');
$ring = api_string('ring');

if ($registrationId <= 0 && ($raceId <= 0 || $ring === '')) {
    api_out(['ok' => false, 'message' => 'Kirim registration_id atau race_id + ring.'], 422);
}

if ($registrationId > 0) {
    $stmt = $pdo->prepare('
        SELECT rr.*, b.nomor_ring, b.warna, r.name AS race_name
        FROM race_registrations rr
        JOIN burung b ON b.id = rr.bird_id
        JOIN races r ON r.id = rr.race_id
        WHERE rr.id = ?
    ');
    $stmt->execute([$registrationId]);
} else {
    $stmt = $pdo->prepare('
        SELECT rr.*, b.nomor_ring, b.warna, r.name AS race_name
        FROM race_registrations rr
        JOIN burung b ON b.id = rr.bird_id
        JOIN races r ON r.id = rr.race_id
        WHERE rr.race_id = ? AND b.nomor_ring = ?
    ');
    $stmt->execute([$raceId, $ring]);
}
$registration = $stmt->fetch();
if (!$registration) {
    api_out(['ok' => false, 'message' => 'Registrasi tidak ditemukan.'], 404);
}

$raceId = (int)$registration['race_id'];
$race = api_can_manage_race($pdo, $raceId, $userId);

if (!in_array($registration['status'], ['pending', 'approved', 'registered', 'basketing'], true)) {
    api_out(['ok' => false, 'message' => 'Status burung tidak bisa dibasketing.'], 422);
}

$stmt = $pdo->prepare('
    UPDATE race_registrations
    SET status = "basketing",
        basketed_at = COALESCE(basketed_at, NOW()),
        basketing_datetime = COALESCE(basketing_datetime, NOW()),
        basketing_verified = 1,
        basketing_verified_by = ?
    WHERE id = ?
');
$stmt->execute([$userId, (int)$registration['id']]);

$progress = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'basketing' OR basketing_verified = 1) AS basketed
    FROM race_registrations
    WHERE race_id = ?
");
$progress->execute([$raceId]);
$counts = $progress->fetch() ?: ['total' => 0, 'basketed' => 0];

notify_user($pdo, (int)$registration['user_id'], 'race_basketing', 'Basketing terverifikasi', $registration['nomor_ring'] . ' sudah dibasketing untuk ' . $race['name'], 'index.php?page=race&id=' . $raceId);
log_audit($pdo, $userId, 'race.basketing.api', 'race_registration', (int)$registration['id'], ['race_id' => $raceId]);

api_out([
    'ok' => true,
    'message' => 'Basketing tercatat.',
    'race_id' => $raceId,
    'registration_id' => (int)$registration['id'],
    'ring' => $registration['nomor_ring'],
    'basketed' => (int)($counts['basketed'] ?? 0),
    'total' => (int)($counts['total'] ?? 0),
]);

