<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT l.id, l.nama_sesi, l.nama_titik_lepas, l.jarak_meter, l.jam_lepas, u.nama_kandang,
           (SELECT COUNT(*) FROM detail_latihan dl WHERE dl.latihan_id = l.id) total_burung,
           (SELECT COUNT(*) FROM detail_latihan dl WHERE dl.latihan_id = l.id AND dl.status_sampai = '1') arrived_burung,
           (SELECT b.nomor_ring
            FROM detail_latihan dl
            JOIN burung b ON b.id = dl.burung_id
            WHERE dl.latihan_id = l.id AND dl.status_sampai = '1'
            ORDER BY dl.kecepatan_mpm DESC
            LIMIT 1) fastest_ring,
           (SELECT MAX(dl.kecepatan_mpm) FROM detail_latihan dl WHERE dl.latihan_id = l.id AND dl.status_sampai = '1') fastest_mpm
    FROM latihan l
    JOIN users u ON u.id = l.user_id
    WHERE l.status = 'berlangsung' AND l.is_public = 1
    ORDER BY l.jam_lepas DESC
    LIMIT 24
");
$stmt->execute();
$sessions = $stmt->fetchAll();

// Fetch arrived birds for each session
foreach ($sessions as &$session) {
    $stmt2 = $pdo->prepare("
        SELECT b.nomor_ring, dl.jam_tiba, dl.kecepatan_mpm, dl.koefisien, dl.metode_checkin
        FROM detail_latihan dl
        JOIN burung b ON b.id = dl.burung_id
        WHERE dl.latihan_id = ? AND dl.status_sampai = '1'
        ORDER BY dl.kecepatan_mpm DESC
        LIMIT 20
    ");
    $stmt2->execute([$session['id']]);
    $session['arrived_birds'] = $stmt2->fetchAll();
}
unset($session);

$raceStmt = $pdo->prepare("
    SELECT r.id, r.name, r.release_point, r.release_datetime, r.actual_release_datetime, r.status,
           c.name AS club_name,
           (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id) total_registered,
           (SELECT COUNT(*) FROM clockings ck WHERE ck.race_id = r.id) total_clocked,
           (SELECT b.nomor_ring
            FROM clockings ck
            JOIN burung b ON b.id = ck.bird_id
            WHERE ck.race_id = r.id
            ORDER BY ck.speed_mpm DESC
            LIMIT 1) fastest_ring,
           (SELECT MAX(ck.speed_mpm) FROM clockings ck WHERE ck.race_id = r.id) fastest_mpm
    FROM races r
    LEFT JOIN clubs c ON c.id = r.club_id
    WHERE r.status = 'released'
      AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
    ORDER BY r.actual_release_datetime DESC
    LIMIT 24
");
$raceStmt->execute();
$officialRaces = $raceStmt->fetchAll();

echo json_encode([
    'ok' => true,
    'data' => $sessions,
    'official_races' => $officialRaces,
]);
