<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('GET');

$pdo = db();
$club = api_string('club');
$location = api_string('location');
$date = api_string('date');
$status = api_string('status');

$where = ["r.status IN ('registration','basketing','released','finished')", "(r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))"];
$params = [];
if ($status !== '' && in_array($status, ['registration','basketing','released','finished'], true)) {
    $where = ['r.status = ?'];
    $params[] = $status;
}
if ($club !== '') {
    $where[] = 'c.name LIKE ?';
    $params[] = '%' . $club . '%';
}
if ($location !== '') {
    $where[] = 'r.release_point LIKE ?';
    $params[] = '%' . $location . '%';
}
if ($date !== '') {
    $where[] = 'DATE(COALESCE(r.release_datetime, r.created_at)) = ?';
    $params[] = $date;
}

$stmt = $pdo->prepare('
    SELECT r.id, r.name, r.type, r.release_point, r.release_datetime, r.actual_release_datetime,
           r.status, r.max_participants, r.entry_fee, r.prize_info, r.sponsor_info,
           c.name AS club_name,
           (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id) AS total_registered,
           (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id AND rr.status = "clocked") AS total_clocked
    FROM races r
    LEFT JOIN clubs c ON c.id = r.club_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY FIELD(r.status, "released", "basketing", "registration", "finished"),
             COALESCE(r.actual_release_datetime, r.release_datetime, r.created_at) DESC
    LIMIT 100
');
$stmt->execute($params);

api_out([
    'ok' => true,
    'data' => $stmt->fetchAll(),
]);
