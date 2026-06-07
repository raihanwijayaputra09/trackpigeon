<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('GET');

$pdo = db();
$raceId = api_int('race_id');

if ($raceId > 0) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.status, r.release_point, r.actual_release_datetime, c.name AS club_name
        FROM races r
        LEFT JOIN clubs c ON c.id = r.club_id
        WHERE r.id = ?
          AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
        LIMIT 1
    ");
    $stmt->execute([$raceId]);
    $race = $stmt->fetch();
    if (!$race) {
        api_out(['ok' => false, 'message' => 'Lomba tidak ditemukan.'], 404);
    }

    $stmt = $pdo->prepare('
        SELECT COALESCE(rs.rank_position, 0) AS rank_position,
               rr.id AS registration_id, rr.status,
               b.nomor_ring, b.nama_burung, b.warna,
               u.nama_kandang, u.nama_pemilik,
               c.arrival_datetime, c.flight_seconds, c.distance_meter, c.speed_mpm, c.koefisien, c.method
        FROM race_registrations rr
        JOIN burung b ON b.id = rr.bird_id
        JOIN users u ON u.id = rr.user_id
        LEFT JOIN clockings c ON c.registration_id = rr.id AND COALESCE(c.is_valid, 1) = 1
        LEFT JOIN race_standings rs ON rs.registration_id = rr.id
        WHERE rr.race_id = ?
        ORDER BY c.speed_mpm IS NULL, COALESCE(rs.rank_position, 999999), c.speed_mpm DESC, c.arrival_datetime ASC, rr.created_at ASC
    ');
    $stmt->execute([$raceId]);

    api_out([
        'ok' => true,
        'race' => $race,
        'data' => $stmt->fetchAll(),
    ]);
}

$stmt = $pdo->query("
    SELECT r.id AS race_id, r.name AS race_name, c.name AS club_name,
           rs.rank_position, rs.speed_mpm, rs.koef,
           b.nomor_ring, b.nama_burung, b.warna,
           u.nama_kandang, u.nama_pemilik,
           cl.arrival_datetime, cl.method
    FROM race_standings rs
    JOIN race_registrations rr ON rr.id = rs.registration_id
    JOIN races r ON r.id = rr.race_id
    LEFT JOIN clubs c ON c.id = r.club_id
    JOIN burung b ON b.id = rr.bird_id
    JOIN users u ON u.id = rr.user_id
    LEFT JOIN clockings cl ON cl.registration_id = rr.id
    WHERE r.status IN ('released','finished')
      AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
    ORDER BY rs.speed_mpm DESC, rs.koef DESC, cl.arrival_datetime ASC
    LIMIT 100
");

api_out([
    'ok' => true,
    'data' => $stmt->fetchAll(),
]);
