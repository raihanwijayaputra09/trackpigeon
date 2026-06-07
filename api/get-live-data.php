<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Cek login dari session
$userId = current_user_id();
if (!$userId) {
    echo json_encode(['ok' => false, 'message' => 'Login dulu!']);
    exit;
}

$pdo = db();
$user = current_user($pdo);
if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'Login dulu!']);
    exit;
}

$raceId = (int)($_GET['race_id'] ?? $_GET['id'] ?? 0);
if ($raceId > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*
        FROM races r
        LEFT JOIN clubs c ON c.id = r.club_id
        WHERE r.id = ?
          AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
    ");
    $stmt->execute([$raceId]);
    $race = $stmt->fetch();
    if (!$race) {
        echo json_encode(['ok' => false, 'message' => 'Lomba tidak ditemukan']);
        exit;
    }

    $canView = (int)$race['created_by'] === $userId || is_super_admin();
    if (!$canView && !empty($race['club_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'approved'");
        $stmt->execute([(int)$race['club_id'], $userId]);
        $canView = (int)$stmt->fetchColumn() > 0;
    }
    if (!$canView) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM race_registrations WHERE race_id = ? AND user_id = ?');
        $stmt->execute([$raceId, $userId]);
        $canView = (int)$stmt->fetchColumn() > 0;
    }
    if (!$canView) {
        echo json_encode(['ok' => false, 'message' => 'Tidak punya akses ke live race ini']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT rr.id AS registration_id, rr.status, rr.distance_km,
               b.nomor_ring, b.nama_burung, b.warna,
               u.nama_kandang,
               c.arrival_datetime, c.flight_seconds, c.speed_mpm, c.koefisien, c.method,
               rs.rank_position
        FROM race_registrations rr
        JOIN burung b ON b.id = rr.bird_id
        JOIN users u ON u.id = rr.user_id
        LEFT JOIN clockings c ON c.registration_id = rr.id
        LEFT JOIN race_standings rs ON rs.registration_id = rr.id
        WHERE rr.race_id = ?
        ORDER BY c.speed_mpm IS NULL, COALESCE(rs.rank_position, 999999), c.speed_mpm DESC, rr.created_at ASC
    ');
    $stmt->execute([$raceId]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'race' => $race,
        'burung' => array_map(static function (array $row): array {
            return [
                'registration_id' => (int)$row['registration_id'],
                'rank' => $row['rank_position'] !== null ? (int)$row['rank_position'] : null,
                'nomor_ring' => $row['nomor_ring'],
                'nama_burung' => $row['nama_burung'],
                'warna' => $row['warna'],
                'nama_kandang' => $row['nama_kandang'],
                'status' => $row['status'],
                'arrival_datetime' => $row['arrival_datetime'],
                'flight_seconds' => $row['flight_seconds'] !== null ? (int)$row['flight_seconds'] : null,
                'speed_mpm' => $row['speed_mpm'] !== null ? round((float)$row['speed_mpm'], 2) : null,
                'koefisien' => $row['koefisien'] !== null ? round((float)$row['koefisien'], 2) : null,
                'method' => $row['method'],
            ];
        }, $rows),
    ]);
    exit;
}

// Ambil sesi latihan aktif
$stmt = $pdo->prepare("
    SELECT id FROM latihan 
    WHERE user_id = ? AND status = 'berlangsung' 
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$latihan = $stmt->fetch();

if (!$latihan) {
    echo json_encode(['ok' => false, 'message' => 'Tidak ada latihan aktif']);
    exit;
}

// Ambil data burung
$stmt = $pdo->prepare("
    SELECT 
        b.nomor_ring, 
        dl.status_sampai, 
        dl.kecepatan_mpm, 
        dl.jam_tiba, 
        dl.waktu_tempuh_menit, 
        dl.metode_checkin
    FROM detail_latihan dl
    JOIN burung b ON b.id = dl.burung_id
    WHERE dl.latihan_id = ?
    ORDER BY dl.status_sampai DESC, dl.kecepatan_mpm DESC
");
$stmt->execute([$latihan['id']]);
$burung = $stmt->fetchAll();

// Format ulang data
$result = [];
foreach ($burung as $b) {
    $result[] = [
        'nomor_ring'        => $b['nomor_ring'],
        'status_sampai'     => $b['status_sampai'],
        'kecepatan_mpm'     => $b['kecepatan_mpm'] ? round((float)$b['kecepatan_mpm'], 2) : null,
        'jam_tiba'          => $b['jam_tiba'] ?? null,
        'waktu_tempuh_menit'=> $b['waktu_tempuh_menit'] ? round((float)$b['waktu_tempuh_menit'], 2) : null,
        'metode_checkin'    => $b['metode_checkin'] ?? 'manual',
        'just_arrived'      => false
    ];
}

echo json_encode([
    'ok'     => true,
    'burung' => $result
]);
