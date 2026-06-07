<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');

$pdo = db();
$user = api_require_user($pdo);
$userId = (int)$user['id'];

if (!profile_complete($user)) {
    api_out(['ok' => false, 'message' => 'Lengkapi profil dan koordinat loft sebelum daftar lomba.'], 422);
}

$raceId = api_int('race_id');
$birdIdsRaw = api_value('bird_id', api_value('bird_ids', []));
if (is_string($birdIdsRaw)) {
    $birdIdsRaw = array_filter(array_map('trim', explode(',', $birdIdsRaw)));
} elseif (is_int($birdIdsRaw)) {
    $birdIdsRaw = [$birdIdsRaw];
}
$selectedBirds = array_values(array_unique(array_map('intval', is_array($birdIdsRaw) ? $birdIdsRaw : [])));

if ($raceId <= 0 || !$selectedBirds) {
    api_out(['ok' => false, 'message' => 'race_id dan minimal satu bird_id wajib dikirim.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM races WHERE id = ? AND status IN ('registration','basketing')");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) {
    api_out(['ok' => false, 'message' => 'Pendaftaran lomba sudah ditutup atau lomba tidak ditemukan.'], 404);
}
if (!empty($race['club_id']) && !user_is_approved_club_member($pdo, $userId, (int)$race['club_id'])) {
    api_out(['ok' => false, 'message' => 'Kamu harus menjadi member approved di klub penyelenggara sebelum mendaftarkan burung.'], 403);
}

$placeholders = implode(',', array_fill(0, count($selectedBirds), '?'));
$check = $pdo->prepare("SELECT id FROM burung WHERE user_id = ? AND aktif = 1 AND status = 'aktif' AND id IN ($placeholders)");
$check->execute(array_merge([$userId], $selectedBirds));
$ownedBirds = array_map('intval', array_column($check->fetchAll(), 'id'));
if (count($ownedBirds) !== count($selectedBirds)) {
    api_out(['ok' => false, 'message' => 'Pilihan burung tidak valid.'], 422);
}

$distanceKm = haversine_meter(
    (float)$race['release_lat'],
    (float)$race['release_lng'],
    (float)$user['lat_kandang'],
    (float)$user['lon_kandang']
) / 1000;
$autoApproved = (int)$race['created_by'] === $userId;
$status = $autoApproved ? 'approved' : 'pending';

$stmt = $pdo->prepare('
    INSERT INTO race_registrations
        (race_id, user_id, bird_id, loft_lat, loft_lng, distance_km, registration_datetime, status)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        loft_lat = VALUES(loft_lat),
        loft_lng = VALUES(loft_lng),
        distance_km = VALUES(distance_km)
');
foreach ($ownedBirds as $birdId) {
    $stmt->execute([
        $raceId,
        $userId,
        $birdId,
        (float)$user['lat_kandang'],
        (float)$user['lon_kandang'],
        round($distanceKm, 3),
        $status,
    ]);
}

notify_user($pdo, $userId, 'race_registration_sent', 'Pendaftaran burung dikirim', count($ownedBirds) . ' burung menunggu validasi/basketing admin untuk ' . $race['name'], 'index.php?page=race&id=' . $raceId);
if (!empty($race['club_id'])) {
    notify_club_admins($pdo, (int)$race['club_id'], 'race_registration', 'Pendaftaran lomba masuk', count($ownedBirds) . ' burung mendaftar ke ' . $race['name'], 'index.php?page=race&id=' . $raceId);
} else {
    notify_user($pdo, (int)$race['created_by'], 'race_registration', 'Pendaftaran lomba masuk', count($ownedBirds) . ' burung mendaftar ke ' . $race['name'], 'index.php?page=race&id=' . $raceId);
}
log_audit($pdo, $userId, 'race.register.api', 'race', $raceId, ['bird_count' => count($ownedBirds)]);

api_out([
    'ok' => true,
    'message' => 'Pendaftaran burung dikirim.',
    'race_id' => $raceId,
    'bird_count' => count($ownedBirds),
    'distance_km' => round($distanceKm, 3),
    'status' => $status,
]);
