<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$pdo = db();
$user = current_user($pdo);
$userId = $user ? (int)$user['id'] : 0;
$raceId = api_int('race_id', api_int('id'));
if ($raceId <= 0) {
    api_out(['ok' => false, 'message' => 'race_id wajib dikirim.'], 422);
}

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS club_name, c.city, c.province, u.nama_kandang AS creator_loft
    FROM races r
    LEFT JOIN clubs c ON c.id = r.club_id
    JOIN users u ON u.id = r.created_by
    WHERE r.id = ?
      AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) {
    api_out(['ok' => false, 'message' => 'Lomba tidak ditemukan.'], 404);
}

$canManage = false;
if ($userId > 0) {
    $canManage = is_super_admin() || (int)$race['created_by'] === $userId;
    if (!$canManage && !empty($race['club_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND user_id = ? AND role = 'admin' AND status = 'approved'");
        $stmt->execute([(int)$race['club_id'], $userId]);
        $canManage = (int)$stmt->fetchColumn() > 0;
    }
}

$stmt = $pdo->prepare('
    SELECT rr.id AS registration_id, rr.status, rr.payment_status, rr.distance_km,
           rr.basketing_datetime, rr.basketing_verified,
           b.id AS bird_id, b.nomor_ring, b.nama_burung, b.warna, b.rfid_tag,
           u.id AS user_id, u.nama_kandang, u.nama_pemilik,
           c.arrival_datetime, c.flight_seconds, c.speed_mpm, c.koefisien, c.method,
           rs.rank_position
    FROM race_registrations rr
    JOIN burung b ON b.id = rr.bird_id
    JOIN users u ON u.id = rr.user_id
    LEFT JOIN clockings c ON c.registration_id = rr.id
    LEFT JOIN race_standings rs ON rs.registration_id = rr.id
    WHERE rr.race_id = ?
    ORDER BY c.speed_mpm IS NULL, COALESCE(rs.rank_position, 999999), c.speed_mpm DESC, rr.registration_datetime ASC, rr.created_at ASC
');
$stmt->execute([$raceId]);
$registrations = $stmt->fetchAll();

api_out([
    'ok' => true,
    'race' => $race,
    'can_manage' => $canManage,
    'registrations' => $registrations,
    'summary' => [
        'total_registered' => count($registrations),
        'total_clocked' => count(array_filter($registrations, fn(array $row): bool => !empty($row['arrival_datetime']))),
        'total_basketed' => count(array_filter($registrations, fn(array $row): bool => (int)($row['basketing_verified'] ?? 0) === 1 || ($row['status'] ?? '') === 'basketing')),
    ],
]);
