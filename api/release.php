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
if (!empty($race['actual_release_datetime'])) {
    api_out(['ok' => false, 'message' => 'Jam lepas aktual sudah dikunci.'], 409);
}

$releaseInput = api_string('actual_release_datetime');
$actualRelease = $releaseInput !== '' ? app_datetime($releaseInput) : app_datetime();

$pdo->beginTransaction();
$stmt = $pdo->prepare("UPDATE races SET status = 'released', actual_release_datetime = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$actualRelease->format('Y-m-d H:i:s'), $raceId]);

$stmt = $pdo->prepare("UPDATE race_registrations SET status = 'released' WHERE race_id = ? AND (status IN ('basketing','basketed') OR basketing_verified = 1)");
$stmt->execute([$raceId]);

$memberStmt = $pdo->prepare('SELECT DISTINCT user_id FROM race_registrations WHERE race_id = ?');
$memberStmt->execute([$raceId]);
foreach ($memberStmt->fetchAll() as $member) {
    notify_user($pdo, (int)$member['user_id'], 'race_released', 'Lomba sudah dilepas', $race['name'] . ' dimulai pada ' . $actualRelease->format('H:i:s') . ' WIB.', 'index.php?page=race&id=' . $raceId);
}

log_audit($pdo, $userId, 'race.release.api', 'race', $raceId, ['actual_release_datetime' => $actualRelease->format('Y-m-d H:i:s')]);
$pdo->commit();

api_out([
    'ok' => true,
    'message' => 'Jam lepas aktual dikunci.',
    'race_id' => $raceId,
    'actual_release_datetime' => $actualRelease->format('Y-m-d H:i:s'),
]);
