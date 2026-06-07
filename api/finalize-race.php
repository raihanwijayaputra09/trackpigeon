<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');

$pdo = db();
$user = api_require_user($pdo);
$userId = (int)$user['id'];
$raceId = api_int('race_id');
if ($raceId <= 0) {
    api_out(['ok' => false, 'message' => 'race_id wajib dikirim.'], 422);
}

$race = api_can_manage_race($pdo, $raceId, $userId);

$pdo->beginTransaction();
recalculate_race_standings($pdo, $raceId);
$stmt = $pdo->prepare("UPDATE races SET status = 'finished', updated_at = NOW() WHERE id = ?");
$stmt->execute([$raceId]);
log_audit($pdo, $userId, 'race.finalize.api', 'race', $raceId);
$pdo->commit();

$stmt = $pdo->prepare('
    SELECT rs.rank_position, rs.speed_mpm, rs.koef, b.nomor_ring, b.nama_burung, u.nama_kandang
    FROM race_standings rs
    JOIN race_registrations rr ON rr.id = rs.registration_id
    JOIN burung b ON b.id = rr.bird_id
    JOIN users u ON u.id = rr.user_id
    WHERE rs.race_id = ?
    ORDER BY rs.rank_position ASC
');
$stmt->execute([$raceId]);

api_out([
    'ok' => true,
    'message' => 'Hasil lomba dipublikasikan.',
    'race_id' => $raceId,
    'race_name' => $race['name'],
    'standings' => $stmt->fetchAll(),
]);

