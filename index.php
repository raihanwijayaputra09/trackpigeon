<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$pdo = db();
$page = $_GET['page'] ?? (current_user_id() ? 'dashboard' : 'home');
if ($page === 'public-rankings') {
    $page = 'klasemen';
}
$publicPages = ['home', 'login', 'register', 'klasemen', 'public-bird'];
$error = null;
$success = $_GET['success'] ?? null;

function json_response(array $data): never
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function birds(PDO $pdo, int $userId, ?string $statusFilter = null): array
{
    $where = "b.user_id = ? AND b.aktif = 1";
    $params = [$userId];
    if ($statusFilter && in_array($statusFilter, ['aktif','hilang','pensiun','terjual'], true)) {
        $where .= " AND b.status = ?";
        $params[] = $statusFilter;
    }
    $stmt = $pdo->prepare("
        SELECT ranked.*
        FROM (
            SELECT b.*, COUNT(dl.id) total_finish,
                   SUM(l.jarak_meter) total_meter,
                   SUM(dl.waktu_tempuh_menit) total_menit,
                   SUM(l.jarak_meter) / NULLIF(SUM(dl.waktu_tempuh_menit), 0) avg_mpm,
                   AVG(COALESCE(dl.koefisien, dl.kecepatan_mpm / 100)) avg_koefisien
            FROM burung b
            LEFT JOIN detail_latihan dl ON dl.burung_id = b.id AND dl.status_sampai = '1'
            LEFT JOIN latihan l ON l.id = dl.latihan_id AND l.user_id = b.user_id
            WHERE $where
            GROUP BY b.id
        ) ranked
        ORDER BY ranked.avg_koefisien IS NULL, ranked.avg_koefisien DESC, ranked.avg_mpm DESC, ranked.created_at DESC, ranked.id DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function birds_count_by_status(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT status, COUNT(*) cnt FROM burung WHERE user_id = ? AND aktif = 1 GROUP BY status");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $counts = ['aktif' => 0, 'hilang' => 0, 'pensiun' => 0, 'terjual' => 0];
    foreach ($rows as $row) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $counts['semua'] = array_sum($counts);
    return $counts;
}

function bird_avatar(?string $photo, string $ring): string
{
    if ($photo) {
        return '<img class="avatar" src="' . h($photo) . '" alt="Foto ' . h($ring) . '">';
    }
    return '<div class="avatar avatar-empty">' . h(strtoupper(substr($ring, 0, 2))) . '</div>';
}

function bird_display_name(array $bird): string
{
    $name = trim((string)($bird['nama_burung'] ?? ''));
    return $name !== '' ? $name : (string)$bird['nomor_ring'];
}

function bird_identity_line(array $bird): string
{
    $parts = array_filter([
        (string)($bird['nomor_ring'] ?? ''),
        (string)($bird['warna'] ?? ''),
        (string)($bird['jenis_kelamin'] ?? ''),
        (string)($bird['bloodline'] ?? ''),
    ], fn($value) => trim($value) !== '');
    return implode(' / ', $parts);
}

function profile_photo_button(?string $photo, string $ring): string
{
    if (!$photo) {
        return '<div class="avatar avatar-empty profile-avatar">' . h(strtoupper(substr($ring, 0, 2))) . '</div>';
    }
    return '<button class="photo-zoom-trigger" type="button" data-photo="' . h($photo) . '" data-title="' . h($ring) . '">'
        . '<img class="avatar profile-avatar" src="' . h($photo) . '" alt="Foto ' . h($ring) . '">'
        . '</button>';
}

function club_logo(?string $logo, string $name, string $class = ''): string
{
    $classes = trim('club-logo ' . $class);
    if ($logo) {
        return '<img class="' . h($classes) . '" src="' . h($logo) . '" alt="Logo ' . h($name) . '" loading="lazy">';
    }
    $initials = implode('', array_map(fn($word) => strtoupper(substr($word, 0, 1)), array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2)));
    return '<div class="' . h($classes . ' club-logo-empty') . '">' . h($initials ?: 'TP') . '</div>';
}

function calculate_koefisien(float $speedMpm): float
{
    return round(max(0, $speedMpm) / 100, 2);
}

function koefisien_stars(?float $koefisien): string
{
    $value = (float)$koefisien;
    if ($value >= 10) {
        return '***';
    }
    if ($value >= 7) {
        return '**-';
    }
    return '*--';
}

function format_duration_seconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
}

function estimate_arrival_rows(float $distanceMeter, string $startTime): array
{
    $start = app_datetime($startTime);
    $speeds = [
        ['label' => 'Sangat Cepat', 'speed' => 1200, 'class' => 'speed-1200'],
        ['label' => 'Cepat Sekali', 'speed' => 1100, 'class' => 'speed-1100'],
        ['label' => 'Cepat',        'speed' => 1000, 'class' => 'speed-1000'],
        ['label' => 'Baik',         'speed' =>  900, 'class' => 'speed-900'],
        ['label' => 'Normal Cepat', 'speed' =>  800, 'class' => 'speed-800'],
        ['label' => 'Normal',       'speed' =>  700, 'class' => 'speed-700'],
        ['label' => 'Di Bawah Normal', 'speed' => 600, 'class' => 'speed-600'],
        ['label' => 'Lambat',       'speed' =>  500, 'class' => 'speed-500'],
        ['label' => 'Sangat Lambat','speed' =>  400, 'class' => 'speed-400'],
        ['label' => 'Terlalu Lambat','speed' => 350, 'class' => 'speed-350'],
    ];
    return array_map(function (array $item) use ($distanceMeter, $start): array {
        $minutes = (int)ceil($distanceMeter / $item['speed']);
        $cloned = clone $start;
        return $item + [
            'minutes' => $minutes,
            'time' => $cloned->modify('+' . $minutes . ' minutes')->format('H:i') . ' WIB',
        ];
    }, $speeds);
}

function global_live_sessions(PDO $pdo, ?string $kandang = null): array
{
    $where = ["l.status = 'berlangsung'", "l.is_public = 1"];
    $params = [];
    if ($kandang !== null && trim($kandang) !== '') {
        $where[] = 'u.nama_kandang LIKE ?';
        $params[] = '%' . trim($kandang) . '%';
    }
    $stmt = $pdo->prepare("
        SELECT l.*, u.nama_kandang,
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
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.jam_lepas DESC
        LIMIT 24
    ");
    $stmt->execute($params);
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

    return $sessions;
}

function global_rankings(PDO $pdo, ?string $kandang = null, ?string $warna = null, int $limit = 100): array
{
    $where = ["b.aktif = 1", "dl.status_sampai = '1'", "l.is_public = 1"];
    $params = [];
    if ($kandang !== null && trim($kandang) !== '') {
        $where[] = 'u.nama_kandang LIKE ?';
        $params[] = '%' . trim($kandang) . '%';
    }
    if ($warna !== null && trim($warna) !== '') {
        $where[] = 'b.warna LIKE ?';
        $params[] = '%' . trim($warna) . '%';
    }
    $sql = "
        SELECT b.id, b.nomor_ring, b.nama_burung, b.warna, b.foto, u.nama_kandang,
               COUNT(dl.id) total_finish,
               SUM(l.jarak_meter) total_meter,
               SUM(dl.waktu_tempuh_menit) total_menit,
               SUM(l.jarak_meter) / NULLIF(SUM(dl.waktu_tempuh_menit), 0) avg_mpm,
               AVG(COALESCE(dl.koefisien, dl.kecepatan_mpm / 100)) avg_koefisien
        FROM burung b
        JOIN users u ON u.id = b.user_id
        JOIN detail_latihan dl ON dl.burung_id = b.id
        JOIN latihan l ON l.id = dl.latihan_id AND l.user_id = b.user_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY b.id
        ORDER BY avg_koefisien DESC, avg_mpm DESC, total_meter DESC
        LIMIT " . max(1, min(100, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function training_rows(PDO $pdo, int $latihanId, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT dl.*, b.nomor_ring, b.nama_burung, b.warna, b.foto, b.jenis_kelamin
        FROM detail_latihan dl
        JOIN burung b ON b.id = dl.burung_id
        JOIN latihan l ON l.id = dl.latihan_id
        WHERE dl.latihan_id = ? AND l.user_id = ? AND b.user_id = ?
        ORDER BY dl.status_sampai DESC, dl.kecepatan_mpm DESC, b.nomor_ring ASC
    ");
    $stmt->execute([$latihanId, $userId, $userId]);
    return $stmt->fetchAll();
}

function format_rupiah(float $value): string
{
    return 'Rp ' . number_format($value, 0, ',', '.');
}

function badge_class_for_race(string $status): string
{
    return match ($status) {
        'released' => 'danger',
        'registration', 'basketing' => 'warning',
        'finished' => 'success',
        'cancelled' => 'secondary',
        default => 'secondary',
    };
}

function unique_slug(PDO $pdo, string $name, string $table = 'clubs'): string
{
    $base = slugify_value($name);
    $slug = $base;
    $suffix = 2;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $suffix++;
    }
}

function club_rows(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT c.*,
               cm.role AS member_role,
               cm.status AS member_status,
               (SELECT COUNT(*) FROM club_members x WHERE x.club_id = c.id AND x.status = 'approved') AS approved_members,
               (SELECT COUNT(*) FROM races r WHERE r.club_id = c.id) AS total_races
        FROM clubs c
        LEFT JOIN club_members cm ON cm.club_id = c.id AND cm.user_id = ?
        WHERE c.is_active = 1 AND c.approval_status = 'approved'
        ORDER BY cm.status = 'approved' DESC, c.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function admin_clubs(PDO $pdo, int $userId): array
{
    if (is_super_admin()) {
        $stmt = $pdo->query("SELECT * FROM clubs WHERE is_active = 1 AND approval_status = 'approved' ORDER BY name");
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT c.*
        FROM clubs c
        JOIN club_members cm ON cm.club_id = c.id
        WHERE cm.user_id = ? AND cm.role = 'admin' AND cm.status = 'approved' AND c.is_active = 1 AND c.approval_status = 'approved'
        ORDER BY c.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function ensure_default_club(PDO $pdo, array $user): int
{
    $userId = (int)$user['id'];
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM clubs c
        JOIN club_members cm ON cm.club_id = c.id
        WHERE cm.user_id = ? AND cm.role = 'admin' AND cm.status = 'approved' AND c.is_active = 1 AND c.approval_status = 'approved'
        ORDER BY c.id
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $clubId = (int)($stmt->fetchColumn() ?: 0);
    if ($clubId > 0) {
        return $clubId;
    }

    $name = trim((string)($user['nama_kandang'] ?? 'TrackPigeon Club')) ?: 'TrackPigeon Club';
    $slug = unique_slug($pdo, $name);
    $stmt = $pdo->prepare('INSERT INTO clubs (name, slug, owner_user_id, admin_id, city, description, approval_status, is_active) VALUES (?, ?, ?, ?, ?, ?, "pending", 0)');
    $stmt->execute([$name, $slug, $userId, $userId, null, 'Klub default menunggu approval super admin.']);
    $clubId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'admin', 'pending')");
    $stmt->execute([$clubId, $userId]);
    $stmt = $pdo->prepare("UPDATE users SET admin_approval_status = 'pending', admin_requested_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    notify_super_admins($pdo, 'club_approval', 'Request klub baru', $name . ' menunggu approval super admin.', 'index.php?page=super-admin');
    return $clubId;
}

function race_rows(PDO $pdo, int $userId, int $limit = 80): array
{
    $stmt = $pdo->prepare("
        SELECT r.*, c.name AS club_name, u.nama_kandang AS creator_loft,
               (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id) AS total_registered,
               (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id AND rr.status = 'clocked') AS total_clocked,
               (r.created_by = ? OR EXISTS(SELECT 1 FROM club_members cm WHERE cm.club_id = r.club_id AND cm.user_id = ? AND cm.role = 'admin' AND cm.status = 'approved')) AS can_manage
        FROM races r
        LEFT JOIN clubs c ON c.id = r.club_id
        JOIN users u ON u.id = r.created_by
        WHERE r.status <> 'cancelled'
          AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
        ORDER BY FIELD(r.status, 'released', 'basketing', 'registration', 'draft', 'finished'), COALESCE(r.actual_release_datetime, r.release_datetime, r.created_at) DESC
        LIMIT " . max(1, min(120, $limit)));
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll();
    if (is_super_admin()) {
        foreach ($rows as &$row) {
            $row['can_manage'] = 1;
        }
        unset($row);
    }
    return $rows;
}

function race_standings(PDO $pdo, int $raceId): array
{
    $stmt = $pdo->prepare("
        SELECT rr.*, b.nomor_ring, b.nama_burung, b.warna, b.foto, b.rfid_tag, b.jenis_kelamin,
               u.nama_kandang, u.nama_pemilik,
               c.arrival_datetime, c.flight_seconds, c.distance_meter, c.speed_mpm, c.koefisien, c.method
        FROM race_registrations rr
        JOIN burung b ON b.id = rr.bird_id
        JOIN users u ON u.id = rr.user_id
        LEFT JOIN clockings c ON c.registration_id = rr.id
        WHERE rr.race_id = ?
        ORDER BY c.speed_mpm IS NULL, c.speed_mpm DESC, c.arrival_datetime ASC, rr.created_at ASC
    ");
    $stmt->execute([$raceId]);
    return $stmt->fetchAll();
}

function user_notifications(PDO $pdo, int $userId, int $limit = 12): array
{
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function render_global_leaderboard(array $leaders): string
{
    ob_start();
    foreach ($leaders as $index => $row): ?>
        <a class="leader-row big podium-<?= min($index + 1, 4) ?>" href="index.php?page=public-bird&id=<?= (int)$row['id'] ?>">
            <span class="rank"><?= $index + 1 ?></span>
            <?= bird_avatar($row['foto'], $row['nomor_ring']) ?>
            <div><strong><?= h(bird_display_name($row)) ?></strong><span><?= h($row['nama_kandang']) ?> / <?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?> / <?= number_format((float)$row['total_meter'] / 1000, 2) ?> km</span></div>
            <b><?= number_format((float)$row['avg_mpm'], 2) ?> MPM <small><?= number_format((float)$row['avg_koefisien'], 2) ?> K</small></b>
        </a>
    <?php endforeach;
    if (!$leaders): ?>
        <div class="empty-state">Leaderboard publik akan muncul setelah ada burung yang finish.</div>
    <?php endif;
    return (string)ob_get_clean();
}

function render_global_live_cards(array $sessions): string
{
    ob_start();
    foreach ($sessions as $session):
        $start = app_datetime($session['jam_lepas']);
        $elapsedSeconds = max(0, app_datetime()->getTimestamp() - $start->getTimestamp());
        $slowSeconds = max(1, (int)ceil(((float)$session['jarak_meter'] / 350) * 60));
        $progress = min(100, (int)round(($elapsedSeconds / $slowSeconds) * 100));
        $estimates = estimate_arrival_rows((float)$session['jarak_meter'], $session['jam_lepas']);
        $title = trim((string)($session['nama_sesi'] ?? '')) ?: 'Latihan ' . date('d M H:i', strtotime($session['jam_lepas']));
        $arrivedBirds = $session['arrived_birds'] ?? [];
    ?>
        <article class="global-live-card"
            data-start="<?= h($start->format(DateTimeInterface::ATOM)) ?>"
            data-distance="<?= (float)$session['jarak_meter'] ?>">
            <div class="global-live-top">
                <span class="live-pill"><i data-lucide="radio-tower"></i>LIVE</span>
                <strong><?= h($session['nama_kandang']) ?></strong>
            </div>
            <h3><?= h($title) ?></h3>
            <p><?= h($session['nama_titik_lepas']) ?></p>
            <div class="live-metrics">
                <div><span><i data-lucide="timer"></i>Penerbangan</span><strong data-flight-time><?= format_duration_seconds($elapsedSeconds) ?></strong></div>
                <div><span><i data-lucide="ruler"></i>Jarak</span><strong><?= number_format((float)$session['jarak_meter'] / 1000, 2) ?> km</strong></div>
                <div><span><i data-lucide="bird"></i>Tiba</span><strong><?= (int)$session['arrived_burung'] ?>/<?= (int)$session['total_burung'] ?></strong></div>
            </div>
            <div class="arrival-box">
                <span class="arrival-title"><i data-lucide="activity"></i>Estimasi Kedatangan</span>
                <div class="arrival-grid">
                <?php foreach ($estimates as $estimate): ?>
                    <div class="arrival-row <?= h($estimate['class']) ?>">
                        <span><?= (int)$estimate['speed'] ?> MPM</span>
                        <strong><?= h($estimate['time']) ?></strong>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="progress-line"><span data-flight-progress style="width: <?= $progress ?>%"></span></div>
            <div class="progress-label"><?= $progress ?>% waktu berlalu</div>
            <?php if ($arrivedBirds): ?>
            <div class="arrived-section">
                <span class="arrived-title"><i data-lucide="check-circle-2"></i>Burung Sudah Tiba</span>
                <div class="table-wrap arrived-table-wrap">
                <table class="arrived-table">
                    <thead><tr><th>#</th><th>Ring</th><th>Waktu</th><th>MPM</th><th>Koef</th></tr></thead>
                    <tbody>
                    <?php foreach ($arrivedBirds as $i => $ab): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($ab['nomor_ring']) ?></td>
                            <td><?= $ab['jam_tiba'] ? date('H:i:s', strtotime($ab['jam_tiba'])) : '-' ?></td>
                            <td><?= $ab['kecepatan_mpm'] ? number_format((float)$ab['kecepatan_mpm'], 0) : '-' ?></td>
                            <td><?= $ab['koefisien'] ? number_format((float)$ab['koefisien'], 2) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>
            <div class="global-live-foot">
                <a href="index.php?page=home" class="btn btn-sm btn-outline-secondary">Lihat Detail</a>
                <strong><?= $session['fastest_ring'] ? h($session['fastest_ring']) . ' (' . number_format((float)$session['fastest_mpm'], 0) . ' MPM)' : 'Menunggu finish pertama' ?></strong>
            </div>
        </article>
    <?php endforeach;
    if (!$sessions): ?>
        <div class="empty-state">Belum ada latihan publik yang sedang berlangsung.</div>
    <?php endif;
    return (string)ob_get_clean();
}

function verify_google_token(string $credential): array
{
    if (GOOGLE_CLIENT_ID === '') {
        throw new RuntimeException('Google Client ID belum dikonfigurasi di config.php.');
    }
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $json = @file_get_contents($url);
    if ($json === false) {
        throw new RuntimeException('Token Google tidak dapat diverifikasi.');
    }
    $payload = json_decode($json, true);
    if (!is_array($payload) || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        throw new RuntimeException('Token Google tidak valid untuk aplikasi ini.');
    }
    if (($payload['email_verified'] ?? 'false') !== 'true') {
        throw new RuntimeException('Email Google belum terverifikasi.');
    }
    return $payload;
}

if (($_GET['action'] ?? '') === 'public_leaderboard') {
    json_response([
        'ok' => true,
        'html' => render_global_leaderboard(global_rankings($pdo, $_GET['kandang'] ?? '', $_GET['warna'] ?? '', 100)),
    ]);
}

if (($_GET['action'] ?? '') === 'global_live') {
    json_response([
        'ok' => true,
        'html' => render_global_live_cards(global_live_sessions($pdo, $_GET['kandang'] ?? '')),
    ]);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'register') {
            $username = trim((string)$_POST['username']);
            $email = trim((string)$_POST['email']);
            $password = (string)$_POST['password'];
            $namaKandang = trim((string)$_POST['nama_kandang']);
            if ($username === '' || $email === '' || $password === '' || $namaKandang === '') {
                throw new RuntimeException('Nama kandang, email, username, dan password wajib diisi.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Format email tidak valid.');
            }
            if (strlen($password) < 6) {
                throw new RuntimeException('Password minimal 6 karakter.');
            }
            $requestedAdmin = ($_POST['account_role'] ?? 'member') === 'club_admin';
            $approvalStatus = $requestedAdmin ? 'pending' : 'none';
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, nama_kandang, role, admin_approval_status, admin_requested_at)
                VALUES (?, ?, ?, ?, 'member', ?, IF(? = 'pending', NOW(), NULL))
            ");
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $namaKandang, $approvalStatus, $approvalStatus]);
            $newUserId = (int)$pdo->lastInsertId();
            if ($requestedAdmin) {
                $slug = unique_slug($pdo, $namaKandang);
                $clubLogo = upload_club_logo($_FILES['club_logo'] ?? [], $namaKandang);
                $stmt = $pdo->prepare('
                    INSERT INTO clubs (name, slug, owner_user_id, admin_id, logo, description, approval_status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, "pending", 0)
                ');
                $stmt->execute([$namaKandang, $slug, $newUserId, $newUserId, $clubLogo, 'Klub menunggu approval super admin dari registrasi admin klub.']);
                $clubId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'admin', 'pending')");
                $stmt->execute([$clubId, $newUserId]);
                notify_super_admins($pdo, 'admin_approval', 'Request admin klub baru', $namaKandang . ' meminta persetujuan admin klub.', 'index.php?page=super-admin');
                log_audit($pdo, $newUserId, 'club.create.request.register', 'club', $clubId);
            }
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = 'member';
            $message = $requestedAdmin
                ? 'Registrasi berhasil. Request admin klub dikirim ke super admin untuk approval.'
                : 'Registrasi berhasil. Lengkapi profil kandang.';
            redirect('index.php?page=settings&success=' . urlencode($message));
        }

        if ($action === 'login') {
            $identity = trim((string)$_POST['username']);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();
            if (!$user || !$user['password'] || !password_verify((string)$_POST['password'], $user['password'])) {
                throw new RuntimeException('Username atau password salah.');
            }
            if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                throw new RuntimeException('Akun sedang nonaktif. Hubungi admin.');
            }
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = normalize_role($user['role'] ?? 'member');
            redirect('index.php?page=dashboard');
        }

        if ($action === 'google_login') {
            $payload = verify_google_token((string)($_POST['credential'] ?? ''));
            $googleId = (string)$payload['sub'];
            $email = (string)$payload['email'];
            $name = trim((string)($payload['name'] ?? 'Kandang Google'));
            $avatar = (string)($payload['picture'] ?? '');
            $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1');
            $stmt->execute([$googleId, $email]);
            $user = $stmt->fetch();
            if ($user) {
                if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                    throw new RuntimeException('Akun sedang nonaktif. Hubungi admin.');
                }
                $stmt = $pdo->prepare('UPDATE users SET google_id = ?, avatar = ?, email = COALESCE(email, ?), email_verified = 1, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?');
                $stmt->execute([$googleId, $avatar ?: null, $email, (int)$user['id']]);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_role'] = normalize_role($user['role'] ?? 'member');
            } else {
                $namaKandang = explode('@', $email)[0] ?: $name;
                $stmt = $pdo->prepare('INSERT INTO users (email, google_id, avatar, nama_kandang, nama_pemilik, email_verified, email_verified_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
                $stmt->execute([$email, $googleId, $avatar ?: null, $namaKandang, $name]);
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                $_SESSION['user_role'] = 'member';
            }
            redirect('index.php?page=settings&success=Login Google berhasil. Lengkapi profil kandang.');
        }

        if ($action === 'save_settings') {
            $userId = require_login();
            $latKandang = nullable_float($_POST['lat_kandang'] ?? null);
            $lonKandang = nullable_float($_POST['lon_kandang'] ?? null);
            if (!valid_lat_lng($latKandang, $lonKandang)) {
                throw new RuntimeException('Koordinat kandang tidak valid. Aktifkan GPS akurat atau pilih titik kandang di peta.');
            }
            $stmt = $pdo->prepare('UPDATE users SET nama_kandang = ?, nama_pemilik = ?, lat_kandang = ?, lon_kandang = ? WHERE id = ?');
            $stmt->execute([
                trim((string)$_POST['nama_kandang']),
                trim((string)$_POST['nama_pemilik']),
                $latKandang,
                $lonKandang,
                $userId,
            ]);
            redirect('index.php?page=settings&success=Pengaturan kandang disimpan');
        }

        if ($action === 'reset_api_key') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('API key perangkat hanya bisa dikelola pemilik platform.');
            }
            $apiKey = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('UPDATE users SET api_key = ? WHERE id = ?');
            $stmt->execute([$apiKey, $userId]);
            redirect('index.php?page=settings&success=API Key ETS diperbarui');
        }

        if ($action === 'create_club') {
            $userId = require_login();
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama klub wajib diisi.');
            }
            $slug = unique_slug($pdo, $name);
            $clubLogo = upload_club_logo($_FILES['club_logo'] ?? [], $name);
            $autoApprove = is_super_admin();
            $stmt = $pdo->prepare('
                INSERT INTO clubs
                    (name, slug, owner_user_id, admin_id, city, province, logo, description, approval_status, approved_by, approved_at, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $name,
                $slug,
                $userId,
                $userId,
                trim((string)($_POST['city'] ?? '')) ?: null,
                trim((string)($_POST['province'] ?? '')) ?: null,
                $clubLogo,
                trim((string)($_POST['description'] ?? '')) ?: null,
                $autoApprove ? 'approved' : 'pending',
                $autoApprove ? $userId : null,
                $autoApprove ? app_datetime()->format('Y-m-d H:i:s') : null,
                $autoApprove ? 1 : 0,
            ]);
            $clubId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'admin', ?)");
            $stmt->execute([$clubId, $userId, $autoApprove ? 'approved' : 'pending']);
            if (!$autoApprove) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET admin_approval_status = IF(role IN ('member','community_admin'), 'pending', admin_approval_status),
                        admin_requested_at = COALESCE(admin_requested_at, NOW())
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                notify_super_admins($pdo, 'club_approval', 'Request klub baru', $name . ' menunggu approval super admin.', 'index.php?page=super-admin');
            } elseif (!is_super_admin()) {
                $stmt = $pdo->prepare("UPDATE users SET role = 'club_admin', admin_approval_status = 'approved', admin_approved_at = NOW(), admin_approved_by = ? WHERE id = ? AND role IN ('member','community_admin')");
                $stmt->execute([$userId, $userId]);
                $_SESSION['user_role'] = 'club_admin';
            }
            log_audit($pdo, $userId, $autoApprove ? 'club.create' : 'club.create.request', 'club', $clubId);
            $message = $autoApprove ? 'Klub berhasil dibuat' : 'Request klub dikirim dan menunggu approval super admin';
            redirect('index.php?page=clubs&success=' . urlencode($message));
        }

        if ($action === 'join_club') {
            $userId = require_login();
            $clubId = (int)($_POST['club_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM clubs WHERE id = ? AND is_active = 1');
            $stmt->execute([$clubId]);
            $club = $stmt->fetch();
            if (!$club) {
                throw new RuntimeException('Klub tidak ditemukan.');
            }
            $stmt = $pdo->prepare("
                INSERT INTO club_members (club_id, user_id, role, status)
                VALUES (?, ?, 'member', 'pending')
                ON DUPLICATE KEY UPDATE status = IF(status = 'banned', status, VALUES(status))
            ");
            $stmt->execute([$clubId, $userId]);
            if (!empty($club['owner_user_id'])) {
                notify_user($pdo, (int)$club['owner_user_id'], 'club_join', 'Permintaan anggota baru', 'Ada member yang meminta bergabung ke ' . $club['name'], 'index.php?page=clubs');
            }
            log_audit($pdo, $userId, 'club.join.request', 'club', $clubId);
            redirect('index.php?page=clubs&success=Permintaan bergabung dikirim');
        }

        if ($action === 'update_club_member') {
            $userId = require_login();
            $clubId = (int)($_POST['club_id'] ?? 0);
            $memberUserId = (int)($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? 'approved';
            if (!in_array($status, ['approved', 'rejected', 'banned'], true)) {
                throw new RuntimeException('Status anggota tidak valid.');
            }
            if (!user_is_club_admin($pdo, $userId, $clubId)) {
                throw new RuntimeException('Hanya admin klub yang bisa mengubah anggota.');
            }
            if ($status === 'rejected') {
                $stmt = $pdo->prepare('DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND role <> "admin"');
                $stmt->execute([$clubId, $memberUserId]);
            } else {
                $stmt = $pdo->prepare('UPDATE club_members SET status = ? WHERE club_id = ? AND user_id = ? AND role <> "admin"');
                $stmt->execute([$status, $clubId, $memberUserId]);
            }
            notify_user($pdo, $memberUserId, 'club_membership', 'Status klub diperbarui', 'Status keanggotaan klub kamu: ' . $status, 'index.php?page=clubs');
            log_audit($pdo, $userId, 'club.member.update', 'club', $clubId, ['member_user_id' => $memberUserId, 'status' => $status]);
            redirect('index.php?page=clubs&success=Status anggota diperbarui');
        }

        if ($action === 'create_race') {
            $userId = require_login();
            $user = current_user($pdo);
            if (!profile_complete($user)) {
                throw new RuntimeException('Lengkapi profil dan koordinat kandang sebelum membuat lomba.');
            }
            $name = trim((string)($_POST['name'] ?? ''));
            $releasePoint = trim((string)($_POST['release_point'] ?? ''));
            if ($name === '' || $releasePoint === '') {
                throw new RuntimeException('Nama lomba dan titik lepas wajib diisi.');
            }
            $clubId = (int)($_POST['club_id'] ?? 0);
            if ($clubId <= 0) {
                throw new RuntimeException('Pilih klub penyelenggara. Hanya admin klub atau super admin yang bisa membuat lomba.');
            }
            $stmt = $pdo->prepare('SELECT id FROM clubs WHERE id = ? AND is_active = 1');
            $stmt->execute([$clubId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Klub penyelenggara tidak ditemukan atau nonaktif.');
            }
            if (!user_is_club_admin($pdo, $userId, $clubId)) {
                throw new RuntimeException('Kamu harus menjadi admin klub untuk membuat lomba.');
            }
            $raceType = in_array($_POST['type'] ?? '', ['latihan_bersama','lomba','latihan_mandiri'], true) ? $_POST['type'] : 'lomba';
            $releaseDate = trim((string)($_POST['release_datetime'] ?? ''));
            $releaseDateSql = $releaseDate !== '' ? app_datetime($releaseDate)->format('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare('
                INSERT INTO races
                    (club_id, name, type, release_point, release_lat, release_lng, release_datetime, status, max_participants, entry_fee, prize_info, sponsor_info, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $clubId,
                $name,
                $raceType,
                $releasePoint,
                (float)$_POST['release_lat'],
                (float)$_POST['release_lng'],
                $releaseDateSql,
                ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'registration',
                ($_POST['max_participants'] ?? '') !== '' ? (int)$_POST['max_participants'] : null,
                ($_POST['entry_fee'] ?? '') !== '' ? (float)$_POST['entry_fee'] : 0,
                trim((string)($_POST['prize_info'] ?? '')) ?: null,
                trim((string)($_POST['sponsor_info'] ?? '')) ?: null,
                trim((string)($_POST['notes'] ?? '')) ?: null,
                $userId,
            ]);
            $raceId = (int)$pdo->lastInsertId();
            $members = $pdo->prepare("SELECT user_id FROM club_members WHERE club_id = ? AND status = 'approved'");
            $members->execute([$clubId]);
            foreach ($members->fetchAll() as $member) {
                notify_user($pdo, (int)$member['user_id'], 'race_new', 'Lomba baru dibuka', $name . ' sudah tersedia untuk pendaftaran.', 'index.php?page=race&id=' . $raceId);
            }
            log_audit($pdo, $userId, 'race.create', 'race', $raceId);
            redirect('index.php?page=race&id=' . $raceId . '&success=Lomba berhasil dibuat');
        }

        if ($action === 'register_race_birds') {
            $userId = require_login();
            $user = current_user($pdo);
            if (!profile_complete($user)) {
                throw new RuntimeException('Lengkapi profil kandang sebelum daftar lomba.');
            }
            $raceId = (int)($_POST['race_id'] ?? 0);
            $selectedBirds = array_map('intval', $_POST['bird_id'] ?? []);
            if (!$selectedBirds) {
                throw new RuntimeException('Pilih minimal satu merpati.');
            }
            $stmt = $pdo->prepare("SELECT * FROM races WHERE id = ? AND status IN ('registration','basketing')");
            $stmt->execute([$raceId]);
            $race = $stmt->fetch();
            if (!$race) {
                throw new RuntimeException('Pendaftaran lomba sudah ditutup.');
            }
            if (!empty($race['club_id']) && !user_is_approved_club_member($pdo, $userId, (int)$race['club_id'])) {
                throw new RuntimeException('Kamu harus menjadi member approved di klub penyelenggara sebelum mendaftarkan burung.');
            }
            $placeholders = implode(',', array_fill(0, count($selectedBirds), '?'));
            $check = $pdo->prepare("SELECT id FROM burung WHERE user_id = ? AND aktif = 1 AND status = 'aktif' AND id IN ($placeholders)");
            $check->execute(array_merge([$userId], $selectedBirds));
            $ownedBirds = array_map('intval', array_column($check->fetchAll(), 'id'));
            if (count($ownedBirds) !== count(array_unique($selectedBirds))) {
                throw new RuntimeException('Pilihan merpati tidak valid.');
            }
            $distanceKm = haversine_meter((float)$race['release_lat'], (float)$race['release_lng'], (float)$user['lat_kandang'], (float)$user['lon_kandang']) / 1000;
            $autoApproved = (int)$race['created_by'] === $userId;
            $stmt = $pdo->prepare('
                INSERT INTO race_registrations (race_id, user_id, bird_id, loft_lat, loft_lng, distance_km, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE loft_lat = VALUES(loft_lat), loft_lng = VALUES(loft_lng), distance_km = VALUES(distance_km)
            ');
            foreach ($ownedBirds as $birdId) {
                $stmt->execute([$raceId, $userId, $birdId, (float)$user['lat_kandang'], (float)$user['lon_kandang'], round($distanceKm, 3), $autoApproved ? 'approved' : 'pending']);
            }
            notify_user($pdo, $userId, 'race_registration_sent', 'Pendaftaran burung dikirim', count($ownedBirds) . ' burung menunggu validasi/basketing admin untuk ' . $race['name'], 'index.php?page=race&id=' . $raceId);
            if (!empty($race['club_id'])) {
                notify_club_admins($pdo, (int)$race['club_id'], 'race_registration', 'Pendaftaran lomba masuk', count($ownedBirds) . ' burung mendaftar ke ' . $race['name'], 'index.php?page=race&id=' . $raceId);
            } else {
                notify_user($pdo, (int)$race['created_by'], 'race_registration', 'Pendaftaran lomba masuk', count($ownedBirds) . ' burung mendaftar ke ' . $race['name'], 'index.php?page=race&id=' . $raceId);
            }
            log_audit($pdo, $userId, 'race.register', 'race', $raceId, ['bird_count' => count($ownedBirds)]);
            redirect('index.php?page=race&id=' . $raceId . '&success=Pendaftaran burung dikirim');
        }

        if ($action === 'update_registration_status') {
            $userId = require_login();
            $raceId = (int)($_POST['race_id'] ?? 0);
            $registrationId = (int)($_POST['registration_id'] ?? 0);
            $status = $_POST['status'] ?? 'approved';
            if (!in_array($status, ['approved','rejected','basketing','dnf'], true)) {
                throw new RuntimeException('Status registrasi tidak valid.');
            }
            $race = user_can_manage_race($pdo, $raceId, $userId);
            if (!$race) {
                throw new RuntimeException('Hanya admin klub atau super admin yang bisa mengelola registrasi.');
            }
            $stmt = $pdo->prepare('SELECT * FROM race_registrations WHERE id = ? AND race_id = ?');
            $stmt->execute([$registrationId, $raceId]);
            $registration = $stmt->fetch();
            if (!$registration) {
                throw new RuntimeException('Registrasi tidak ditemukan.');
            }
            $stmt = $pdo->prepare('
                UPDATE race_registrations
                SET status = ?,
                    basketed_at = IF(? = "basketing", NOW(), basketed_at),
                    basketing_datetime = IF(? = "basketing", NOW(), basketing_datetime),
                    basketing_verified = IF(? = "basketing", 1, basketing_verified),
                    basketing_verified_by = IF(? = "basketing", ?, basketing_verified_by)
                WHERE id = ?
            ');
            $stmt->execute([$status, $status, $status, $status, $status, $userId, $registrationId]);
            if ($status === 'basketing' && in_array($race['status'], ['registration','draft'], true)) {
                $stmt = $pdo->prepare("UPDATE races SET status = 'basketing', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$raceId]);
            }
            notify_user($pdo, (int)$registration['user_id'], 'race_registration_status', 'Status pendaftaran lomba', 'Status burung kamu di ' . $race['name'] . ': ' . $status, 'index.php?page=race&id=' . $raceId);
            log_audit($pdo, $userId, 'race.registration.update', 'race_registration', $registrationId, ['status' => $status]);
            redirect('index.php?page=race&id=' . $raceId . '&success=Status registrasi diperbarui');
        }

        if ($action === 'basketing_by_ring') {
            $userId = require_login();
            $raceId = (int)($_POST['race_id'] ?? 0);
            $ring = trim((string)($_POST['ring'] ?? ''));
            $race = user_can_manage_race($pdo, $raceId, $userId);
            if (!$race) {
                throw new RuntimeException('Hanya admin klub atau super admin yang bisa memproses basketing.');
            }
            if ($ring === '') {
                throw new RuntimeException('Nomor ring wajib diisi untuk basketing.');
            }
            if (!in_array($race['status'], ['registration','basketing'], true)) {
                throw new RuntimeException('Basketing hanya bisa dilakukan saat pendaftaran atau status basketing.');
            }
            $stmt = $pdo->prepare('
                SELECT rr.*, b.nomor_ring
                FROM race_registrations rr
                JOIN burung b ON b.id = rr.bird_id
                WHERE rr.race_id = ? AND b.nomor_ring = ?
                LIMIT 1
            ');
            $stmt->execute([$raceId, $ring]);
            $registration = $stmt->fetch();
            if (!$registration) {
                throw new RuntimeException('Ring belum terdaftar pada lomba ini.');
            }
            if (!in_array($registration['status'], ['pending','approved','registered','basketing'], true)) {
                throw new RuntimeException('Status peserta tidak bisa diproses basketing.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('
                UPDATE race_registrations
                SET status = "basketing",
                    basketed_at = NOW(),
                    basketing_datetime = NOW(),
                    basketing_verified = 1,
                    basketing_verified_by = ?
                WHERE id = ?
            ');
            $stmt->execute([$userId, (int)$registration['id']]);
            $stmt = $pdo->prepare("UPDATE races SET status = 'basketing', updated_at = NOW() WHERE id = ? AND status IN ('registration','draft','basketing')");
            $stmt->execute([$raceId]);
            log_audit($pdo, $userId, 'race.basketing.ring', 'race_registration', (int)$registration['id'], ['ring' => $ring]);
            $pdo->commit();
            notify_user($pdo, (int)$registration['user_id'], 'race_basketing', 'Basketing terverifikasi', $ring . ' sudah diverifikasi untuk ' . $race['name'], 'index.php?page=race&id=' . $raceId);
            redirect('index.php?page=race&id=' . $raceId . '&view=basketing&success=Basketing ring ' . urlencode($ring) . ' tercatat');
        }

        if ($action === 'release_race') {
            $userId = require_login();
            $raceId = (int)($_POST['race_id'] ?? 0);
            $race = user_can_manage_race($pdo, $raceId, $userId);
            if (!$race) {
                throw new RuntimeException('Lomba tidak ditemukan atau akses admin tidak tersedia.');
            }
            if (!empty($race['actual_release_datetime'])) {
                throw new RuntimeException('Jam lepas aktual sudah dikunci dan tidak bisa diubah.');
            }
            $releaseAt = trim((string)($_POST['actual_release_datetime'] ?? ''));
            $actualRelease = $releaseAt !== '' ? app_datetime($releaseAt) : app_datetime();
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
            log_audit($pdo, $userId, 'race.release.locked', 'race', $raceId, ['actual_release_datetime' => $actualRelease->format('Y-m-d H:i:s')]);
            $pdo->commit();
            redirect('index.php?page=race&id=' . $raceId . '&success=Jam lepas aktual dikunci');
        }

        if ($action === 'finish_race') {
            $userId = require_login();
            $raceId = (int)($_POST['race_id'] ?? 0);
            $race = user_can_manage_race($pdo, $raceId, $userId);
            if (!$race) {
                throw new RuntimeException('Hanya admin klub atau super admin yang bisa finalisasi lomba.');
            }
            recalculate_race_standings($pdo, $raceId);
            $stmt = $pdo->prepare("UPDATE races SET status = 'finished', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$raceId]);
            log_audit($pdo, $userId, 'race.finish', 'race', $raceId);
            redirect('index.php?page=race&id=' . $raceId . '&success=Hasil lomba dipublikasikan');
        }

        if ($action === 'clock_race_manual') {
            $userId = require_login();
            $registrationId = (int)($_POST['registration_id'] ?? 0);
            $clockLat = nullable_float($_POST['clocking_lat'] ?? $_POST['lat'] ?? $_POST['latitude'] ?? null);
            $clockLng = nullable_float($_POST['clocking_lng'] ?? $_POST['lng'] ?? $_POST['lon'] ?? $_POST['longitude'] ?? null);
            $gpsAccuracy = nullable_float($_POST['gps_accuracy'] ?? $_POST['accuracy'] ?? null);
            $stmt = $pdo->prepare("
                SELECT rr.*, r.name, r.status AS race_status, r.actual_release_datetime, b.nomor_ring
                FROM race_registrations rr
                JOIN races r ON r.id = rr.race_id
                JOIN burung b ON b.id = rr.bird_id
                WHERE rr.id = ? AND rr.user_id = ?
            ");
            $stmt->execute([$registrationId, $userId]);
            $registration = $stmt->fetch();
            $serverTime = app_datetime();
            $reject = function (string $reason) use ($pdo, $userId, $registration, $registrationId, $serverTime): never {
                $stmt = $pdo->prepare('
                    INSERT INTO manual_clocking_attempts (race_id, registration_id, user_id, bird_id, result, reason, reject_reason, server_time, ip_address, user_agent)
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
                json_response(['ok' => false, 'message' => $reason]);
            };
            if (!$registration) {
                $reject('Registrasi lomba tidak ditemukan.');
            }
            if ($registration['race_status'] !== 'released' || empty($registration['actual_release_datetime'])) {
                $reject('Clocking hanya bisa dilakukan setelah lomba dilepas.');
            }
            if ((int)($registration['basketing_verified'] ?? 0) !== 1 && empty($registration['basketing_datetime']) && empty($registration['basketed_at'])) {
                $reject('Burung belum di-basketing oleh admin klub.');
            }
            if (!in_array($registration['status'], ['released','basketing','basketed'], true)) {
                $reject('Status burung belum valid untuk clocking.');
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clockings WHERE registration_id = ?');
            $stmt->execute([$registrationId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $reject('Burung ini sudah tercatat clocking.');
            }
            $cooldown = manual_clocking_cooldown_seconds($pdo, $userId, (int)$registration['bird_id'], $serverTime);
            if ($cooldown > 0) {
                $reject('Tunggu ' . $cooldown . ' detik sebelum mencoba clocking ring ini lagi.');
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
                $reject($gpsCheck['message']);
            }
            $start = app_datetime($registration['actual_release_datetime']);
            $elapsedSeconds = $serverTime->getTimestamp() - $start->getTimestamp();
            if ($elapsedSeconds <= 0) {
                $reject('Waktu server lebih awal dari jam lepas aktual.');
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
                INSERT INTO manual_clocking_attempts (race_id, registration_id, user_id, bird_id, result, server_time, ip_address, user_agent)
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
            log_audit($pdo, $userId, 'race.clock.manual', 'race_registration', $registrationId, [
                'speed_mpm' => round($speed, 2),
                'gps_distance_meter' => $gpsCheck['distance_meter'],
                'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
            ]);
            $pdo->commit();
            notify_user($pdo, $userId, 'race_clocked', 'Clocking tercatat', $registration['nomor_ring'] . ' tercatat di ' . $registration['name'], 'index.php?page=race&id=' . (int)$registration['race_id']);
            json_response([
                'ok' => true,
                'arrival' => $serverTime->format('H:i:s'),
                'duration' => format_duration($minutes),
                'speed' => round($speed, 2),
                'koefisien' => $koefisien,
                'gps_distance_meter' => $gpsCheck['distance_meter'],
                'gps_accuracy_meter' => $gpsCheck['accuracy_meter'],
            ]);
        }

        if ($action === 'register_ets_device') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Pendaftaran perangkat ETS hanya dilakukan oleh pemilik platform.');
            }
            $deviceUserId = (int)($_POST['owner_user_id'] ?? $userId);
            if ($deviceUserId <= 0) {
                throw new RuntimeException('Pilih pemilik perangkat ETS.');
            }
            $ownerCheck = $pdo->prepare('SELECT id FROM users WHERE id = ? AND COALESCE(is_active, 1) = 1');
            $ownerCheck->execute([$deviceUserId]);
            if (!$ownerCheck->fetch()) {
                throw new RuntimeException('Pemilik perangkat tidak ditemukan atau nonaktif.');
            }
            $serial = strtoupper(trim((string)($_POST['serial_number'] ?? '')));
            $name = trim((string)($_POST['device_name'] ?? ''));
            if ($serial === '' || $name === '') {
                throw new RuntimeException('Serial number dan nama perangkat wajib diisi.');
            }
            $token = bin2hex(random_bytes(32));
            $secret = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO ets_devices (user_id, owner_id, serial_number, device_name, device_token, secret_key, firmware_version) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$deviceUserId, $userId, $serial, $name, $token, $secret, trim((string)($_POST['firmware_version'] ?? '')) ?: null]);
            $deviceId = (int)$pdo->lastInsertId();
            $_SESSION['new_ets_token'] = $token;
            $_SESSION['new_ets_secret'] = $secret;
            notify_user($pdo, $deviceUserId, 'ets_registered', 'ETS berhasil didaftarkan', $name . ' sudah terhubung ke akun TrackPigeon kamu.', 'index.php?page=ets');
            log_audit($pdo, $userId, 'ets.register', 'ets_device', $deviceId);
            redirect('index.php?page=super-admin&success=Perangkat ETS berhasil didaftarkan. Simpan token yang muncul di halaman ini.');
        }

        if ($action === 'revoke_ets_device') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Token perangkat ETS hanya bisa dicabut oleh pemilik platform.');
            }
            $stmt = $pdo->prepare("UPDATE ets_devices SET status = 'revoked' WHERE id = ?");
            $stmt->execute([(int)$_POST['device_id']]);
            log_audit($pdo, $userId, 'ets.revoke', 'ets_device', (int)$_POST['device_id']);
            redirect('index.php?page=ets&success=Token ETS dicabut');
        }

        if ($action === 'assign_rfid_tag') {
            $userId = require_login();
            $birdId = (int)($_POST['bird_id'] ?? 0);
            $tag = trim((string)($_POST['rfid_tag'] ?? ''));
            if ($birdId <= 0 || $tag === '') {
                throw new RuntimeException('Pilih burung dan isi RFID tag.');
            }
            $stmt = $pdo->prepare('SELECT id FROM burung WHERE id = ? AND user_id = ? AND aktif = 1');
            $stmt->execute([$birdId, $userId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Burung tidak ditemukan.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE burung SET rfid_tag = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$tag, $birdId, $userId]);
            $stmt = $pdo->prepare('
                INSERT INTO rfid_tags (user_id, bird_id, rfid_tag, status)
                VALUES (?, ?, ?, "active")
                ON DUPLICATE KEY UPDATE bird_id = VALUES(bird_id), status = "active"
            ');
            $stmt->execute([$userId, $birdId, $tag]);
            log_audit($pdo, $userId, 'rfid.assign', 'bird', $birdId, ['rfid_tag' => $tag]);
            $pdo->commit();
            redirect('index.php?page=ets&success=RFID tag berhasil dipasang ke burung');
        }

        if ($action === 'create_sponsor') {
            $userId = require_login();
            if (!user_is_club_admin($pdo, $userId)) {
                throw new RuntimeException('Sponsor hanya bisa dikelola admin klub atau super admin.');
            }
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama sponsor wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO sponsors (name, contact_name, phone, offer_text, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $name,
                trim((string)($_POST['contact_name'] ?? '')) ?: null,
                trim((string)($_POST['phone'] ?? '')) ?: null,
                trim((string)($_POST['offer_text'] ?? '')) ?: null,
                $userId,
            ]);
            log_audit($pdo, $userId, 'sponsor.create', 'sponsor', (int)$pdo->lastInsertId());
            redirect('index.php?page=sponsors&success=Sponsor disimpan');
        }

        if ($action === 'toggle_user_active') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Hanya super admin yang bisa mengubah status user.');
            }
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if ($targetUserId <= 0 || $targetUserId === $userId) {
                throw new RuntimeException('User target tidak valid.');
            }
            $active = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            $stmt->execute([$active, $targetUserId]);
            log_audit($pdo, $userId, 'superadmin.user.active', 'user', $targetUserId, ['is_active' => $active]);
            redirect('index.php?page=super-admin&success=Status user diperbarui');
        }

        if ($action === 'update_user_role') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Hanya super admin yang bisa mengubah role user.');
            }
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $role = normalize_role((string)($_POST['role'] ?? 'member'));
            if ($targetUserId <= 0 || !in_array($role, ['member','club_admin','superadmin'], true)) {
                throw new RuntimeException('Role target tidak valid.');
            }
            if ($targetUserId === $userId && $role !== 'superadmin') {
                throw new RuntimeException('Super admin tidak bisa menurunkan role akunnya sendiri dari panel ini.');
            }
            $stmt = $pdo->prepare("
                UPDATE users
                SET role = ?,
                    admin_approval_status = CASE WHEN ? = 'club_admin' THEN 'approved' WHEN ? = 'member' THEN 'none' ELSE admin_approval_status END,
                    admin_approved_at = CASE WHEN ? = 'club_admin' THEN NOW() ELSE admin_approved_at END,
                    admin_approved_by = CASE WHEN ? = 'club_admin' THEN ? ELSE admin_approved_by END
                WHERE id = ?
            ");
            $stmt->execute([$role, $role, $role, $role, $role, $userId, $targetUserId]);
            log_audit($pdo, $userId, 'superadmin.user.role', 'user', $targetUserId, ['role' => $role]);
            redirect('index.php?page=super-admin&success=Role user diperbarui');
        }

        if ($action === 'approve_club_admin_request') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Hanya super admin yang bisa menyetujui klub/admin.');
            }
            $clubId = (int)($_POST['club_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM clubs WHERE id = ?');
            $stmt->execute([$clubId]);
            $club = $stmt->fetch();
            if (!$club) {
                throw new RuntimeException('Request klub tidak ditemukan.');
            }
            $targetUserId = (int)($club['admin_id'] ?: $club['owner_user_id']);
            if ($targetUserId <= 0) {
                throw new RuntimeException('Admin klub target tidak valid.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE clubs
                SET approval_status = 'approved', is_active = 1, approved_by = ?, approved_at = NOW(), admin_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $targetUserId, $clubId]);
            $stmt = $pdo->prepare("
                INSERT INTO club_members (club_id, user_id, role, status)
                VALUES (?, ?, 'admin', 'approved')
                ON DUPLICATE KEY UPDATE role = 'admin', status = 'approved'
            ");
            $stmt->execute([$clubId, $targetUserId]);
            $stmt = $pdo->prepare("
                UPDATE users
                SET role = 'club_admin',
                    admin_approval_status = 'approved',
                    admin_approved_at = NOW(),
                    admin_approved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $targetUserId]);
            log_audit($pdo, $userId, 'superadmin.club_admin.approve', 'club', $clubId, ['target_user_id' => $targetUserId]);
            $pdo->commit();
            notify_user($pdo, $targetUserId, 'admin_approved', 'Admin klub disetujui', $club['name'] . ' sudah aktif. Menu lomba, klub, dan sponsor kini tersedia.', 'index.php?page=dashboard');
            redirect('index.php?page=super-admin&success=Request admin klub disetujui');
        }

        if ($action === 'reject_club_admin_request') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Hanya super admin yang bisa menolak klub/admin.');
            }
            $clubId = (int)($_POST['club_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM clubs WHERE id = ?');
            $stmt->execute([$clubId]);
            $club = $stmt->fetch();
            if (!$club) {
                throw new RuntimeException('Request klub tidak ditemukan.');
            }
            $targetUserId = (int)($club['admin_id'] ?: $club['owner_user_id']);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE clubs SET approval_status = 'rejected', is_active = 0, approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $clubId]);
            if ($targetUserId > 0) {
                $stmt = $pdo->prepare("UPDATE club_members SET status = 'banned' WHERE club_id = ? AND user_id = ? AND role = 'admin'");
                $stmt->execute([$clubId, $targetUserId]);
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM club_members cm
                    JOIN clubs c ON c.id = cm.club_id
                    WHERE cm.user_id = ?
                      AND cm.role = 'admin'
                      AND cm.status = 'approved'
                      AND cm.club_id <> ?
                      AND c.is_active = 1
                      AND c.approval_status = 'approved'
                ");
                $stmt->execute([$targetUserId, $clubId]);
                $hasOtherApprovedAdminClub = (int)$stmt->fetchColumn() > 0;
                if (!$hasOtherApprovedAdminClub) {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET role = CASE WHEN role = 'club_admin' THEN 'member' ELSE role END,
                            admin_approval_status = 'rejected',
                            admin_approved_at = NOW(),
                            admin_approved_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId, $targetUserId]);
                }
                notify_user($pdo, $targetUserId, 'club_request_rejected', 'Request klub ditolak', $club['name'] . ' belum disetujui. Hubungi super admin untuk revisi data.', 'index.php?page=settings');
            }
            log_audit($pdo, $userId, 'superadmin.club_admin.reject', 'club', $clubId, ['target_user_id' => $targetUserId]);
            $pdo->commit();
            redirect('index.php?page=super-admin&success=Request admin klub ditolak');
        }

        if ($action === 'toggle_club_active') {
            $userId = require_login();
            if (!is_super_admin()) {
                throw new RuntimeException('Hanya super admin yang bisa mengubah status klub.');
            }
            $clubId = (int)($_POST['club_id'] ?? 0);
            $active = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
            if ($clubId <= 0) {
                throw new RuntimeException('Klub target tidak valid.');
            }
            $stmt = $pdo->prepare('SELECT * FROM clubs WHERE id = ?');
            $stmt->execute([$clubId]);
            $club = $stmt->fetch();
            if (!$club) {
                throw new RuntimeException('Klub target tidak ditemukan.');
            }
            $stmt = $pdo->prepare("
                UPDATE clubs
                SET is_active = ?,
                    approval_status = IF(? = 1, 'approved', approval_status),
                    approved_by = IF(? = 1, ?, approved_by),
                    approved_at = IF(? = 1, NOW(), approved_at)
                WHERE id = ?
            ");
            $stmt->execute([$active, $active, $active, $userId, $active, $clubId]);
            if ($active === 1) {
                $targetUserId = (int)($club['admin_id'] ?: $club['owner_user_id']);
                if ($targetUserId > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO club_members (club_id, user_id, role, status)
                        VALUES (?, ?, 'admin', 'approved')
                        ON DUPLICATE KEY UPDATE role = 'admin', status = 'approved'
                    ");
                    $stmt->execute([$clubId, $targetUserId]);
                    $stmt = $pdo->prepare("UPDATE users SET role = 'club_admin', admin_approval_status = 'approved', admin_approved_at = NOW(), admin_approved_by = ? WHERE id = ?");
                    $stmt->execute([$userId, $targetUserId]);
                    notify_user($pdo, $targetUserId, 'club_active', 'Klub diaktifkan', $club['name'] . ' sudah aktif dan bisa mengelola lomba.', 'index.php?page=dashboard');
                }
            }
            log_audit($pdo, $userId, 'superadmin.club.active', 'club', $clubId, ['is_active' => $active]);
            redirect('index.php?page=super-admin&success=Status klub diperbarui');
        }

        if ($action === 'mark_notifications_read') {
            $userId = require_login();
            $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
            $stmt->execute([$userId]);
            redirect('index.php?page=notifications&success=Notifikasi ditandai terbaca');
        }

        if ($action === 'create_bird') {
            $userId = require_login();
            $user = current_user($pdo);
            if (!profile_complete($user)) {
                throw new RuntimeException('Lengkapi pengaturan kandang sebelum menambah merpati.');
            }
            $ring = trim((string)$_POST['nomor_ring']);
            $rfidTag = trim((string)($_POST['rfid_tag'] ?? '')) ?: null;
            $birdName = trim((string)($_POST['nama_burung'] ?? '')) ?: null;
            $birthDate = !empty($_POST['tanggal_lahir']) ? (string)$_POST['tanggal_lahir'] : null;
            $bloodline = trim((string)($_POST['bloodline'] ?? '')) ?: null;
            $sire = trim((string)($_POST['induk_jantan'] ?? '')) ?: null;
            $dam = trim((string)($_POST['induk_betina'] ?? '')) ?: null;
            $weight = ($_POST['berat_gram'] ?? '') !== '' ? (float)$_POST['berat_gram'] : null;
            $photo = upload_photo($_FILES['foto'] ?? [], $ring);
            $status = in_array($_POST['status'] ?? '', ['aktif','hilang','pensiun','terjual']) ? $_POST['status'] : 'aktif';
            $catatan = trim((string)($_POST['catatan'] ?? '')) ?: null;
            $stmt = $pdo->prepare('
                INSERT INTO burung
                    (user_id, nomor_ring, rfid_tag, nama_burung, warna, jenis_kelamin, tanggal_lahir, bloodline, induk_jantan, induk_betina, berat_gram, foto, ukuran_file, status, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $ring, $rfidTag, $birdName, trim((string)$_POST['warna']), $_POST['jenis_kelamin'] ?: null, $birthDate, $bloodline, $sire, $dam, $weight, $photo['path'], $photo['size'], $status, $catatan]);
            redirect('index.php?page=birds&success=Merpati ditambahkan');
        }

        if ($action === 'update_bird') {
            $userId = require_login();
            $stmt = $pdo->prepare('SELECT foto, ukuran_file FROM burung WHERE id = ? AND user_id = ? AND aktif = 1');
            $stmt->execute([(int)$_POST['id'], $userId]);
            $current = $stmt->fetch();
            if (!$current) {
                throw new RuntimeException('Merpati tidak ditemukan.');
            }
            $ring = trim((string)$_POST['nomor_ring']);
            $rfidTag = trim((string)($_POST['rfid_tag'] ?? '')) ?: null;
            $birdName = trim((string)($_POST['nama_burung'] ?? '')) ?: null;
            $birthDate = !empty($_POST['tanggal_lahir']) ? (string)$_POST['tanggal_lahir'] : null;
            $bloodline = trim((string)($_POST['bloodline'] ?? '')) ?: null;
            $sire = trim((string)($_POST['induk_jantan'] ?? '')) ?: null;
            $dam = trim((string)($_POST['induk_betina'] ?? '')) ?: null;
            $weight = ($_POST['berat_gram'] ?? '') !== '' ? (float)$_POST['berat_gram'] : null;
            $photo = upload_photo($_FILES['foto'] ?? [], $ring, $current['foto'] ?? null, isset($current['ukuran_file']) ? (int)$current['ukuran_file'] : null);
            $status = in_array($_POST['status'] ?? '', ['aktif','hilang','pensiun','terjual']) ? $_POST['status'] : 'aktif';
            $tanggalStatus = !empty($_POST['tanggal_status']) ? $_POST['tanggal_status'] : null;
            $catatan = trim((string)($_POST['catatan'] ?? '')) ?: null;
            $stmt = $pdo->prepare('
                UPDATE burung
                SET nomor_ring = ?, rfid_tag = ?, nama_burung = ?, warna = ?, jenis_kelamin = ?, tanggal_lahir = ?,
                    bloodline = ?, induk_jantan = ?, induk_betina = ?, berat_gram = ?, foto = ?, ukuran_file = ?,
                    status = ?, tanggal_status = ?, catatan = ?
                WHERE id = ? AND user_id = ?
            ');
            $stmt->execute([$ring, $rfidTag, $birdName, trim((string)$_POST['warna']), $_POST['jenis_kelamin'] ?: null, $birthDate, $bloodline, $sire, $dam, $weight, $photo['path'], $photo['size'], $status, $tanggalStatus, $catatan, (int)$_POST['id'], $userId]);
            redirect('index.php?page=birds&success=Merpati diperbarui');
        }

        if ($action === 'update_bird_status') {
            $userId = require_login();
            $birdId = (int)($_POST['id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'aktif';
            if (!in_array($newStatus, ['aktif','hilang','pensiun','terjual'], true)) {
                throw new RuntimeException('Status tidak valid.');
            }
            $stmt = $pdo->prepare('UPDATE burung SET status = ?, tanggal_status = CURDATE() WHERE id = ? AND user_id = ?');
            $stmt->execute([$newStatus, $birdId, $userId]);
            redirect('index.php?page=birds&success=Status merpati diperbarui');
        }

        if ($action === 'delete_bird') {
            $userId = require_login();
            $stmt = $pdo->prepare('SELECT foto FROM burung WHERE id = ? AND user_id = ?');
            $stmt->execute([(int)$_POST['id'], $userId]);
            $bird = $stmt->fetch();
            if ($bird) {
                delete_uploaded_photo($bird['foto'] ?? null);
            }
            $stmt = $pdo->prepare('UPDATE burung SET aktif = 0 WHERE id = ? AND user_id = ?');
            $stmt->execute([(int)$_POST['id'], $userId]);
            redirect('index.php?page=birds&success=Merpati dinonaktifkan');
        }

        if ($action === 'create_training') {
            $userId = require_login();
            $user = current_user($pdo);
            if (!profile_complete($user)) {
                throw new RuntimeException('Lengkapi koordinat kandang lebih dulu.');
            }
            $selectedBirds = array_map('intval', $_POST['burung_id'] ?? []);
            if (!$selectedBirds) {
                throw new RuntimeException('Pilih minimal satu merpati.');
            }
            $placeholders = implode(',', array_fill(0, count($selectedBirds), '?'));
            $check = $pdo->prepare("SELECT id FROM burung WHERE user_id = ? AND aktif = 1 AND status = 'aktif' AND id IN ($placeholders)");
            $check->execute(array_merge([$userId], $selectedBirds));
            $ownedBirds = array_map('intval', array_column($check->fetchAll(), 'id'));
            if (count($ownedBirds) !== count(array_unique($selectedBirds))) {
                throw new RuntimeException('Pilihan merpati tidak valid.');
            }
            $lat = (float)$_POST['lat_lepas'];
            $lon = (float)$_POST['lon_lepas'];
            $distance = haversine_meter($lat, $lon, (float)$user['lat_kandang'], (float)$user['lon_kandang']);
            $jamLepas = app_datetime($_POST['jam_lepas'])->format('Y-m-d H:i:s');
            $isPublic = isset($_POST['is_public']) ? 1 : 0;
            $namaSesi = trim((string)($_POST['nama_sesi'] ?? '')) ?: null;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO latihan (user_id, nama_sesi, nama_titik_lepas, lat_lepas, lon_lepas, jarak_meter, jam_lepas, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $namaSesi, trim((string)$_POST['nama_titik_lepas']), $lat, $lon, $distance, $jamLepas, $isPublic]);
            $latihanId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO detail_latihan (latihan_id, burung_id) VALUES (?, ?)');
            foreach ($ownedBirds as $birdId) {
                $stmt->execute([$latihanId, $birdId]);
            }
            $pdo->commit();
            redirect('index.php?page=live&id=' . $latihanId);
        }

        if ($action === 'checkin') {
            $userId = require_login();
            $stmt = $pdo->prepare("
                SELECT dl.*, l.jarak_meter, l.jam_lepas
                FROM detail_latihan dl
                JOIN latihan l ON l.id = dl.latihan_id
                JOIN burung b ON b.id = dl.burung_id
                WHERE dl.id = ? AND l.user_id = ? AND b.user_id = ?
            ");
            $stmt->execute([(int)$_POST['detail_id'], $userId, $userId]);
            $row = $stmt->fetch();
            if (!$row) {
                json_response(['ok' => false, 'message' => 'Data tidak ditemukan atau bukan milik akun ini.']);
            }
            if ($row['status_sampai'] === '1') {
                json_response(['ok' => true, 'message' => 'Sudah sampai.']);
            }
            $arrival = app_datetime();
            $start = app_datetime($row['jam_lepas']);
            $elapsedSeconds = $arrival->getTimestamp() - $start->getTimestamp();
            if ($elapsedSeconds <= 0) {
                json_response(['ok' => false, 'message' => 'Jam tiba tidak boleh lebih awal dari jam lepas.']);
            }
            $minutes = $elapsedSeconds / 60;
            $speed = (float)$row['jarak_meter'] / $minutes;
            $rankStmt = $pdo->prepare("SELECT COUNT(*) FROM detail_latihan WHERE latihan_id = ? AND status_sampai = '1'");
            $rankStmt->execute([$row['latihan_id']]);
            $rank = (int)$rankStmt->fetchColumn() + 1;
            $koefisien = calculate_koefisien($speed);
            $poin = max(1, 101 - $rank);
            $stmt = $pdo->prepare("UPDATE detail_latihan SET jam_tiba = ?, waktu_tempuh_menit = ?, kecepatan_mpm = ?, status_sampai = '1', metode_checkin = 'manual', koefisien = ?, poin = ? WHERE id = ?");
            $stmt->execute([$arrival->format('Y-m-d H:i:s'), round($minutes, 4), round($speed, 2), $koefisien, $poin, (int)$row['id']]);
            json_response(['ok' => true, 'arrival' => $arrival->format('H:i:s'), 'duration' => format_duration($minutes), 'speed' => round($speed, 2), 'koefisien' => $koefisien]);
        }

        if ($action === 'finish_training') {
            $userId = require_login();
            $stmt = $pdo->prepare("UPDATE latihan SET status = 'selesai' WHERE id = ? AND user_id = ?");
            $stmt->execute([(int)$_POST['id'], $userId]);
            redirect('index.php?page=history&success=Latihan selesai');
        }

        if ($action === 'delete_training') {
            $userId = require_login();
            $stmt = $pdo->prepare('DELETE FROM latihan WHERE id = ? AND user_id = ?');
            $stmt->execute([(int)$_POST['id'], $userId]);
            redirect('index.php?page=history&success=Latihan dihapus dan klasemen diperbarui');
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}

if ($page === 'logout') {
    session_destroy();
    redirect('index.php?page=home');
}

if (!in_array($page, $publicPages, true)) {
    require_login();
}

$user = current_user($pdo);
$userId = $user ? (int)$user['id'] : null;
if ($userId && in_array($page, ['races', 'clubs', 'sponsors'], true) && !user_is_club_admin($pdo, $userId)) {
    $fallbackPage = match ($page) {
        'races' => 'available-races',
        'clubs' => 'join-club',
        default => 'dashboard',
    };
    redirect('index.php?page=' . $fallbackPage . '&success=' . urlencode('Section ini hanya untuk admin klub. Gunakan halaman member yang tersedia.'));
}
$birdStatusFilter = in_array($_GET['status'] ?? '', ['aktif','hilang','pensiun','terjual']) ? ($_GET['status']) : null;
$allBirds = $userId ? birds($pdo, $userId) : [];
$birdStatusCounts = $userId ? birds_count_by_status($pdo, $userId) : ['semua'=>0,'aktif'=>0,'hilang'=>0,'pensiun'=>0,'terjual'=>0];
$filteredBirds = ($userId && $birdStatusFilter) ? birds($pdo, $userId, $birdStatusFilter) : $allBirds;
$globalFilterKandang = $_GET['kandang'] ?? '';
$globalFilterWarna = $_GET['warna'] ?? '';
$globalLeaders = global_rankings($pdo, $globalFilterKandang, $globalFilterWarna, 100);
$globalLiveSessions = global_live_sessions($pdo, $globalFilterKandang);
$totalGlobalBirds = (int)$pdo->query('SELECT COUNT(*) FROM burung WHERE aktif = 1')->fetchColumn();
$totalGlobalSessions = (int)$pdo->query('SELECT COUNT(*) FROM latihan')->fetchColumn();
$totalOfficialRaces = (int)$pdo->query('SELECT COUNT(*) FROM races')->fetchColumn();
$totalActivePublicSessions = (int)$pdo->query("SELECT COUNT(*) FROM latihan WHERE status = 'berlangsung' AND is_public = 1")->fetchColumn();
$totalLofts = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$defaultMapLat = $user && $user['lat_kandang'] ? (string)$user['lat_kandang'] : '-7.797068';
$defaultMapLon = $user && $user['lon_kandang'] ? (string)$user['lon_kandang'] : '110.370529';
$unreadNotificationCount = 0;
if ($userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    $unreadNotificationCount = (int)$stmt->fetchColumn();
}
$appNavItems = [];
if ($user) {
    $appNavItems['dashboard'] = ['Dashboard', 'layout-dashboard'];
    if (is_member()) {
        $appNavItems += [
            'birds' => ['Merpati', 'bird'],
            'join-club' => ['Gabung Klub', 'users-round'],
            'available-races' => ['Ikut Lomba', 'flag'],
            'clocking' => ['Clocking', 'crosshair'],
            'new-training' => ['Latihan', 'timer'],
            'ets' => ['ETS', 'scan-line'],
            'rankings' => ['Klasemen', 'medal'],
        ];
    }
    if (is_club_admin() || is_super_admin()) {
        $appNavItems += [
            'races' => ['Lomba', 'flag'],
            'clubs' => ['Klub', 'users-round'],
            'sponsors' => ['Sponsor', 'badge-dollar-sign'],
        ];
    }
    $appNavItems['notifications'] = ['Notifikasi', 'bell'];
    if (is_super_admin()) {
        $appNavItems['super-admin'] = ['Super Admin', 'shield-check'];
    }
    $appNavItems['settings'] = ['Kandang', 'settings'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WEB <?= APP_NAME ?></title>
    <link rel="icon" href="assets/trackpigeon-mark.svg?v=<?= filemtime(__DIR__ . '/assets/trackpigeon-mark.svg') ?>" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Nunito:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="assets/styles.css?v=<?= filemtime(__DIR__ . '/assets/styles.css') ?>" rel="stylesheet">
</head>
<body class="<?= $user ? 'has-app-shell' : 'public-shell' ?>"
      data-gps-required="<?= $user ? '1' : '0' ?>"
      data-gps-max-accuracy="<?= (int)GPS_CLOCKING_MAX_ACCURACY_METER ?>"
      data-gps-clock-distance="<?= (int)GPS_CLOCKING_MAX_DISTANCE_METER ?>"
      data-loft-lat="<?= $user && $user['lat_kandang'] !== null ? h((string)$user['lat_kandang']) : '' ?>"
      data-loft-lng="<?= $user && $user['lon_kandang'] !== null ? h((string)$user['lon_kandang']) : '' ?>">
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<nav class="navbar navbar-expand-md sticky-top top-nav">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php" aria-label="WEB <?= APP_NAME ?> beranda">
            <span class="brand-mark"><img class="brand-mark-img" src="assets/trackpigeon-mark.svg?v=<?= filemtime(__DIR__ . '/assets/trackpigeon-mark.svg') ?>" alt="" aria-hidden="true"></span>
            <span class="brand-name"><span class="brand-prefix">WEB</span><span><?= APP_NAME ?></span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link <?= $page === 'home' ? 'active' : '' ?>" href="index.php?page=home"><i data-lucide="trophy"></i>Publik</a>
                <a class="nav-link <?= $page === 'klasemen' ? 'active' : '' ?>" href="index.php?page=klasemen"><i data-lucide="medal"></i>Klasemen</a>
                <?php if ($user): ?>
                    <?php foreach ($appNavItems as $key => [$label, $icon]): ?>
                        <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>"><i data-lucide="<?= $icon ?>"></i><?= $label ?><?php if ($key === 'notifications' && $unreadNotificationCount > 0): ?><span class="nav-badge"><?= $unreadNotificationCount ?></span><?php endif; ?></a>
                    <?php endforeach; ?>
                    <a class="nav-link" href="index.php?page=logout"><i data-lucide="log-out"></i>Logout</a>
                <?php else: ?>
                    <a class="nav-link <?= $page === 'login' ? 'active' : '' ?>" href="index.php?page=login"><i data-lucide="log-in"></i>Login</a>
                    <a class="nav-link <?= $page === 'register' ? 'active' : '' ?>" href="index.php?page=register"><i data-lucide="user-plus"></i>Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if ($user): ?>
<aside class="sidebar" aria-label="Navigasi aplikasi">
    <a class="sidebar-brand" href="index.php?page=dashboard">
        <span class="brand-mark"><img class="brand-mark-img" src="assets/trackpigeon-mark.svg?v=<?= filemtime(__DIR__ . '/assets/trackpigeon-mark.svg') ?>" alt="" aria-hidden="true"></span>
        <span class="sidebar-brand-copy">
            <strong><span class="brand-prefix">WEB</span><span><?= APP_NAME ?></span></strong>
            <small><?= h($user['nama_kandang'] ?: 'Kandang') ?></small>
        </span>
    </a>
    <nav class="sidebar-nav">
        <?php foreach ($appNavItems as $key => [$label, $icon]): ?>
            <a class="sidebar-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
                <i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span><?php if ($key === 'notifications' && $unreadNotificationCount > 0): ?><span class="nav-badge"><?= $unreadNotificationCount ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a class="sidebar-link" href="index.php?page=home"><i data-lucide="globe-2"></i><span>Publik</span></a>
        <a class="sidebar-link" href="index.php?page=logout"><i data-lucide="log-out"></i><span>Logout</span></a>
    </div>
</aside>
<?php endif; ?>

<main class="container-fluid px-3 px-lg-4 py-4 main-content" id="main-content">
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <?php if ($user && !profile_complete($user) && !in_array($page, ['settings', 'logout'], true)): ?>
        <section class="setup-banner mb-4">
            <div><strong>Profil kandang belum lengkap.</strong><span>Isi nama pemilik dan koordinat kandang sebelum menambah merpati atau latihan.</span></div>
            <a class="btn btn-light" href="index.php?page=settings"><i data-lucide="map-pin"></i>Lengkapi</a>
        </section>
    <?php endif; ?>

    <?php if ($page === 'home'): ?>
        <!-- HERO CTA SECTION -->
<section class="public-hero">
    <div class="public-hero-bg"></div>
    <div class="public-hero-content">
        <div class="hero-text-area">
            <p class="eyebrow">
                <span class="live-dot"></span>
                Community Racing Platform
            </p>
            <h1 class="glitch" data-text="<?= APP_NAME ?>"><?= APP_NAME ?></h1>
            <p class="hero-description">Platform manajemen komunitas merpati pos racing untuk manajemen burung, lomba resmi, ETS/RFID, live clocking, klasemen transparan, dan analitik performa.</p>
            
            <div class="hero-actions">
                <a href="#globalLiveRace" class="btn btn-primary btn-lg">
                    <i data-lucide="radio"></i>
                    Lihat Live Race
                    <span class="pulse-badge">LIVE</span>
                </a>
                <a href="index.php?page=klasemen" class="btn btn-outline-primary btn-lg">
                    <i data-lucide="trophy"></i>
                    Klasemen
                </a>
                <a href="<?= $user ? 'index.php?page=dashboard' : 'index.php?page=register' ?>" class="btn btn-outline-primary btn-lg">
                    <i data-lucide="<?= $user ? 'layout-dashboard' : 'user-plus' ?>"></i>
                    <?= $user ? 'Buka Dashboard' : 'Mulai Sekarang' ?>
                </a>
            </div>

            <div class="hero-trust">
                <div class="trust-item">
                    <i data-lucide="shield-check"></i>
                    <span>Data Real-time</span>
                </div>
                <div class="trust-item">
                    <i data-lucide="users"></i>
                    <span>Komunitas Publik</span>
                </div>
                <div class="trust-item">
                    <i data-lucide="zap"></i>
                    <span>ETS Ready</span>
                </div>
            </div>
        </div>

        <div class="hero-stats-area">
            <div class="public-stats">
                <div class="stat-card stat-birds">
                    <div class="stat-icon">
                        <i data-lucide="bird"></i>
                    </div>
                    <div class="stat-info">
                        <span>Burung Global</span>
                        <strong><?= number_format($totalGlobalBirds) ?></strong>
                        <small>Total terdaftar</small>
                    </div>
                </div>
                <div class="stat-card stat-live">
                    <div class="stat-icon">
                        <i data-lucide="radio"></i>
                    </div>
                    <div class="stat-info">
                        <span>Live Publik</span>
                        <strong><?= number_format($totalActivePublicSessions) ?></strong>
                        <small>Sesi aktif</small>
                    </div>
                </div>
                <div class="stat-card stat-lofts">
                    <div class="stat-icon">
                        <i data-lucide="warehouse"></i>
                    </div>
                    <div class="stat-info">
                        <span>Total Kandang</span>
                        <strong><?= number_format($totalLofts) ?></strong>
                        <small>Terverifikasi</small>
                    </div>
                </div>
                <div class="stat-card stat-sessions">
                    <div class="stat-icon">
                        <i data-lucide="play-circle"></i>
                    </div>
                    <div class="stat-info">
                        <span>Lomba Resmi</span>
                        <strong><?= number_format($totalOfficialRaces) ?></strong>
                        <small>Total event</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION TITLES - ENHANCED -->
<div class="section-header">
    <div class="section-title">
        <div class="section-title-left">
            <div class="section-icon">
                <i data-lucide="radio"></i>
            </div>
            <div>
                <h2>Global Live Race</h2>
                <span class="section-note">
                    <span class="live-indicator"></span>
                    Update otomatis setiap 5 detik
                </span>
            </div>
        </div>
        <span class="section-badge"><?= count($globalLiveSessions) ?> Aktif</span>
    </div>
</div>

<section class="global-live-grid" id="globalLiveRace">
    <?= render_global_live_cards($globalLiveSessions) ?>
</section>

<section class="platform-section">
    <div class="section-header flush">
        <div class="section-title">
            <div class="section-title-left">
                <div class="section-icon"><i data-lucide="rocket"></i></div>
                <div>
                    <h2><?= APP_NAME ?> Startup Platform</h2>
                    <span class="section-note">Dari latihan live menjadi ekosistem lomba merpati pos yang transparan, terukur, dan siap ETS.</span>
                </div>
            </div>
        </div>
    </div>
    <div class="platform-grid">
        <article class="platform-card">
            <i data-lucide="timer"></i>
            <h3>Race Management</h3>
            <p>Buat lomba, buka pendaftaran, kelola basketing, kunci jam lepas, dan publikasikan hasil otomatis.</p>
        </article>
        <article class="platform-card">
            <i data-lucide="scan-line"></i>
            <h3>ETS / RFID</h3>
            <p>Check-in RFID dari perangkat resmi, sinkronisasi hasil, dan log operasional untuk lomba maupun latihan.</p>
        </article>
        <article class="platform-card">
            <i data-lucide="bar-chart-3"></i>
            <h3>Performance Analytics</h3>
            <p>Koefisien, speed trend, konsistensi kedatangan, dan bahan awal rekomendasi latihan berbasis data.</p>
        </article>
        <article class="platform-card">
            <i data-lucide="users-round"></i>
            <h3>Community Layer</h3>
            <p>Klub, anggota pending approval, admin klub, event lomba, dan ranking antar kandang.</p>
        </article>
        <article class="platform-card">
            <i data-lucide="shopping-bag"></i>
            <h3>Monetisasi Legal</h3>
            <p>Sponsor natura/voucher, paket premium komunitas, dan exposure event tanpa mengelola uang hadiah.</p>
        </article>
        <article class="platform-card">
            <i data-lucide="shield-check"></i>
            <h3>Secure Scale</h3>
            <p>Role user, audit log, validasi perangkat resmi, indeks performa, dan kontrol akses untuk operasional klub.</p>
        </article>
    </div>
</section>
        <footer class="landing-footer">
            <div>
                <span><?= APP_NAME ?></span>
                <strong>Dibuat oleh Ehandev 2026</strong>
                <span>Assisted by</span>
                <strong>AI Codex & AI DeepSeek</strong>
                <span>RHN LOFT TECH</span>
            </div>
        </footer>
    <?php endif; ?>

    <?php if ($page === 'klasemen'):
        $officialRows = $pdo->query("
            SELECT rs.rank_position, rs.speed_mpm, rs.koef,
                   r.id AS race_id, r.name AS race_name, r.status AS race_status,
                   COALESCE(r.actual_release_datetime, r.release_datetime, r.created_at) AS race_time,
                   c.name AS club_name,
                   b.nomor_ring, b.nama_burung, b.warna,
                   u.nama_kandang, u.nama_pemilik,
                   cl.arrival_datetime
            FROM race_standings rs
            JOIN race_registrations rr ON rr.id = rs.registration_id
            JOIN races r ON r.id = rr.race_id
            LEFT JOIN clubs c ON c.id = r.club_id
            JOIN burung b ON b.id = rr.bird_id
            JOIN users u ON u.id = rr.user_id
            LEFT JOIN clockings cl ON cl.registration_id = rr.id
            WHERE r.status IN ('released','finished')
              AND (r.club_id IS NULL OR (c.is_active = 1 AND c.approval_status = 'approved'))
            ORDER BY race_time DESC, rs.rank_position ASC
            LIMIT 80
        ")->fetchAll();
    ?>
        <section class="page-head">
            <div>
                <p class="eyebrow">Klasemen publik</p>
                <h1>Klasemen TrackPigeon</h1>
                <span>Leaderboard global, performa latihan publik, dan hasil lomba resmi lintas klub.</span>
            </div>
            <a class="btn btn-outline-primary" href="index.php?page=home"><i data-lucide="radio"></i>Live Race</a>
        </section>

        <div class="filter-wrapper">
            <form class="filter-bar" method="get">
                <input type="hidden" name="page" value="klasemen">
                <div class="filter-group">
                    <label class="filter-label">Kandang</label>
                    <div class="search-box"><i data-lucide="warehouse"></i><input name="kandang" value="<?= h($globalFilterKandang) ?>" placeholder="Cari nama kandang..."></div>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Warna</label>
                    <div class="search-box"><i data-lucide="palette"></i><input name="warna" value="<?= h($globalFilterWarna) ?>" placeholder="Filter warna burung..."></div>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-primary"><i data-lucide="search"></i>Cari</button>
                    <a href="index.php?page=klasemen" class="btn btn-light"><i data-lucide="x"></i>Reset</a>
                </div>
            </form>
        </div>

        <section class="stats-grid">
            <div class="stat"><span>Burung Global</span><strong><?= number_format($totalGlobalBirds) ?></strong></div>
            <div class="stat"><span>Total Kandang</span><strong><?= number_format($totalLofts) ?></strong></div>
            <div class="stat"><span>Lomba Resmi</span><strong><?= number_format($totalOfficialRaces) ?></strong></div>
            <div class="stat"><span>Hasil Resmi</span><strong><?= number_format(count($officialRows)) ?></strong></div>
        </section>

        <section class="section-header">
            <div class="section-title">
                <div class="section-title-left"><div class="section-icon"><i data-lucide="trophy"></i></div><div><h2>Global Leaderboard Koefisien</h2><span class="section-note">Top 100 performa terbaik dari sesi publik.</span></div></div>
                <span class="section-badge">Top 100</span>
            </div>
        </section>
        <section class="leaderboard radar-card" id="publicLeaderboard">
            <?= render_global_leaderboard($globalLeaders) ?>
        </section>

        <section class="section-header">
            <div class="section-title">
                <div class="section-title-left"><div class="section-icon"><i data-lucide="flag"></i></div><div><h2>Klasemen Lomba Resmi</h2><span class="section-note">Hasil dari ETS dan race_standings lintas klub.</span></div></div>
                <span class="section-badge"><?= count($officialRows) ?> hasil</span>
            </div>
        </section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Rank</th><th>Lomba</th><th>Klub</th><th>Peserta</th><th>Ring</th><th>MPM</th><th>Koef</th><th>Clocking</th></tr></thead>
                <tbody>
                <?php foreach ($officialRows as $row): ?>
                    <tr>
                        <td data-label="Rank"><?= (int)$row['rank_position'] ?></td>
                        <td data-label="Lomba"><strong><?= h($row['race_name']) ?></strong><br><small><?= $row['race_time'] ? date('d M Y H:i', strtotime($row['race_time'])) : '-' ?> / <?= h($row['race_status']) ?></small></td>
                        <td data-label="Klub"><?= h($row['club_name'] ?: 'Mandiri') ?></td>
                        <td data-label="Peserta"><strong><?= h($row['nama_pemilik'] ?: $row['nama_kandang']) ?></strong><br><small><?= h($row['nama_kandang']) ?></small></td>
                        <td data-label="Ring"><?= h($row['nama_burung'] ?: $row['nomor_ring']) ?><br><small><?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?></small></td>
                        <td data-label="MPM"><?= $row['speed_mpm'] !== null ? number_format((float)$row['speed_mpm'], 2) : '-' ?></td>
                        <td data-label="Koef"><?= $row['koef'] !== null ? number_format((float)$row['koef'], 2) : '-' ?></td>
                        <td data-label="Clocking"><?= $row['arrival_datetime'] ? date('H:i:s', strtotime($row['arrival_datetime'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$officialRows): ?><div class="empty-state">Klasemen lomba resmi akan muncul setelah lomba dipublish dan standings dihitung.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'login' || $page === 'register'): ?>
        <section class="auth-shell">
            <form class="form-panel auth-card" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $page ?>">
                <p class="eyebrow"><?= $page === 'login' ? 'Masuk kandang' : 'Buat akun kandang' ?></p>
                <h1><?= $page === 'login' ? 'Login ' . APP_NAME : 'Registrasi Kandang' ?></h1>
                <?php if ($page === 'register'): ?>
                    <div class="role-choice-grid mb-3">
                        <label class="role-choice">
                            <input type="radio" name="account_role" value="member" checked>
                            <span><i data-lucide="bird"></i><strong>Member</strong><small>Kelola burung, latihan mandiri, gabung klub, ikut lomba berbasis ETS, dan lihat klasemen.</small></span>
                        </label>
                        <label class="role-choice">
                            <input type="radio" name="account_role" value="club_admin">
                            <span><i data-lucide="trophy"></i><strong>Request Admin Klub</strong><small>Buat klub dan kelola lomba setelah disetujui super admin.</small></span>
                        </label>
                    </div>
                    <label class="form-label">Nama Kandang/Club</label><input required class="form-control" name="nama_kandang">
                    <label class="form-label mt-3">Email</label><input required class="form-control" type="email" name="email" autocomplete="email">
                    <label class="form-label mt-3">Logo Klub <small class="text-secondary">(opsional untuk Request Admin Klub)</small></label><input class="form-control optimized-image-input" type="file" accept="image/jpeg,image/png,image/webp" name="club_logo">
                <?php endif; ?>
                <label class="form-label mt-3"><?= $page === 'login' ? 'Username atau Email' : 'Username' ?></label><input required class="form-control" name="username" autocomplete="username">
                <label class="form-label mt-3">Password</label><input required class="form-control" type="password" name="password" autocomplete="<?= $page === 'login' ? 'current-password' : 'new-password' ?>">
                <button class="btn btn-primary btn-lg mt-4"><i data-lucide="<?= $page === 'login' ? 'log-in' : 'user-plus' ?>"></i><?= $page === 'login' ? 'Masuk' : 'Daftar' ?></button>
                <div class="auth-divider"><span>atau</span></div>
                <?php if (GOOGLE_CLIENT_ID !== ''): ?>
                    <button class="google-login-btn" type="button" id="googleLoginBtn" data-client-id="<?= h(GOOGLE_CLIENT_ID) ?>">
                        <span class="google-g">G</span><strong>Sign in with Google</strong>
                    </button>
                    <form method="post" id="googleLoginForm" hidden>
                        <input type="hidden" name="action" value="google_login">
                        <input type="hidden" name="credential" id="googleCredential">
                    </form>
                <?php else: ?>
                    <button class="google-login-btn is-disabled" type="button" disabled>
                        <span class="google-g">G</span><strong>Google Sign-In belum dikonfigurasi</strong>
                    </button>
                <?php endif; ?>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($page === 'public-bird'):
        $stmt = $pdo->prepare('
            SELECT b.*, u.nama_kandang,
                   COUNT(dl.id) total_finish,
                   SUM(l.jarak_meter) total_meter,
                   SUM(dl.waktu_tempuh_menit) total_menit,
                   SUM(l.jarak_meter) / NULLIF(SUM(dl.waktu_tempuh_menit), 0) avg_mpm
            FROM burung b
            JOIN users u ON u.id = b.user_id
            LEFT JOIN detail_latihan dl ON dl.burung_id = b.id AND dl.status_sampai = \'1\'
            LEFT JOIN latihan l ON l.id = dl.latihan_id AND l.user_id = b.user_id
            WHERE b.id = ? AND b.aktif = 1
            GROUP BY b.id
        ');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        $bird = $stmt->fetch();
        if (!$bird) { echo '<div class="empty-state">Merpati publik tidak ditemukan.</div>'; }
        else {
            $stmt = $pdo->prepare('
                SELECT l.*, dl.jam_tiba, dl.waktu_tempuh_menit, dl.kecepatan_mpm, dl.status_sampai, dl.koefisien
                FROM detail_latihan dl
                JOIN latihan l ON l.id = dl.latihan_id
                WHERE dl.burung_id = ? AND dl.status_sampai = \'1\'
                ORDER BY l.jam_lepas DESC
            ');
            $stmt->execute([(int)$bird['id']]);
            $history = $stmt->fetchAll();
    ?>
        <section class="profile-head">
            <?= profile_photo_button($bird['foto'], $bird['nomor_ring']) ?>
            <div><p class="eyebrow">Detail publik</p><h1><?= h(bird_display_name($bird)) ?></h1><span><?= h($bird['nama_kandang']) ?> / <?= h(bird_identity_line($bird)) ?></span></div>
            <div class="profile-metric"><span>Rata-rata</span><strong><?= number_format((float)$bird['avg_mpm'], 1) ?> MPM</strong></div>
        </section>
        <canvas class="chart-box" id="speedChart" data-points='<?= h(json_encode(array_values(array_filter(array_map(fn($r) => $r['kecepatan_mpm'] ? ['label' => date('d/m', strtotime($r['jam_lepas'])), 'value' => (float)$r['kecepatan_mpm']] : null, array_reverse($history)))))) ?>'></canvas>
        <div class="table-wrap responsive-table"><table class="table align-middle"><thead><tr><th>Titik Lepas</th><th>Jarak</th><th>Waktu</th><th>MPM</th><th>Koefisien</th></tr></thead><tbody>
            <?php foreach ($history as $row): $koef = (float)($row['koefisien'] ?: calculate_koefisien((float)$row['kecepatan_mpm'])); ?><tr><td data-label="Titik Lepas"><?= h($row['nama_titik_lepas']) ?><br><small><?= date('d M Y H:i', strtotime($row['jam_lepas'])) ?></small></td><td data-label="Jarak"><?= number_format((float)$row['jarak_meter'] / 1000, 2) ?> km</td><td data-label="Waktu"><?= h(format_duration((float)$row['waktu_tempuh_menit'])) ?></td><td data-label="MPM"><?= number_format((float)$row['kecepatan_mpm'], 2) ?></td><td data-label="Koefisien"><?= number_format($koef, 2) ?> <?= h(koefisien_stars($koef)) ?></td></tr><?php endforeach; ?>
        </tbody></table><?php if (!$history): ?><div class="empty-state">Belum ada riwayat finish untuk ditampilkan publik.</div><?php endif; ?></div>
    <?php } endif; ?>

    <?php if ($page === 'dashboard' && $userId):
        if (is_club_admin() || is_super_admin()):
            $managedClubs = admin_clubs($pdo, $userId);
            $managedClubIds = array_map('intval', array_column($managedClubs, 'id'));
            $clubStats = ['members' => 0, 'pending' => 0, 'active_races' => 0, 'registrations' => 0];
            $clubRaces = [];
            if ($managedClubIds) {
                $placeholders = implode(',', array_fill(0, count($managedClubIds), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id IN ($placeholders) AND status = 'approved'");
                $stmt->execute($managedClubIds);
                $clubStats['members'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id IN ($placeholders) AND status = 'pending'");
                $stmt->execute($managedClubIds);
                $clubStats['pending'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM races WHERE club_id IN ($placeholders) AND status IN ('registration','basketing','released')");
                $stmt->execute($managedClubIds);
                $clubStats['active_races'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM race_registrations rr JOIN races r ON r.id = rr.race_id WHERE r.club_id IN ($placeholders)");
                $stmt->execute($managedClubIds);
                $clubStats['registrations'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("
                    SELECT r.*, c.name AS club_name,
                           (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id) AS total_registered,
                           (SELECT COUNT(*) FROM race_registrations rr WHERE rr.race_id = r.id AND rr.status = 'clocked') AS total_clocked
                    FROM races r
                    JOIN clubs c ON c.id = r.club_id
                    WHERE r.club_id IN ($placeholders)
                    ORDER BY FIELD(r.status, 'released','basketing','registration','draft','finished'), COALESCE(r.actual_release_datetime, r.release_datetime, r.created_at) DESC
                    LIMIT 8
                ");
                $stmt->execute($managedClubIds);
                $clubRaces = $stmt->fetchAll();
            }
    ?>
        <section class="hero">
            <div><p class="eyebrow"><?= is_super_admin() ? 'Super admin' : 'Dashboard club' ?></p><h1>Kontrol operasional lomba dan komunitas.</h1></div>
            <a href="index.php?page=races" class="btn btn-primary btn-lg"><i data-lucide="flag"></i>Kelola Lomba</a>
        </section>
        <section class="stats-grid">
            <div class="stat"><span>Klub Dikelola</span><strong><?= count($managedClubs) ?></strong></div>
            <div class="stat"><span>Anggota Approved</span><strong><?= $clubStats['members'] ?></strong></div>
            <div class="stat"><span>Join Pending</span><strong><?= $clubStats['pending'] ?></strong></div>
            <div class="stat"><span>Lomba Aktif</span><strong><?= $clubStats['active_races'] ?></strong></div>
            <div class="stat"><span>Pendaftaran</span><strong><?= $clubStats['registrations'] ?></strong></div>
        </section>
        <section class="startup-grid">
            <article class="startup-card is-ready"><i data-lucide="package-check"></i><div><span>Basketing</span><strong>Input ring peserta</strong><small>Verifikasi sebelum release agar clocking ETS valid.</small></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=races"><i data-lucide="arrow-right"></i></a></article>
            <article class="startup-card"><i data-lucide="radio-tower"></i><div><span>Jam Lepas</span><strong>Kunci waktu aktual</strong><small>Release memakai waktu server dan tersimpan di audit log.</small></div></article>
            <article class="startup-card"><i data-lucide="users-round"></i><div><span>Anggota</span><strong><?= $clubStats['pending'] ?> pending</strong><small>Approve/reject permintaan anggota klub.</small></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=clubs"><i data-lucide="arrow-right"></i></a></article>
            <?php if (is_super_admin()): ?><article class="startup-card"><i data-lucide="shield-check"></i><div><span>Platform</span><strong>Super Admin</strong><small>User, klub, ETS, dan audit log platform.</small></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=super-admin"><i data-lucide="arrow-right"></i></a></article><?php endif; ?>
        </section>
        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="timer"></i></div><div><h2>Lomba Klub Terbaru</h2><span class="section-note">Prioritas released, basketing, dan pendaftaran.</span></div></div></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Lomba</th><th>Klub</th><th>Status</th><th>Peserta</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($clubRaces as $race): ?>
                    <tr><td data-label="Lomba"><strong><?= h($race['name']) ?></strong><br><small><?= h($race['release_point']) ?></small></td><td data-label="Klub"><?= h($race['club_name']) ?></td><td data-label="Status"><span class="badge text-bg-<?= h(badge_class_for_race($race['status'])) ?>"><?= h($race['status']) ?></span></td><td data-label="Peserta"><?= (int)$race['total_clocked'] ?>/<?= (int)$race['total_registered'] ?> clocked</td><td data-label="Aksi" class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?page=race&id=<?= (int)$race['id'] ?>"><i data-lucide="eye"></i></a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$clubRaces): ?><div class="empty-state">Belum ada lomba dari klub yang kamu kelola.</div><?php endif; ?>
        </div>
    <?php else:
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM latihan WHERE user_id = ? AND status = 'berlangsung'");
        $stmt->execute([$userId]);
        $activeCount = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM detail_latihan dl JOIN latihan l ON l.id = dl.latihan_id WHERE l.user_id = ? AND dl.status_sampai = '1'");
        $stmt->execute([$userId]);
        $finishedCount = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT AVG(dl.kecepatan_mpm) FROM detail_latihan dl JOIN latihan l ON l.id = dl.latihan_id WHERE l.user_id = ? AND dl.status_sampai = '1'");
        $stmt->execute([$userId]);
        $avgSpeed = (float)($stmt->fetchColumn() ?: 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM devices WHERE user_id = ?');
        $stmt->execute([$userId]);
        $deviceCount = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM club_members WHERE user_id = ?');
        $stmt->execute([$userId]);
        $clubCount = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND type = 'marketplace'");
        $stmt->execute([$userId]);
        $marketplaceCount = (int)$stmt->fetchColumn();
    ?>
        <section class="hero">
            <div><p class="eyebrow"><?= h($user['nama_kandang']) ?> / <?= h($user['role'] ?? 'member') ?></p><h1>Mission control komunitas merpati balap.</h1></div>
            <a href="index.php?page=new-training" class="btn btn-primary btn-lg"><i data-lucide="play"></i>Mulai Latihan</a>
        </section>
        <section class="stats-grid">
            <div class="stat"><span>Merpati</span><strong><?= count($allBirds) ?></strong></div>
            <div class="stat"><span>Latihan Aktif</span><strong><?= $activeCount ?></strong></div>
            <div class="stat"><span>Finish</span><strong><?= $finishedCount ?></strong></div>
            <div class="stat"><span>Rata-rata MPM</span><strong><?= number_format($avgSpeed, 1) ?></strong></div>
        </section>
        <section class="startup-grid">
            <article class="startup-card is-ready">
                <i data-lucide="scan-line"></i>
                <div><span>ETS / RFID</span><strong><?= $user['api_key'] ? 'Device API siap' : 'Generate API key' ?></strong><small><?= $deviceCount ?> device terdaftar</small></div>
                <a href="index.php?page=settings" class="btn btn-sm btn-outline-primary">Kelola</a>
            </article>
            <article class="startup-card">
                <i data-lucide="users-round"></i>
                <div><span>Community</span><strong><?= $clubCount ?> club</strong><small>Fondasi club, member, feed, dan event sudah tersedia di database.</small></div>
            </article>
            <article class="startup-card">
                <i data-lucide="shopping-bag"></i>
                <div><span>Marketplace</span><strong><?= $marketplaceCount ?> listing</strong><small>Disiapkan untuk jual burung, pakan, obat, device ETS, dan aksesoris.</small></div>
            </article>
            <article class="startup-card">
                <i data-lucide="line-chart"></i>
                <div><span>Analytics</span><strong>Koefisien + trend</strong><small>Basis data performa siap dikembangkan ke prediksi ETA dan rekomendasi latihan.</small></div>
            </article>
        </section>
        <section class="content-grid">
            <div>
                <div class="section-title"><h2>Latihan Terbaru</h2><a href="index.php?page=history">Lihat semua</a></div>
                <?php $stmt = $pdo->prepare('SELECT * FROM latihan WHERE user_id = ? ORDER BY status ASC, created_at DESC LIMIT 6'); $stmt->execute([$userId]); $sessions = $stmt->fetchAll(); ?>
                <?php foreach ($sessions as $session): ?>
                    <a class="session-row" href="index.php?page=live&id=<?= (int)$session['id'] ?>">
                        <div><strong><?= h($session['nama_titik_lepas']) ?></strong><span><?= date('d M Y H:i', strtotime($session['jam_lepas'])) ?> / <?= number_format((float)$session['jarak_meter'] / 1000, 2) ?> km</span></div>
                        <span class="badge text-bg-<?= $session['status'] === 'berlangsung' ? 'warning' : 'success' ?>"><?= h($session['status']) ?></span>
                    </a>
                <?php endforeach; if (!$sessions): ?><div class="empty-state">Belum ada latihan.</div><?php endif; ?>
            </div>
            <div>
                <div class="section-title"><h2>Top Kandang</h2><a href="index.php?page=rankings">Klasemen</a></div>
                <?php foreach (array_slice($allBirds, 0, 5) as $index => $leader): ?>
                    <div class="leader-row"><span class="rank"><?= $index + 1 ?></span><?= bird_avatar($leader['foto'], $leader['nomor_ring']) ?><div><strong><?= h(bird_display_name($leader)) ?></strong><span><?= h(bird_identity_line($leader)) ?></span></div><b><?= number_format((float)$leader['avg_mpm'], 1) ?> MPM</b></div>
                <?php endforeach; if (!$allBirds): ?><div class="empty-state">Tambahkan merpati pertama.</div><?php endif; ?>
            </div>
        </section>
    <?php endif; endif; ?>

    <?php if ($page === 'join-club' && $userId):
        $clubs = club_rows($pdo, $userId);
    ?>
        <section class="page-head">
            <div><p class="eyebrow">Member club access</p><h1>Gabung Klub Tersedia</h1><span>Member perlu status approved di klub sebelum bisa ikut lomba atau latihan bersama klub.</span></div>
            <a class="btn btn-outline-primary" href="index.php?page=available-races"><i data-lucide="flag"></i>Ikut Lomba</a>
        </section>
        <?php if (($user['admin_approval_status'] ?? 'none') === 'pending'): ?>
            <section class="setup-banner mt-4">
                <div><strong>Request admin menunggu approval.</strong><span>Super admin akan mengaktifkan role admin klub setelah data disetujui.</span></div>
                <a class="btn btn-light" href="index.php?page=notifications"><i data-lucide="bell"></i>Cek Notifikasi</a>
            </section>
        <?php endif; ?>
        <div class="platform-grid mt-4">
            <?php foreach ($clubs as $club): ?>
                <article class="platform-card">
                    <div class="club-card-head">
                        <?= club_logo($club['logo'] ?? null, $club['name']) ?>
                        <div><h3><?= h($club['name']) ?></h3><span><?= h($club['city'] ?: '-') ?><?= $club['province'] ? ', ' . h($club['province']) : '' ?></span></div>
                    </div>
                    <p><?= h($club['description'] ?: 'Klub aktif yang menerima member untuk lomba dan latihan bersama.') ?></p>
                    <div class="club-meta">
                        <span><?= (int)$club['approved_members'] ?> anggota / <?= (int)$club['total_races'] ?> lomba</span>
                    </div>
                    <?php if (($club['member_status'] ?? '') === 'approved'): ?>
                        <span class="badge text-bg-success"><?= h($club['member_role'] === 'admin' ? 'Admin' : 'Member Approved') ?></span>
                    <?php elseif (($club['member_status'] ?? '') === 'pending'): ?>
                        <span class="badge text-bg-warning">Menunggu approval admin klub</span>
                    <?php else: ?>
                        <form method="post"><input type="hidden" name="action" value="join_club"><input type="hidden" name="club_id" value="<?= (int)$club['id'] ?>"><button class="btn btn-outline-primary"><i data-lucide="user-plus"></i>Gabung Klub</button></form>
                    <?php endif; ?>
                </article>
            <?php endforeach; if (!$clubs): ?><div class="empty-state">Belum ada klub approved yang tersedia.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'available-races' && $userId):
        $stmt = $pdo->prepare('SELECT club_id, status FROM club_members WHERE user_id = ?');
        $stmt->execute([$userId]);
        $memberClubStatuses = [];
        foreach ($stmt->fetchAll() as $membership) {
            $memberClubStatuses[(int)$membership['club_id']] = (string)$membership['status'];
        }
        $races = array_values(array_filter(race_rows($pdo, $userId), fn($race) => in_array($race['status'], ['registration','basketing','released','finished'], true)));
    ?>
        <section class="page-head">
            <div><p class="eyebrow">Member race access</p><h1>Ikut Lomba & Latihan Bersama</h1><span>Pilih event dari klub yang sudah kamu ikuti. Clocking lomba resmi hanya menerima data ETS.</span></div>
            <a class="btn btn-outline-primary" href="index.php?page=join-club"><i data-lucide="users-round"></i>Gabung Klub</a>
        </section>
        <div class="table-wrap responsive-table mt-4">
            <table class="table align-middle">
                <thead><tr><th>Lomba</th><th>Klub</th><th>Status</th><th>Akses Klub</th><th>Rencana Lepas</th><th>Peserta</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($races as $race):
                    $clubId = (int)($race['club_id'] ?? 0);
                    $membershipStatus = $clubId > 0 ? ($memberClubStatuses[$clubId] ?? 'none') : 'approved';
                    $canJoinRace = $membershipStatus === 'approved' && in_array($race['status'], ['registration','basketing'], true);
                ?>
                    <tr>
                        <td data-label="Lomba"><strong><?= h($race['name']) ?></strong><br><small><?= h($race['release_point']) ?> / <?= h($race['type']) ?></small></td>
                        <td data-label="Klub"><?= h($race['club_name'] ?: 'Mandiri') ?></td>
                        <td data-label="Status"><span class="badge text-bg-<?= h(badge_class_for_race($race['status'])) ?>"><?= h($race['status']) ?></span></td>
                        <td data-label="Akses Klub">
                            <span class="badge text-bg-<?= $membershipStatus === 'approved' ? 'success' : ($membershipStatus === 'pending' ? 'warning' : 'secondary') ?>">
                                <?= h($membershipStatus === 'none' ? 'Belum bergabung' : $membershipStatus) ?>
                            </span>
                        </td>
                        <td data-label="Rencana Lepas"><?= $race['release_datetime'] ? date('d M Y H:i', strtotime($race['release_datetime'])) : '-' ?></td>
                        <td data-label="Peserta"><?= (int)$race['total_clocked'] ?>/<?= (int)$race['total_registered'] ?> clocked</td>
                        <td data-label="Aksi" class="text-end">
                            <?php if ($canJoinRace || $membershipStatus === 'approved' || $race['status'] === 'finished'): ?>
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=race&id=<?= (int)$race['id'] ?>"><i data-lucide="eye"></i></a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-secondary" href="index.php?page=join-club"><i data-lucide="users-round"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$races): ?><div class="empty-state">Belum ada lomba atau latihan bersama yang tersedia.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'races' && $userId):
        $adminClubs = admin_clubs($pdo, $userId);
        $races = race_rows($pdo, $userId);
        $canCreateRace = user_is_club_admin($pdo, $userId) && count($adminClubs) > 0;
    ?>
        <section class="page-head">
            <div><p class="eyebrow">Race management</p><h1>Lomba & Terbangan Resmi</h1><span>Kelola pendaftaran, basketing, jam lepas, clocking, dan hasil sesuai PRD TrackPigeon.</span></div>
            <a class="btn btn-outline-primary" href="index.php?page=clubs"><i data-lucide="users-round"></i>Klub</a>
        </section>

        <?php if ($canCreateRace): ?>
        <form class="form-panel mt-4" method="post">
            <input type="hidden" name="action" value="create_race">
            <div class="section-title mb-3"><h2>Buat Lomba</h2><span class="section-badge">Admin klub</span></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Klub Penyelenggara</label><select class="form-select" name="club_id" required><?php foreach ($adminClubs as $club): ?><option value="<?= (int)$club['id'] ?>"><?= h($club['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Nama Lomba</label><input required class="form-control" name="name" placeholder="Contoh: Seri Priangan Terbangan 1"></div>
                <div class="col-md-4"><label class="form-label">Tipe</label><select class="form-select" name="type"><option value="lomba">Lomba</option><option value="latihan_bersama">Latihan Bersama</option><option value="latihan_mandiri">Latihan Mandiri</option></select></div>
                <div class="col-md-4"><label class="form-label">Status Awal</label><select class="form-select" name="status"><option value="registration">Buka Pendaftaran</option><option value="draft">Draft</option></select></div>
                <div class="col-md-4"><label class="form-label">Rencana Lepas</label><input class="form-control" type="datetime-local" name="release_datetime" value="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-md-6"><label class="form-label">Titik Lepas</label><input required class="form-control" name="release_point" placeholder="Nama kota/titik pelepasan"></div>
                <div class="col-md-3"><label class="form-label">Latitude</label><input required class="form-control" id="raceReleaseLat" type="number" step="0.00000001" name="release_lat"></div>
                <div class="col-md-3"><label class="form-label">Longitude</label><input required class="form-control" id="raceReleaseLon" type="number" step="0.00000001" name="release_lng"></div>
                <div class="col-md-3"><label class="form-label">Max Peserta</label><input class="form-control" type="number" min="1" name="max_participants"></div>
                <div class="col-md-3"><label class="form-label">Iuran/Event Fee</label><input class="form-control" type="number" min="0" step="1000" name="entry_fee" value="0"></div>
                <div class="col-md-3"><label class="form-label">Hadiah Non-Tunai</label><input class="form-control" name="prize_info" placeholder="Voucher, pakan, produk"></div>
                <div class="col-md-3"><label class="form-label">Sponsor</label><input class="form-control" name="sponsor_info" placeholder="Nama sponsor"></div>
                <div class="col-12"><label class="form-label">Catatan Panitia</label><textarea class="form-control" name="notes" rows="2" placeholder="Aturan lokal, titik kumpul, jam basketing"></textarea></div>
            </div>
            <div class="map-panel"><div class="map-title"><div><strong>Pilih Titik Lepas Lomba</strong><span>Jarak loft peserta akan dihitung dan disimpan sebagai snapshot saat mendaftar.</span></div><button class="btn btn-outline-secondary btn-sm locate-btn" type="button" data-map-target="raceReleaseMap"><i data-lucide="crosshair"></i>Lokasi Saya</button></div><div id="raceReleaseMap" class="map-box" data-lat="<?= h($defaultMapLat) ?>" data-lon="<?= h($defaultMapLon) ?>" data-input-lat="raceReleaseLat" data-input-lon="raceReleaseLon"></div></div>
            <div class="form-actions"><button class="btn btn-primary"><i data-lucide="flag"></i>Buat Lomba</button></div>
        </form>
        <?php else: ?>
            <section class="setup-banner mt-4">
                <div><strong>Mode peserta.</strong><span>Pembuatan lomba hanya untuk admin klub atau super admin. Kamu tetap bisa melihat lomba dan mendaftarkan burung dari daftar di bawah.</span></div>
                <a class="btn btn-light" href="index.php?page=clubs"><i data-lucide="users-round"></i>Kelola Klub</a>
            </section>
        <?php endif; ?>

        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="timer"></i></div><div><h2>Daftar Lomba</h2><span class="section-note">Polling live tetap tersedia sebagai fallback real-time.</span></div></div><span class="section-badge"><?= count($races) ?> Lomba</span></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Lomba</th><th>Klub</th><th>Status</th><th>Rencana Lepas</th><th>Peserta</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($races as $race): ?>
                    <tr>
                        <td data-label="Lomba"><strong><?= h($race['name']) ?></strong><br><small><?= h($race['release_point']) ?> / <?= h($race['type']) ?></small></td>
                        <td data-label="Klub"><?= h($race['club_name'] ?: 'Mandiri') ?><br><small><?= h($race['creator_loft']) ?></small></td>
                        <td data-label="Status"><span class="badge text-bg-<?= h(badge_class_for_race($race['status'])) ?>"><?= h($race['status']) ?></span></td>
                        <td data-label="Rencana Lepas"><?= $race['release_datetime'] ? date('d M Y H:i', strtotime($race['release_datetime'])) : '-' ?></td>
                        <td data-label="Peserta"><?= (int)$race['total_clocked'] ?>/<?= (int)$race['total_registered'] ?> clocked</td>
                        <td data-label="Aksi" class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?page=race&id=<?= (int)$race['id'] ?>"><i data-lucide="eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$races): ?><div class="empty-state">Belum ada lomba. Buat lomba pertama untuk membuka pendaftaran.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'race' && $userId):
        $stmt = $pdo->prepare('
            SELECT r.*, c.name AS club_name, c.owner_user_id, u.nama_kandang AS creator_loft
            FROM races r
            LEFT JOIN clubs c ON c.id = r.club_id
            JOIN users u ON u.id = r.created_by
            WHERE r.id = ?
        ');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        $race = $stmt->fetch();
        if (!$race) { echo '<div class="empty-state">Lomba tidak ditemukan.</div>'; }
        else {
            $canManage = user_can_manage_race($pdo, (int)$race['id'], $userId) !== null;
            $canParticipate = empty($race['club_id']) || user_is_approved_club_member($pdo, $userId, (int)$race['club_id']);
            $standings = race_standings($pdo, (int)$race['id']);
            $registeredBirdIds = array_map('intval', array_column(array_filter($standings, fn($row) => (int)$row['user_id'] === $userId), 'bird_id'));
            $availableBirds = array_values(array_filter($allBirds, fn($bird) => ($bird['status'] ?? 'aktif') === 'aktif' && !in_array((int)$bird['id'], $registeredBirdIds, true)));
            $clockedRows = array_values(array_filter($standings, fn($row) => !empty($row['arrival_datetime'])));
        ?>
        <section class="live-head">
            <div>
                <p class="eyebrow"><span class="live-dot"></span><?= h($race['status']) ?> / <?= h($race['club_name'] ?: 'Mandiri') ?></p>
                <h1><?= h($race['name']) ?></h1>
                <span><?= h($race['release_point']) ?> / <?= $race['release_datetime'] ? date('d M Y H:i', strtotime($race['release_datetime'])) : 'Jadwal belum diisi' ?> / <?= h($race['type']) ?></span>
            </div>
            <div class="race-head-actions">
                <a class="btn btn-outline-secondary" href="index.php?page=races"><i data-lucide="arrow-left"></i>Daftar Lomba</a>
                <?php if ($canManage && $race['status'] !== 'finished'): ?>
                    <form method="post" onsubmit="return confirm('Publikasikan hasil dan akhiri lomba ini?')"><input type="hidden" name="action" value="finish_race"><input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>"><button class="btn btn-outline-danger"><i data-lucide="flag"></i>Finalisasi</button></form>
                <?php endif; ?>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat"><span>Peserta</span><strong><?= count($standings) ?></strong></div>
            <div class="stat"><span>Clocked</span><strong><?= count($clockedRows) ?></strong></div>
            <div class="stat"><span>Jam Lepas Aktual</span><strong><?= $race['actual_release_datetime'] ? date('H:i:s', strtotime($race['actual_release_datetime'])) : '-' ?></strong></div>
            <div class="stat"><span>Iuran</span><strong><?= format_rupiah((float)$race['entry_fee']) ?></strong></div>
        </section>

        <?php if ($canManage): ?>
            <div class="status-tabs mt-4">
                <a href="index.php?page=race&id=<?= (int)$race['id'] ?>" class="status-tab <?= ($_GET['view'] ?? '') !== 'basketing' ? 'active' : '' ?>">Monitor</a>
                <a href="index.php?page=race&id=<?= (int)$race['id'] ?>&view=basketing" class="status-tab <?= ($_GET['view'] ?? '') === 'basketing' ? 'active' : '' ?>">Basketing <span class="tab-count"><?= count(array_filter($standings, fn($row) => in_array($row['status'], ['basketing','released','clocked'], true))) ?></span></a>
            </div>
        <?php endif; ?>

        <?php if ($canManage && ($_GET['view'] ?? '') === 'basketing' && in_array($race['status'], ['registration','basketing'], true)): ?>
            <form class="form-panel mt-4" method="post">
                <input type="hidden" name="action" value="basketing_by_ring">
                <input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>">
                <div class="section-title mb-3"><h2>Input Ring Basketing</h2><span class="section-badge">Admin klub</span></div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-8"><label class="form-label">Nomor Ring Peserta</label><input class="form-control" name="ring" placeholder="Scan atau ketik nomor ring" autofocus></div>
                    <div class="col-md-4"><button class="btn btn-primary w-100"><i data-lucide="package-check"></i>Verifikasi Basketing</button></div>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($canManage && empty($race['actual_release_datetime'])): ?>
            <form class="form-panel mt-4" method="post" onsubmit="return confirm('Jam lepas aktual akan dikunci dan tidak bisa diubah tanpa audit. Lanjutkan?')">
                <input type="hidden" name="action" value="release_race"><input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8"><label class="form-label">Jam Lepas Aktual</label><input class="form-control" type="datetime-local" name="actual_release_datetime" value="<?= date('Y-m-d\TH:i') ?>"></div>
                    <div class="col-md-4"><button class="btn btn-primary w-100"><i data-lucide="radio-tower"></i>Kunci & Lepas</button></div>
                </div>
            </form>
        <?php endif; ?>

        <?php if (in_array($race['status'], ['registration','basketing'], true) && !$canParticipate): ?>
            <section class="setup-banner mt-4">
                <div><strong>Gabung klub dulu.</strong><span>Pendaftaran burung hanya dibuka untuk member yang sudah approved di klub penyelenggara.</span></div>
                <a class="btn btn-light" href="index.php?page=join-club"><i data-lucide="users-round"></i>Gabung Klub</a>
            </section>
        <?php endif; ?>

        <?php if (in_array($race['status'], ['registration','basketing'], true) && $canParticipate): ?>
            <form class="form-panel mt-4" method="post">
                <input type="hidden" name="action" value="register_race_birds"><input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>">
                <div class="section-title mb-3"><h2>Daftarkan Burung</h2><span class="section-badge"><?= count($availableBirds) ?> tersedia</span></div>
                <div class="bird-check-grid">
                    <?php foreach ($availableBirds as $idx => $bird): ?>
                        <label class="bird-check"><input type="checkbox" name="bird_id[]" value="<?= (int)$bird['id'] ?>"><span class="bird-seq-num"><?= $idx + 1 ?></span><?= bird_avatar($bird['foto'], $bird['nomor_ring']) ?><span><strong><?= h(bird_display_name($bird)) ?></strong><small><?= h($bird['nomor_ring']) ?> / <?= h($bird['warna']) ?><?= $bird['rfid_tag'] ? ' / RFID ' . h($bird['rfid_tag']) : '' ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <?php if (!$availableBirds): ?><div class="empty-state">Semua burung aktif kamu sudah terdaftar atau belum ada burung aktif.</div><?php endif; ?>
                <div class="form-actions"><button class="btn btn-primary" <?= !$availableBirds ? 'disabled' : '' ?>><i data-lucide="send"></i>Kirim Pendaftaran</button></div>
            </form>
        <?php endif; ?>

        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="trophy"></i></div><div><h2>Live Hasil Terbangan</h2><span class="section-note">Rank dihitung dari MPM server-side. Clocking diterima dari ETS atau manual GPS yang lolos validasi kandang.</span></div></div><span class="section-badge"><?= count($clockedRows) ?>/<?= count($standings) ?> finish</span></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Rank</th><th>Pemilik</th><th>No Ring</th><th>MPM</th><th>Jarak</th><th>Lepas</th><th>Datang</th><th>Tempuh</th><th>Koef</th><th>Metode</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php $rank = 1; foreach ($standings as $row): $arrived = !empty($row['arrival_datetime']); ?>
                    <tr>
                        <td data-label="Rank"><?= $arrived ? $rank++ : '-' ?></td>
                        <td data-label="Pemilik"><strong><?= h($row['nama_pemilik'] ?: $row['nama_kandang']) ?></strong><br><small><?= h($row['nama_kandang']) ?></small></td>
                        <td data-label="No Ring"><?= h($row['nomor_ring']) ?><br><small><?= h($row['warna']) ?> / <?= h($row['status']) ?></small></td>
                        <td data-label="MPM"><?= $arrived ? number_format((float)$row['speed_mpm'], 2) : '-' ?></td>
                        <td data-label="Jarak"><?= $row['distance_km'] ? number_format((float)$row['distance_km'], 3) . ' km' : '-' ?></td>
                        <td data-label="Lepas"><?= $race['actual_release_datetime'] ? date('H:i:s', strtotime($race['actual_release_datetime'])) : '-' ?></td>
                        <td data-label="Datang"><?= $arrived ? date('H:i:s', strtotime($row['arrival_datetime'])) : '-' ?></td>
                        <td data-label="Tempuh"><?= $arrived ? format_duration(((int)$row['flight_seconds']) / 60) : '-' ?></td>
                        <td data-label="Koef"><?= $arrived ? number_format((float)$row['koefisien'], 2) : '-' ?></td>
                        <td data-label="Metode"><?= $arrived ? h(strtoupper($row['method'])) : '-' ?></td>
                        <td data-label="Aksi" class="text-end">
                            <div class="table-actions">
                            <?php if ($canManage && !$arrived && in_array($race['status'], ['registration','basketing'], true)): ?>
                                <form method="post"><input type="hidden" name="action" value="update_registration_status"><input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>"><input type="hidden" name="registration_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-outline-primary" title="Approve"><i data-lucide="check"></i></button></form>
                                <form method="post"><input type="hidden" name="action" value="update_registration_status"><input type="hidden" name="race_id" value="<?= (int)$race['id'] ?>"><input type="hidden" name="registration_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="status" value="basketing"><button class="btn btn-sm btn-outline-secondary" title="Basketing"><i data-lucide="package-check"></i></button></form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$standings): ?><div class="empty-state">Belum ada peserta terdaftar.</div><?php endif; ?>
        </div>
        <?php } endif; ?>

    <?php if ($page === 'clocking' && $userId):
        $stmt = $pdo->prepare('
            SELECT rr.*, r.name AS race_name, r.release_point, r.actual_release_datetime, r.status AS race_status,
                   b.nomor_ring, b.nama_burung, b.warna, b.foto,
                   c.arrival_datetime, c.speed_mpm, c.koefisien, c.method
            FROM race_registrations rr
            JOIN races r ON r.id = rr.race_id
            JOIN burung b ON b.id = rr.bird_id
            LEFT JOIN clockings c ON c.registration_id = rr.id
            WHERE rr.user_id = ? AND r.status = "released"
            ORDER BY r.actual_release_datetime DESC, b.nomor_ring ASC
        ');
        $stmt->execute([$userId]);
        $clockingRows = $stmt->fetchAll();
        $serverNow = app_datetime();
    ?>
        <section class="page-head">
            <div><p class="eyebrow">Clocking GPS anti-kecurangan</p><h1>Clocking Kedatangan</h1><span>Waktu datang memakai jam server. GPS wajib aktif, akurasi maksimal <?= (int)GPS_CLOCKING_MAX_ACCURACY_METER ?> m, dan posisi maksimal <?= (int)GPS_CLOCKING_MAX_DISTANCE_METER ?> m dari koordinat kandang.</span></div>
            <div class="server-clock" data-server-time="<?= h($serverNow->format(DateTimeInterface::ATOM)) ?>"><?= h($serverNow->format('H:i:s')) ?> WIB</div>
        </section>
        <section class="setup-banner mt-4">
            <div><strong>Aturan resmi.</strong><span>Burung harus terdaftar, sudah di-basketing admin, lomba sudah released, satu clocking per burung, cooldown 30 detik per ring, dan GPS browser wajib berada di area kandang.</span></div>
            <a class="btn btn-light" href="index.php?page=races"><i data-lucide="flag"></i>Lihat Lomba</a>
        </section>
        <div class="table-wrap responsive-table mt-4">
            <table class="table align-middle">
                <thead><tr><th>Lomba</th><th>Burung</th><th>Basketing</th><th>Status</th><th>Datang</th><th>MPM</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($clockingRows as $row):
                    $arrived = !empty($row['arrival_datetime']);
                    $basketed = (int)($row['basketing_verified'] ?? 0) === 1 || !empty($row['basketing_datetime']) || !empty($row['basketed_at']);
                    $canClock = !$arrived && $basketed && in_array($row['status'], ['released','basketing','basketed'], true);
                ?>
                    <tr>
                        <td data-label="Lomba"><strong><?= h($row['race_name']) ?></strong><br><small><?= h($row['release_point']) ?> / Lepas <?= $row['actual_release_datetime'] ? date('H:i:s', strtotime($row['actual_release_datetime'])) : '-' ?></small></td>
                        <td data-label="Burung"><?= bird_avatar($row['foto'], $row['nomor_ring']) ?><strong><?= h(bird_display_name($row)) ?></strong><br><small><?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?></small></td>
                        <td data-label="Basketing"><span class="badge text-bg-<?= $basketed ? 'success' : 'warning' ?>"><?= $basketed ? 'Verified' : 'Belum' ?></span></td>
                        <td data-label="Status"><?= h($row['status']) ?></td>
                        <td data-label="Datang"><?= $arrived ? date('H:i:s', strtotime($row['arrival_datetime'])) : '-' ?></td>
                        <td data-label="MPM"><?= $arrived ? number_format((float)$row['speed_mpm'], 2) : '-' ?></td>
                        <td data-label="Aksi" class="text-end">
                            <?php if ($canClock): ?>
                                <button class="btn btn-sm btn-outline-danger race-clock-btn" type="button" data-registration="<?= (int)$row['id'] ?>" data-ring="<?= h($row['nomor_ring']) ?>" data-loft-lat="<?= h((string)($row['loft_lat'] ?? '')) ?>" data-loft-lng="<?= h((string)($row['loft_lng'] ?? '')) ?>"><i data-lucide="timer"></i>Clock</button>
                            <?php elseif ($arrived): ?>
                                <span class="badge text-bg-success">Tercatat <?= h(strtoupper((string)$row['method'])) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Menunggu validasi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$clockingRows): ?><div class="empty-state">Belum ada lomba released untuk clocking manual.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'birds' && $userId): ?>
        <section class="page-head"><div><p class="eyebrow">Master data</p><h1>Merpati Saya</h1></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#birdModal"><i data-lucide="plus"></i>Tambah</button></section>

        <div class="status-tabs">
            <a href="index.php?page=birds" class="status-tab <?= !$birdStatusFilter ? 'active' : '' ?>">Semua <span class="tab-count"><?= $birdStatusCounts['semua'] ?></span></a>
            <a href="index.php?page=birds&status=aktif" class="status-tab <?= $birdStatusFilter === 'aktif' ? 'active' : '' ?>">Aktif <span class="tab-count"><?= $birdStatusCounts['aktif'] ?></span></a>
            <a href="index.php?page=birds&status=hilang" class="status-tab <?= $birdStatusFilter === 'hilang' ? 'active' : '' ?>">Hilang <span class="tab-count"><?= $birdStatusCounts['hilang'] ?></span></a>
            <a href="index.php?page=birds&status=pensiun" class="status-tab <?= $birdStatusFilter === 'pensiun' ? 'active' : '' ?>">Pensiun <span class="tab-count"><?= $birdStatusCounts['pensiun'] ?></span></a>
            <a href="index.php?page=birds&status=terjual" class="status-tab <?= $birdStatusFilter === 'terjual' ? 'active' : '' ?>">Terjual <span class="tab-count"><?= $birdStatusCounts['terjual'] ?></span></a>
        </div>

        <div class="tool-row">
            <div class="search-box"><i data-lucide="scan-search"></i><input id="birdRingSearch" type="search" placeholder="Cari nomor ring"></div>
            <div class="search-box"><i data-lucide="palette"></i><input id="birdColorSearch" type="search" placeholder="Cari warna"></div>
            <select class="form-select bird-filter-select" id="birdGenderSearch" aria-label="Filter jenis kelamin">
                <option value="">Semua jenis kelamin</option>
                <option value="jantan">Jantan</option>
                <option value="betina">Betina</option>
                <option value="-">Tidak diisi</option>
            </select>
            <button class="btn btn-outline-secondary" type="button" id="birdFilterReset"><i data-lucide="rotate-ccw"></i>Reset</button>
        </div>
        <div class="bird-card-grid" id="birdTable">
            <?php foreach ($filteredBirds as $bird): ?>
                <article class="bird-card" data-ring="<?= h(strtolower($bird['nomor_ring'])) ?>" data-color="<?= h(strtolower($bird['warna'])) ?>" data-gender="<?= h(strtolower($bird['jenis_kelamin'] ?: '-')) ?>">
                    <?= bird_avatar($bird['foto'], $bird['nomor_ring']) ?>
                    <div>
                        <a href="index.php?page=bird&id=<?= (int)$bird['id'] ?>"><?= h(bird_display_name($bird)) ?></a>
                        <span><?= h(bird_identity_line($bird)) ?><?= $bird['rfid_tag'] ? ' / RFID ' . h($bird['rfid_tag']) : '' ?></span>
                        <strong><?= number_format((float)$bird['avg_mpm'], 1) ?> MPM</strong>
                        <span class="badge-status badge-<?= h($bird['status'] ?? 'aktif') ?>"><?= ucfirst(h($bird['status'] ?? 'aktif')) ?></span>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary edit-bird"
                        data-id="<?= (int)$bird['id'] ?>"
                        data-ring="<?= h($bird['nomor_ring']) ?>"
                        data-rfid="<?= h($bird['rfid_tag']) ?>"
                        data-nama="<?= h($bird['nama_burung'] ?? '') ?>"
                        data-warna="<?= h($bird['warna']) ?>"
                        data-jk="<?= h($bird['jenis_kelamin']) ?>"
                        data-lahir="<?= h($bird['tanggal_lahir'] ?? '') ?>"
                        data-bloodline="<?= h($bird['bloodline'] ?? '') ?>"
                        data-sire="<?= h($bird['induk_jantan'] ?? '') ?>"
                        data-dam="<?= h($bird['induk_betina'] ?? '') ?>"
                        data-weight="<?= h((string)($bird['berat_gram'] ?? '')) ?>"
                        data-status="<?= h($bird['status'] ?? 'aktif') ?>"
                        data-catatan="<?= h($bird['catatan'] ?? '') ?>">
                        <i data-lucide="pencil"></i>
                    </button>
                    <form method="post" onsubmit="return confirm('Nonaktifkan merpati ini?')"><input type="hidden" name="action" value="delete_bird"><input type="hidden" name="id" value="<?= (int)$bird['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button></form>
                </article>
            <?php endforeach; if (!$filteredBirds): ?><div class="empty-state">Tidak ada merpati dengan status ini.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'bird' && $userId):
        $stmt = $pdo->prepare('SELECT * FROM burung WHERE id = ? AND user_id = ? AND aktif = 1');
        $stmt->execute([(int)($_GET['id'] ?? 0), $userId]);
        $bird = $stmt->fetch();
        if (!$bird) { echo '<div class="empty-state">Merpati tidak ditemukan.</div>'; }
        else {
            $stmt = $pdo->prepare("SELECT l.*, dl.jam_tiba, dl.waktu_tempuh_menit, dl.kecepatan_mpm, dl.status_sampai, dl.koefisien FROM detail_latihan dl JOIN latihan l ON l.id = dl.latihan_id WHERE dl.burung_id = ? AND l.user_id = ? ORDER BY l.jam_lepas DESC");
            $stmt->execute([(int)$bird['id'], $userId]);
            $history = $stmt->fetchAll();
            $birdAvg = 0.0;
            foreach ($history as $row) {
                if ($row['status_sampai'] === '1') { $birdAvg += (float)$row['kecepatan_mpm']; }
            }
    ?>
        <section class="profile-head"><?= profile_photo_button($bird['foto'], $bird['nomor_ring']) ?><div><p class="eyebrow">Profil merpati</p><h1><?= h(bird_display_name($bird)) ?></h1><span><?= h(bird_identity_line($bird)) ?></span></div></section>
        <section class="bird-profile-grid">
            <div><span>Nomor Ring</span><strong><?= h($bird['nomor_ring']) ?></strong></div>
            <div><span>RFID</span><strong><?= h($bird['rfid_tag'] ?: '-') ?></strong></div>
            <div><span>Tanggal Lahir</span><strong><?= $bird['tanggal_lahir'] ? date('d M Y', strtotime($bird['tanggal_lahir'])) : '-' ?></strong></div>
            <div><span>Berat</span><strong><?= $bird['berat_gram'] ? number_format((float)$bird['berat_gram'], 0) . ' g' : '-' ?></strong></div>
            <div><span>Induk Jantan</span><strong><?= h($bird['induk_jantan'] ?: '-') ?></strong></div>
            <div><span>Induk Betina</span><strong><?= h($bird['induk_betina'] ?: '-') ?></strong></div>
        </section>
        <canvas class="chart-box" id="speedChart" data-points='<?= h(json_encode(array_values(array_filter(array_map(fn($r) => $r['kecepatan_mpm'] ? ['label' => date('d/m', strtotime($r['jam_lepas'])), 'value' => (float)$r['kecepatan_mpm']] : null, array_reverse($history)))))) ?>'></canvas>
        <div class="table-wrap responsive-table"><table class="table align-middle"><thead><tr><th>Titik Lepas</th><th>Jarak</th><th>Waktu</th><th>MPM</th><th>Koefisien</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($history as $row): ?><tr><td data-label="Titik Lepas"><?= h($row['nama_titik_lepas']) ?><br><small><?= date('d M Y H:i', strtotime($row['jam_lepas'])) ?></small></td><td data-label="Jarak"><?= number_format((float)$row['jarak_meter'] / 1000, 2) ?> km</td><td data-label="Waktu"><?= $row['waktu_tempuh_menit'] ? h(format_duration((float)$row['waktu_tempuh_menit'])) : '-' ?></td><td data-label="MPM"><?= $row['kecepatan_mpm'] ? number_format((float)$row['kecepatan_mpm'], 2) : '-' ?></td><td data-label="Koefisien"><?= $row['koefisien'] ? number_format((float)$row['koefisien'], 2) . ' ' . h(koefisien_stars((float)$row['koefisien'])) : '-' ?></td><td data-label="Status"><span class="badge text-bg-<?= $row['status_sampai'] === '1' ? 'success' : 'secondary' ?>"><?= $row['status_sampai'] === '1' ? 'Sampai' : 'Dalam Perjalanan' ?></span></td></tr><?php endforeach; ?>
        </tbody></table><?php if (!$history): ?><div class="empty-state">Belum ada riwayat latihan.</div><?php endif; ?></div>
    <?php } endif; ?>

    <?php if ($page === 'new-training' && $userId): ?>
        <section class="page-head"><div><p class="eyebrow">Sesi latihan</p><h1>Buat Latihan Baru</h1></div></section>
        <form class="form-panel" method="post">
            <input type="hidden" name="action" value="create_training">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nama Sesi</label><input class="form-control" name="nama_sesi" placeholder="Contoh: Latihan Pagi"></div>
                <div class="col-md-6"><label class="form-label">Nama Titik Lepas</label><input required class="form-control" name="nama_titik_lepas"></div>
                <div class="col-md-6"><label class="form-label">Tanggal & Jam Lepas</label><input required class="form-control" type="datetime-local" name="jam_lepas" value="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-md-6"><label class="form-label">Latitude Titik Lepas</label><input required class="form-control" id="releaseLat" type="number" step="0.00000001" name="lat_lepas"></div>
                <div class="col-md-6"><label class="form-label">Longitude Titik Lepas</label><input required class="form-control" id="releaseLon" type="number" step="0.00000001" name="lon_lepas"></div>
                <div class="col-md-6 d-flex align-items-end"><label class="switch-field"><input type="checkbox" name="is_public" value="1"><span><strong>Publik di Global Live Race</strong><small>Aktifkan agar latihan tampil di landing page.</small></span></label></div>
            </div>
            <div class="map-panel"><div class="map-title"><div><strong>Pilih Titik Lepas di Peta</strong><span>Klik peta untuk mengisi koordinat.</span></div><button class="btn btn-outline-secondary btn-sm locate-btn" type="button" data-map-target="releaseMap"><i data-lucide="crosshair"></i>Lokasi Saya</button></div><div id="releaseMap" class="map-box" data-lat="<?= h($defaultMapLat) ?>" data-lon="<?= h($defaultMapLon) ?>" data-input-lat="releaseLat" data-input-lon="releaseLon"></div></div>
            <div class="squad-tools">
                <div>
                    <h2 class="subhead">Pilih Merpati</h2>
                    <span>Hanya merpati berstatus <strong>Aktif</strong> yang bisa dipilih.</span>
                </div>
                <div class="squad-actions">
                    <div class="search-box"><i data-lucide="search"></i><input id="squadQuickFilter" type="search" placeholder="Filter ring atau warna"></div>
                    <button class="btn btn-outline-secondary" type="button" id="invertSelectionBtn"><i data-lucide="shuffle"></i>Invert</button>
                    <button class="btn btn-primary select-all-toggle" type="button" id="selectAllToggle"><i data-lucide="users"></i><span>Pilih Semua</span></button>
                    <span class="bird-counter" id="birdCounter">0/0 dipilih</span>
                </div>
            </div>
            <div class="bird-check-grid" id="trainingBirdGrid">
                <?php $aktifBirds = array_values(array_filter($allBirds, fn($b) => ($b['status'] ?? 'aktif') === 'aktif')); ?>
                <?php foreach ($aktifBirds as $idx => $bird): ?>
                    <label class="bird-check" data-ring="<?= h(strtolower($bird['nomor_ring'])) ?>" data-color="<?= h(strtolower($bird['warna'])) ?>">
                        <input type="checkbox" name="burung_id[]" value="<?= (int)$bird['id'] ?>" class="bird-check-input">
                        <span class="bird-seq-num"><?= $idx + 1 ?></span>
                        <?= bird_avatar($bird['foto'], $bird['nomor_ring']) ?>
                        <span><strong><?= h(bird_display_name($bird)) ?></strong><small><?= h($bird['nomor_ring']) ?> / <?= h($bird['warna']) ?><?= $bird['avg_mpm'] > 0 ? ' / ' . number_format((float)$bird['avg_mpm'], 0) . ' MPM' : '' ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (!$aktifBirds): ?><div class="empty-state">Tidak ada merpati aktif. Tambahkan atau ubah status merpati ke Aktif.</div><?php endif; ?>
            <div class="form-actions"><button class="btn btn-primary btn-lg" <?= (!profile_complete($user) || !$aktifBirds) ? 'disabled' : '' ?>><i data-lucide="play"></i>Mulai Latihan</button></div>
        </form>
    <?php endif; ?>

    <?php if ($page === 'live' && $userId):
        $stmt = $pdo->prepare('SELECT * FROM latihan WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)($_GET['id'] ?? 0), $userId]);
        $session = $stmt->fetch();
        if (!$session) { echo '<div class="empty-state">Latihan tidak ditemukan.</div>'; }
        else { $rows = training_rows($pdo, (int)$session['id'], $userId); ?>
        <section class="live-head"><div><p class="eyebrow">Live race <?= (int)$session['is_public'] === 1 ? '/ publik' : '/ privat' ?></p><h1><?= h($session['nama_sesi'] ?: $session['nama_titik_lepas']) ?></h1><span><?= h($session['nama_titik_lepas']) ?> / <?= number_format((float)$session['jarak_meter'] / 1000, 2) ?> km / Lepas <?= date('d M Y H:i', strtotime($session['jam_lepas'])) ?></span></div><form method="post"><input type="hidden" name="action" value="finish_training"><input type="hidden" name="id" value="<?= (int)$session['id'] ?>"><button class="btn btn-outline-danger session-finish-btn"><i data-lucide="flag"></i>Akhiri Sesi</button></form></section>
        <div class="tool-row">
            <div class="search-box"><i data-lucide="scan-search"></i><input id="liveRingSearch" type="search" placeholder="Cari nomor ring"></div>
            <div class="search-box"><i data-lucide="palette"></i><input id="liveColorSearch" type="search" placeholder="Cari warna"></div>
            <select class="form-select bird-filter-select" id="liveGenderSearch" aria-label="Filter jenis kelamin live race">
                <option value="">Semua jenis kelamin</option>
                <option value="jantan">Jantan</option>
                <option value="betina">Betina</option>
                <option value="-">Tidak diisi</option>
            </select>
            <button class="btn btn-outline-secondary" type="button" id="liveFilterReset"><i data-lucide="rotate-ccw"></i>Reset</button>
        </div>
        <div class="live-table" id="liveRaceTable"><?php foreach ($rows as $index => $row): ?><div class="race-row <?= $row['status_sampai'] === '1' ? 'arrived' : '' ?>" data-detail="<?= (int)$row['id'] ?>" data-ring="<?= h(strtolower($row['nomor_ring'])) ?>" data-color="<?= h(strtolower($row['warna'])) ?>" data-gender="<?= h(strtolower($row['jenis_kelamin'] ?: '-')) ?>"><span class="rank"><?= $row['status_sampai'] === '1' ? $index + 1 : '-' ?></span><?= bird_avatar($row['foto'], $row['nomor_ring']) ?><div class="race-info"><strong><?= h(bird_display_name($row)) ?></strong><span><?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?> / <?= h($row['jenis_kelamin'] ?: 'Tidak diisi') ?> / <?= $row['jam_tiba'] ? date('H:i:s', strtotime($row['jam_tiba'])) . ' / ' . h($row['metode_checkin']) : 'Dalam Perjalanan' ?></span></div><div class="race-speed"><?= $row['kecepatan_mpm'] ? number_format((float)$row['kecepatan_mpm'], 2) . '<small>MPM / K ' . number_format((float)$row['koefisien'], 2) . '</small>' : '<small>Menunggu</small>' ?></div><button class="finish-btn" <?= $row['status_sampai'] === '1' || $session['status'] === 'selesai' ? 'disabled' : '' ?>><span>Tandai</span><strong>Tiba</strong></button></div><?php endforeach; ?></div>
        <div class="empty-state" id="liveFilterEmpty" hidden>Tidak ada burung yang cocok dengan filter.</div>
    <?php } endif; ?>

    <?php if ($page === 'history' && $userId):
        $stmt = $pdo->prepare('SELECT * FROM latihan WHERE user_id = ? ORDER BY jam_lepas DESC'); $stmt->execute([$userId]); $history = $stmt->fetchAll();
    ?>
        <section class="page-head"><div><p class="eyebrow">Riwayat</p><h1>Semua Latihan</h1></div><a class="btn btn-primary" href="index.php?page=new-training"><i data-lucide="plus"></i>Baru</a></section>
        <div class="table-wrap responsive-table"><table class="table align-middle"><thead><tr><th>Sesi</th><th>Jarak</th><th>Jam Lepas</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach ($history as $session): ?><tr><td data-label="Sesi"><?= h($session['nama_sesi'] ?: $session['nama_titik_lepas']) ?><br><small><?= h($session['nama_titik_lepas']) ?> / <?= (int)$session['is_public'] === 1 ? 'Publik' : 'Privat' ?></small></td><td data-label="Jarak"><?= number_format((float)$session['jarak_meter'] / 1000, 2) ?> km</td><td data-label="Jam Lepas"><?= date('d M Y H:i', strtotime($session['jam_lepas'])) ?></td><td data-label="Status"><span class="badge text-bg-<?= $session['status'] === 'berlangsung' ? 'warning' : 'success' ?>"><?= h($session['status']) ?></span></td><td data-label="Aksi" class="text-end"><div class="table-actions"><a class="btn btn-sm btn-outline-primary" href="index.php?page=live&id=<?= (int)$session['id'] ?>"><i data-lucide="eye"></i></a><form method="post" onsubmit="return confirm('Hapus latihan ini? Data check-in akan ikut terhapus dan klasemen diperbarui.')"><input type="hidden" name="action" value="delete_training"><input type="hidden" name="id" value="<?= (int)$session['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button></form></div></td></tr><?php endforeach; ?></tbody></table><?php if (!$history): ?><div class="empty-state">Belum ada riwayat latihan.</div><?php endif; ?></div>
    <?php endif; ?>

    <?php if ($page === 'rankings' && $userId):
        $stmt = $pdo->prepare("
            SELECT c.id
            FROM clubs c
            JOIN club_members cm ON cm.club_id = c.id
            WHERE cm.user_id = ? AND cm.status = 'approved' AND c.is_active = 1 AND c.approval_status = 'approved'
        ");
        $stmt->execute([$userId]);
        $approvedClubIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $clubStandingRows = [];
        if ($approvedClubIds) {
            $placeholders = implode(',', array_fill(0, count($approvedClubIds), '?'));
            $stmt = $pdo->prepare("
                SELECT rs.rank_position, rs.speed_mpm, rs.koef,
                       r.id AS race_id, r.name AS race_name, r.status AS race_status,
                       c.name AS club_name,
                       b.nomor_ring, b.nama_burung, b.warna,
                       u.nama_kandang, u.nama_pemilik,
                       cl.arrival_datetime
                FROM race_standings rs
                JOIN race_registrations rr ON rr.id = rs.registration_id
                JOIN races r ON r.id = rr.race_id
                JOIN clubs c ON c.id = r.club_id
                JOIN burung b ON b.id = rr.bird_id
                JOIN users u ON u.id = rr.user_id
                LEFT JOIN clockings cl ON cl.registration_id = rr.id
                WHERE r.club_id IN ($placeholders)
                  AND r.status IN ('released','finished')
                ORDER BY COALESCE(r.actual_release_datetime, r.release_datetime, r.created_at) DESC, rs.rank_position ASC
                LIMIT 80
            ");
            $stmt->execute($approvedClubIds);
            $clubStandingRows = $stmt->fetchAll();
        }
    ?>
        <section class="page-head"><div><p class="eyebrow">Akumulasi</p><h1>Klasemen Kandang</h1></div></section>
        <div class="leaderboard radar-card"><?php foreach ($allBirds as $index => $row): ?><a class="leader-row big" href="index.php?page=bird&id=<?= (int)$row['id'] ?>"><span class="rank"><?= $index + 1 ?></span><?= bird_avatar($row['foto'], $row['nomor_ring']) ?><div><strong><?= h(bird_display_name($row)) ?></strong><span><?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?> / <?= (int)$row['total_finish'] ?> finish / <?= number_format((float)$row['total_meter'] / 1000, 2) ?> km</span></div><b><?= number_format((float)$row['avg_koefisien'], 2) ?> K <small><?= koefisien_stars((float)$row['avg_koefisien']) ?></small></b></a><?php endforeach; if (!$allBirds): ?><div class="empty-state">Belum ada data.</div><?php endif; ?></div>
        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="users-round"></i></div><div><h2>Klasemen Klub Saya</h2><span class="section-note">Hanya lomba dari klub tempat kamu sudah approved.</span></div></div><span class="section-badge"><?= count($clubStandingRows) ?> hasil</span></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Rank</th><th>Lomba</th><th>Klub</th><th>Pemilik</th><th>Ring</th><th>MPM</th><th>Clocking</th></tr></thead>
                <tbody>
                <?php foreach ($clubStandingRows as $row): ?>
                    <tr>
                        <td data-label="Rank"><?= (int)$row['rank_position'] ?></td>
                        <td data-label="Lomba"><a href="index.php?page=race&id=<?= (int)$row['race_id'] ?>"><strong><?= h($row['race_name']) ?></strong></a><br><small><?= h($row['race_status']) ?></small></td>
                        <td data-label="Klub"><?= h($row['club_name']) ?></td>
                        <td data-label="Pemilik"><strong><?= h($row['nama_pemilik'] ?: $row['nama_kandang']) ?></strong><br><small><?= h($row['nama_kandang']) ?></small></td>
                        <td data-label="Ring"><?= h($row['nama_burung'] ?: $row['nomor_ring']) ?><br><small><?= h($row['nomor_ring']) ?> / <?= h($row['warna']) ?></small></td>
                        <td data-label="MPM"><?= $row['speed_mpm'] !== null ? number_format((float)$row['speed_mpm'], 2) : '-' ?></td>
                        <td data-label="Clocking"><?= $row['arrival_datetime'] ? date('H:i:s', strtotime($row['arrival_datetime'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$clubStandingRows): ?><div class="empty-state">Belum ada klasemen klub. Gabung klub dan ikuti lomba berbasis ETS.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'clubs' && $userId):
        $clubs = club_rows($pdo, $userId);
        $adminClubIds = array_map('intval', array_column(array_filter($clubs, fn($club) => ($club['member_role'] ?? '') === 'admin' && ($club['member_status'] ?? '') === 'approved'), 'id'));
        $pendingMembers = [];
        if ($adminClubIds) {
            $placeholders = implode(',', array_fill(0, count($adminClubIds), '?'));
            $stmt = $pdo->prepare("
                SELECT cm.*, c.name AS club_name, u.nama_kandang, u.nama_pemilik
                FROM club_members cm
                JOIN clubs c ON c.id = cm.club_id
                JOIN users u ON u.id = cm.user_id
                WHERE cm.club_id IN ($placeholders) AND cm.status = 'pending'
                ORDER BY cm.joined_at DESC
            ");
            $stmt->execute($adminClubIds);
            $pendingMembers = $stmt->fetchAll();
        }
    ?>
        <section class="page-head"><div><p class="eyebrow">Community layer</p><h1>Klub & Anggota</h1><span>Buat klub, kelola anggota pending, dan jadikan klub sebagai penyelenggara lomba.</span></div><a class="btn btn-primary" href="index.php?page=races"><i data-lucide="flag"></i>Lomba</a></section>
        <form class="form-panel mt-4" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_club">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Nama Klub</label><input required class="form-control" name="name" placeholder="Contoh: Priangan Racing Club"></div>
                <div class="col-md-3"><label class="form-label">Kota</label><input class="form-control" name="city"></div>
                <div class="col-md-3"><label class="form-label">Provinsi</label><input class="form-control" name="province"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i data-lucide="plus"></i>Buat</button></div>
                <div class="col-md-5"><label class="form-label">Logo Klub</label><input class="form-control optimized-image-input" type="file" accept="image/jpeg,image/png,image/webp" name="club_logo"><small class="text-secondary">Otomatis dikompresi ke WebP agar tetap ringan.</small></div>
                <div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description" rows="2"></textarea></div>
            </div>
        </form>
        <?php if ($pendingMembers): ?>
            <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="user-check"></i></div><div><h2>Approval Anggota</h2><span class="section-note">Permintaan bergabung butuh keputusan admin klub.</span></div></div><span class="section-badge"><?= count($pendingMembers) ?> pending</span></div></section>
            <div class="table-wrap responsive-table"><table class="table align-middle"><thead><tr><th>Klub</th><th>Member</th><th>Bergabung</th><th>Aksi</th></tr></thead><tbody><?php foreach ($pendingMembers as $member): ?><tr><td data-label="Klub"><?= h($member['club_name']) ?></td><td data-label="Member"><strong><?= h($member['nama_pemilik'] ?: $member['nama_kandang']) ?></strong><br><small><?= h($member['nama_kandang']) ?></small></td><td data-label="Bergabung"><?= date('d M Y H:i', strtotime($member['joined_at'])) ?></td><td data-label="Aksi" class="text-end"><div class="table-actions"><form method="post"><input type="hidden" name="action" value="update_club_member"><input type="hidden" name="club_id" value="<?= (int)$member['club_id'] ?>"><input type="hidden" name="user_id" value="<?= (int)$member['user_id'] ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-outline-primary"><i data-lucide="check"></i></button></form><form method="post"><input type="hidden" name="action" value="update_club_member"><input type="hidden" name="club_id" value="<?= (int)$member['club_id'] ?>"><input type="hidden" name="user_id" value="<?= (int)$member['user_id'] ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger"><i data-lucide="x"></i></button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="users-round"></i></div><div><h2>Direktori Klub</h2><span class="section-note">Status approved diperlukan untuk role admin/member resmi.</span></div></div></div></section>
        <div class="platform-grid">
            <?php foreach ($clubs as $club): ?>
                <article class="platform-card">
                    <div class="club-card-head">
                        <?= club_logo($club['logo'] ?? null, $club['name']) ?>
                        <div><h3><?= h($club['name']) ?></h3><span><?= h($club['city'] ?: '-') ?><?= $club['province'] ? ', ' . h($club['province']) : '' ?></span></div>
                    </div>
                    <p><?= h($club['description'] ?: 'Belum ada deskripsi klub.') ?></p>
                    <div class="club-meta"><span><?= (int)$club['approved_members'] ?> anggota / <?= (int)$club['total_races'] ?> lomba</span></div>
                    <?php if (($club['member_status'] ?? '') === 'approved'): ?>
                        <span class="badge text-bg-success"><?= h($club['member_role'] === 'admin' ? 'Admin' : 'Member') ?></span>
                    <?php elseif (($club['member_status'] ?? '') === 'pending'): ?>
                        <span class="badge text-bg-warning">Pending</span>
                    <?php else: ?>
                        <form method="post"><input type="hidden" name="action" value="join_club"><input type="hidden" name="club_id" value="<?= (int)$club['id'] ?>"><button class="btn btn-outline-primary"><i data-lucide="user-plus"></i>Gabung</button></form>
                    <?php endif; ?>
                </article>
            <?php endforeach; if (!$clubs): ?><div class="empty-state">Belum ada klub. Buat klub pertama untuk mulai mengelola komunitas.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'ets' && $userId):
        unset($_SESSION['new_ets_token'], $_SESSION['new_ets_secret']);

        $stmt = $pdo->prepare('SELECT * FROM ets_devices WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        $etsDevices = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT rt.*, b.nomor_ring, b.nama_burung, b.warna FROM rfid_tags rt LEFT JOIN burung b ON b.id = rt.bird_id WHERE rt.user_id = ? ORDER BY rt.created_at DESC');
        $stmt->execute([$userId]);
        $rfidTags = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT el.*, ed.device_name, ed.serial_number FROM ets_logs el LEFT JOIN ets_devices ed ON ed.id = el.device_id WHERE el.user_id = ? ORDER BY el.created_at DESC LIMIT 30');
        $stmt->execute([$userId]);
        $etsLogs = $stmt->fetchAll();

        $birdsWithRfid = count(array_filter($allBirds, fn($b) => !empty($b['rfid_tag'])));
    ?>

    <section class="page-head">
        <div>
            <p class="eyebrow">Member ETS</p>
            <h1>ETS &amp; RFID</h1>
            <span>Pasang RFID tag, pantau perangkat terdaftar, dan lihat log scan terbaru.</span>
        </div>
    </section>
    <section class="form-panel mt-4">
            <div class="section-title mb-3"><h2><i data-lucide="tag" style="vertical-align:-3px"></i> Pasang RFID Tag</h2><span class="section-badge">1 tag per burung</span></div>
            <form method="post" id="assignRfidForm">
                <input type="hidden" name="action" value="assign_rfid_tag">
                <label class="form-label">Pilih Burung</label>
                <select required class="form-select" name="bird_id" id="rfidBirdSelect">
                    <option value="">— Pilih burung —</option>
                    <?php foreach ($allBirds as $bird): ?>
                    <option value="<?= (int)$bird['id'] ?>" <?= $bird['rfid_tag'] ? 'data-has-rfid="1"' : '' ?>>
                        <?= h($bird['nomor_ring']) ?><?= $bird['nama_burung'] ? ' – ' . h($bird['nama_burung']) : '' ?><?= $bird['rfid_tag'] ? ' ✓ (RFID: ' . h($bird['rfid_tag']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label mt-3">UID RFID Tag <small style="color:#94a3b8">(masukkan UID tag resmi yang diberikan pengelola)</small></label>
                <div class="rfid-input-row">
                    <input required class="form-control" name="rfid_tag" id="rfidTagInput" placeholder="0F1234ABCD" pattern="[A-Fa-f0-9]{6,20}" style="font-family:monospace;text-transform:uppercase">
                </div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary"><i data-lucide="link"></i> Pasang Tag ke Burung</button>
                </div>
            </form>
    </section>

    <section class="section-header mt-4">
        <div class="section-title">
            <div class="section-title-left">
                <div class="section-icon"><i data-lucide="radio-tower"></i></div>
                <div><h2>Perangkat ETS Terdaftar</h2><span class="section-note"><?= count($etsDevices) ?> perangkat terhubung ke akun ini.</span></div>
            </div>
        </div>
    </section>
    <div class="platform-grid">
        <?php foreach ($etsDevices as $device):
            $statusColor = match($device['status']) {
                'online'      => '#22c55e',
                'offline'     => '#94a3b8',
                'maintenance' => '#f59e0b',
                'revoked'     => '#ef4444',
                default       => '#94a3b8'
            };
            $statusIcon = match($device['status']) {
                'online'  => 'wifi',
                'revoked' => 'ban',
                default   => 'wifi-off'
            };
        ?>
        <article class="platform-card" style="position:relative;overflow:hidden">
            <div style="position:absolute;top:.8rem;right:.8rem;width:10px;height:10px;border-radius:50%;background:<?= $statusColor ?>;<?= $device['status']==='online' ? 'box-shadow:0 0 6px '.$statusColor : '' ?>"></div>
            <i data-lucide="<?= $statusIcon ?>"></i>
            <h3><?= h($device['device_name'] ?: 'Perangkat ETS') ?></h3>
            <p style="font-size:.82rem;margin:.3rem 0">No. seri <?= h($device['serial_number']) ?></p>
            <div class="club-meta" style="font-size:.78rem">
                <span style="color:<?= $statusColor ?>;font-weight:600"><?= strtoupper(h($device['status'])) ?></span>
                <span>Sync: <?= $device['last_sync_at'] ? date('d M H:i', strtotime($device['last_sync_at'])) : 'Belum pernah' ?></span>
                <span>Dibuat: <?= date('d M Y', strtotime($device['created_at'])) ?></span>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if (!$etsDevices): ?>
        <div class="empty-state" style="grid-column:1/-1">
            <i data-lucide="radio-tower"></i>
            <p>Belum ada perangkat ETS terdaftar di akun ini.<br>Hubungi pengelola untuk aktivasi perangkat resmi.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============ RFID TAG BURUNG ============ -->
    <section class="section-header mt-4">
        <div class="section-title">
            <div class="section-title-left">
                <div class="section-icon"><i data-lucide="tag"></i></div>
                <div><h2>RFID Tag Terdaftar</h2><span class="section-note"><?= $birdsWithRfid ?> dari <?= count($allBirds) ?> burung sudah punya RFID tag.</span></div>
            </div>
        </div>
    </section>
    <div class="table-wrap responsive-table">
        <table class="table align-middle">
            <thead><tr><th>UID Tag</th><th>Burung</th><th>Warna</th><th>Status</th><th>Dipasang</th></tr></thead>
            <tbody>
            <?php foreach ($rfidTags as $tag): ?>
            <tr>
                <td data-label="UID"><code style="font-size:.85rem"><?= h($tag['rfid_tag']) ?></code></td>
                <td data-label="Burung"><strong><?= h($tag['nomor_ring'] ?: '—') ?></strong><?= $tag['nama_burung'] ? '<br><small>' . h($tag['nama_burung']) . '</small>' : '' ?></td>
                <td data-label="Warna"><?= h($tag['warna'] ?: '—') ?></td>
                <td data-label="Status">
                    <span class="badge <?= $tag['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= h($tag['status']) ?></span>
                </td>
                <td data-label="Dipasang" style="font-size:.8rem;color:#64748b"><?= date('d M Y', strtotime($tag['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rfidTags): ?>
            <tr><td colspan="5" class="text-center" style="color:#94a3b8;padding:2rem">Belum ada RFID tag. Pasang tag lewat form di atas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============ LOG ETS REAL-TIME ============ -->
    <section class="section-header mt-4">
        <div class="section-title">
            <div class="section-title-left">
                <div class="section-icon"><i data-lucide="activity"></i></div>
                <div><h2>Log ETS Real-time</h2><span class="section-note">30 event terakhir dari semua perangkat Anda.</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.reload()"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </section>
    <div class="table-wrap responsive-table">
        <table class="table align-middle">
            <thead><tr><th>Waktu</th><th>Perangkat</th><th>Event</th><th>RFID</th><th>Status</th><th>Pesan</th></tr></thead>
            <tbody>
            <?php foreach ($etsLogs as $log):
                $badgeClass = match($log['status']) {
                    'accepted' => 'text-bg-success',
                    'rejected' => 'text-bg-danger',
                    default    => 'text-bg-secondary'
                };
            ?>
            <tr>
                <td data-label="Waktu" style="font-size:.8rem;white-space:nowrap"><?= date('d M H:i:s', strtotime($log['created_at'])) ?></td>
                <td data-label="Perangkat" style="font-size:.82rem"><?= h($log['device_name'] ?? '—') ?><br><small style="color:#94a3b8"><?= h($log['serial_number'] ?? '') ?></small></td>
                <td data-label="Event"><code style="font-size:.78rem"><?= h($log['event_type']) ?></code></td>
                <td data-label="RFID"><code style="font-size:.78rem"><?= h($log['rfid_tag'] ?: '—') ?></code></td>
                <td data-label="Status"><span class="badge <?= $badgeClass ?>"><?= h($log['status']) ?></span></td>
                <td data-label="Pesan" style="font-size:.8rem;color:#475569;max-width:220px"><?= h($log['message']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$etsLogs): ?>
            <tr><td colspan="6" class="text-center" style="color:#94a3b8;padding:2rem">Belum ada log ETS. Hubungkan perangkat dan scan tag untuk melihat log.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.getElementById('rfidTagInput')?.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/[^A-F0-9]/g, '');
        this.setSelectionRange(pos, pos);
    });
    </script>

    <?php endif; ?>

    <?php if ($page === 'notifications' && $userId):
        $notifications = user_notifications($pdo, $userId, 40);
    ?>
        <section class="page-head"><div><p class="eyebrow">In-app notification</p><h1>Notifikasi</h1><span>Lomba baru, status pendaftaran, basketing, clocking ETS, dan update klub.</span></div><form method="post"><input type="hidden" name="action" value="mark_notifications_read"><button class="btn btn-primary"><i data-lucide="check-check"></i>Tandai Terbaca</button></form></section>
        <div class="leaderboard mt-4">
            <?php foreach ($notifications as $notif): ?>
                <a class="notification-row <?= $notif['read_at'] ? '' : 'is-unread' ?>" href="<?= h($notif['link'] ?: 'index.php?page=notifications') ?>">
                    <span class="rank"><i data-lucide="<?= $notif['read_at'] ? 'bell' : 'bell-ring' ?>"></i></span>
                    <div><strong><?= h($notif['title']) ?></strong><span><?= h($notif['body'] ?: $notif['type']) ?> / <?= date('d M Y H:i', strtotime($notif['created_at'])) ?></span></div>
                </a>
            <?php endforeach; if (!$notifications): ?><div class="empty-state">Belum ada notifikasi.</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'sponsors' && $userId):
        $canManageSponsors = user_is_club_admin($pdo, $userId);
        $stmt = $pdo->query('SELECT * FROM sponsors WHERE is_active = 1 ORDER BY created_at DESC');
        $sponsors = $stmt->fetchAll();
    ?>
        <section class="page-head"><div><p class="eyebrow">Sponsorship non-judi</p><h1>Sponsor & Exposure</h1><span>Catat sponsor lomba dalam bentuk produk, voucher, atau natura. Platform tidak mengelola uang hadiah.</span></div></section>
        <?php if ($canManageSponsors): ?>
        <form class="form-panel mt-4" method="post">
            <input type="hidden" name="action" value="create_sponsor">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Nama Sponsor</label><input required class="form-control" name="name"></div>
                <div class="col-md-3"><label class="form-label">Kontak</label><input class="form-control" name="contact_name"></div>
                <div class="col-md-3"><label class="form-label">Telepon/WA</label><input class="form-control" name="phone"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i data-lucide="save"></i>Simpan</button></div>
                <div class="col-12"><label class="form-label">Penawaran</label><input class="form-control" name="offer_text" placeholder="Contoh: Voucher pakan untuk juara 1-3"></div>
            </div>
        </form>
        <?php else: ?>
            <section class="setup-banner mt-4">
                <div><strong>Akses terbatas.</strong><span>Form sponsor hanya tersedia untuk admin klub dan super admin. Member dapat melihat sponsor aktif.</span></div>
                <a class="btn btn-light" href="index.php?page=races"><i data-lucide="flag"></i>Lihat Lomba</a>
            </section>
        <?php endif; ?>
        <div class="platform-grid mt-4"><?php foreach ($sponsors as $sponsor): ?><article class="platform-card"><i data-lucide="badge-dollar-sign"></i><h3><?= h($sponsor['name']) ?></h3><p><?= h($sponsor['offer_text'] ?: 'Belum ada detail penawaran.') ?></p><div class="club-meta"><span><?= h($sponsor['contact_name'] ?: '-') ?></span><span><?= h($sponsor['phone'] ?: '-') ?></span></div></article><?php endforeach; if (!$sponsors): ?><div class="empty-state">Belum ada sponsor.</div><?php endif; ?></div>
    <?php endif; ?>

    <?php if ($page === 'super-admin' && $userId):
        if (!is_super_admin()) {
            echo '<div class="empty-state">Halaman ini hanya untuk super admin.</div>';
        } else {
            $superStats = [
                'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'clubs' => (int)$pdo->query('SELECT COUNT(*) FROM clubs')->fetchColumn(),
                'races' => (int)$pdo->query('SELECT COUNT(*) FROM races')->fetchColumn(),
                'clockings' => (int)$pdo->query('SELECT COUNT(*) FROM clockings')->fetchColumn(),
                'ets' => (int)$pdo->query('SELECT COUNT(*) FROM ets_devices')->fetchColumn(),
                'audit' => (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
            ];
            $users = $pdo->query('SELECT id, username, email, nama_kandang, nama_pemilik, role, admin_approval_status, plan, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 40')->fetchAll();
            $pendingClubRequests = $pdo->query('
                SELECT c.*, u.nama_kandang AS requester_loft, u.nama_pemilik, u.email, u.admin_approval_status,
                       (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id AND cm.status = "approved") AS member_count
                FROM clubs c
                LEFT JOIN users u ON u.id = COALESCE(c.admin_id, c.owner_user_id)
                WHERE c.approval_status = "pending"
                ORDER BY c.created_at ASC
                LIMIT 40
            ')->fetchAll();
            $clubsAll = $pdo->query('
                SELECT c.*, u.nama_kandang AS admin_loft,
                       (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id AND cm.status = "approved") AS member_count,
                       (SELECT COUNT(*) FROM races r WHERE r.club_id = c.id) AS race_count
                FROM clubs c
                LEFT JOIN users u ON u.id = c.admin_id
                ORDER BY c.created_at DESC
                LIMIT 30
            ')->fetchAll();
            $etsAll = $pdo->query('
                SELECT ed.*, u.nama_kandang
                FROM ets_devices ed
                LEFT JOIN users u ON u.id = ed.user_id
                ORDER BY COALESCE(ed.last_sync_at, ed.created_at) DESC
                LIMIT 30
            ')->fetchAll();
            $auditRows = $pdo->query('
                SELECT al.*, u.nama_kandang
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                ORDER BY al.created_at DESC
                LIMIT 40
            ')->fetchAll();
            $newOwnerEtsToken = $_SESSION['new_ets_token'] ?? null;
            $newOwnerEtsSecret = $_SESSION['new_ets_secret'] ?? null;
            unset($_SESSION['new_ets_token'], $_SESSION['new_ets_secret']);
            $etsOwnerOptions = $pdo->query('
                SELECT id, username, email, nama_kandang, nama_pemilik
                FROM users
                WHERE COALESCE(is_active, 1) = 1
                ORDER BY nama_kandang ASC, username ASC
                LIMIT 200
            ')->fetchAll();
    ?>
        <section class="page-head">
            <div><p class="eyebrow">Platform control</p><h1>Super Admin</h1><span>Kelola user, klub, ETS, race visibility, dan audit log sesuai PRD TrackPigeon.</span></div>
            <a class="btn btn-outline-primary" href="index.php?page=home"><i data-lucide="globe-2"></i>Publik</a>
        </section>
        <section class="stats-grid">
            <div class="stat"><span>User</span><strong><?= number_format($superStats['users']) ?></strong></div>
            <div class="stat"><span>Klub</span><strong><?= number_format($superStats['clubs']) ?></strong></div>
            <div class="stat"><span>Lomba</span><strong><?= number_format($superStats['races']) ?></strong></div>
            <div class="stat"><span>Clocking</span><strong><?= number_format($superStats['clockings']) ?></strong></div>
            <div class="stat"><span>ETS</span><strong><?= number_format($superStats['ets']) ?></strong></div>
            <div class="stat"><span>Audit</span><strong><?= number_format($superStats['audit']) ?></strong></div>
        </section>

        <section class="form-panel mt-4">
            <div class="section-title mb-3"><h2><i data-lucide="radio-tower" style="vertical-align:-3px"></i> Aktivasi Perangkat ETS</h2><span class="section-badge">Owner only</span></div>
            <?php if ($newOwnerEtsToken): ?>
            <div class="alert alert-success">
                <strong>Token aktivasi baru</strong>
                <div class="api-key-box mt-2"><code><?= h($newOwnerEtsToken) ?></code><button class="btn btn-outline-primary btn-sm" type="button" onclick="navigator.clipboard.writeText('<?= h($newOwnerEtsToken) ?>')"><i data-lucide="copy"></i>Token</button></div>
                <div class="api-key-box mt-2"><code><?= h($newOwnerEtsSecret ?: '-') ?></code><button class="btn btn-outline-primary btn-sm" type="button" onclick="navigator.clipboard.writeText('<?= h($newOwnerEtsSecret ?: '') ?>')"><i data-lucide="copy"></i>Secret</button></div>
            </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="register_ets_device">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Pemilik</label>
                        <select required class="form-select" name="owner_user_id">
                            <option value="">Pilih akun</option>
                            <?php foreach ($etsOwnerOptions as $owner): ?>
                                <?php $ownerName = $owner['nama_kandang'] ?: ($owner['nama_pemilik'] ?: ($owner['username'] ?: $owner['email'])); ?>
                                <option value="<?= (int)$owner['id'] ?>"><?= h($ownerName) ?><?= $owner['email'] ? ' / ' . h($owner['email']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Serial</label><input required class="form-control" name="serial_number" placeholder="ETS-001" pattern="[A-Za-z0-9\-_]{3,40}"></div>
                    <div class="col-md-3"><label class="form-label">Nama Perangkat</label><input required class="form-control" name="device_name" placeholder="Kandang Utama"></div>
                    <div class="col-md-2"><label class="form-label">Versi</label><input class="form-control" name="firmware_version" placeholder="internal-v1"></div>
                    <div class="col-12"><button class="btn btn-primary"><i data-lucide="key-round"></i>Aktivasi ETS</button></div>
                </div>
            </form>
        </section>

        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="shield-question"></i></div><div><h2>Approval Admin & Klub</h2><span class="section-note">Request admin klub dan pembuatan klub wajib disetujui super admin.</span></div></div><span class="section-badge"><?= count($pendingClubRequests) ?> pending</span></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Klub</th><th>Pemohon</th><th>Status</th><th>Anggota</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($pendingClubRequests as $request): ?>
                    <tr>
                        <td data-label="Klub"><div class="club-table-cell"><?= club_logo($request['logo'] ?? null, $request['name'], 'club-logo-sm') ?><div><strong><?= h($request['name']) ?></strong><br><small><?= h($request['city'] ?: '-') ?><?= $request['province'] ? ', ' . h($request['province']) : '' ?></small></div></div></td>
                        <td data-label="Pemohon"><strong><?= h($request['nama_pemilik'] ?: $request['requester_loft'] ?: '-') ?></strong><br><small><?= h($request['email'] ?: '-') ?></small></td>
                        <td data-label="Status"><span class="badge text-bg-warning"><?= h($request['approval_status']) ?></span><br><small><?= h($request['admin_approval_status'] ?: 'none') ?></small></td>
                        <td data-label="Anggota"><?= (int)$request['member_count'] ?></td>
                        <td data-label="Dibuat"><?= date('d M Y', strtotime($request['created_at'])) ?></td>
                        <td data-label="Aksi" class="text-end">
                            <div class="table-actions">
                                <form method="post"><input type="hidden" name="action" value="approve_club_admin_request"><input type="hidden" name="club_id" value="<?= (int)$request['id'] ?>"><button class="btn btn-sm btn-outline-primary"><i data-lucide="check"></i></button></form>
                                <form method="post" onsubmit="return confirm('Tolak request admin/klub ini?')"><input type="hidden" name="action" value="reject_club_admin_request"><input type="hidden" name="club_id" value="<?= (int)$request['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i data-lucide="x"></i></button></form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$pendingClubRequests): ?><div class="empty-state">Tidak ada request admin/klub yang menunggu approval.</div><?php endif; ?>
        </div>

        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="users"></i></div><div><h2>User Platform</h2><span class="section-note">Aktif/nonaktif dan role user.</span></div></div></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>User</th><th>Role</th><th>Approval</th><th>Plan</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td data-label="User"><strong><?= h($row['nama_kandang'] ?: $row['username'] ?: '-') ?></strong><br><small><?= h($row['email'] ?: '-') ?></small></td>
                        <td data-label="Role">
                            <form method="post" class="inline-admin-form">
                                <input type="hidden" name="action" value="update_user_role"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                <select class="form-select form-select-sm" name="role" onchange="this.form.submit()" <?= (int)$row['id'] === $userId ? 'disabled' : '' ?>>
                                    <?php foreach (['member' => 'Member', 'club_admin' => 'Admin Club', 'superadmin' => 'Super Admin'] as $roleValue => $roleLabel): ?>
                                        <option value="<?= h($roleValue) ?>" <?= normalize_role((string)$row['role']) === $roleValue ? 'selected' : '' ?>><?= h($roleLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td data-label="Approval"><span class="badge text-bg-<?= ($row['admin_approval_status'] ?? 'none') === 'pending' ? 'warning' : ((($row['admin_approval_status'] ?? 'none') === 'approved') ? 'success' : 'secondary') ?>"><?= h($row['admin_approval_status'] ?? 'none') ?></span></td>
                        <td data-label="Plan"><?= h($row['plan'] ?: 'free') ?></td>
                        <td data-label="Status"><span class="badge text-bg-<?= (int)$row['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$row['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td data-label="Dibuat"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td data-label="Aksi" class="text-end">
                            <?php if ((int)$row['id'] !== $userId): ?>
                                <form method="post"><input type="hidden" name="action" value="toggle_user_active"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="is_active" value="<?= (int)$row['is_active'] === 1 ? 0 : 1 ?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$row['is_active'] === 1 ? 'Suspend' : 'Aktifkan' ?></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="content-grid mt-4">
            <div>
                <div class="section-title"><h2>Klub</h2><span><?= count($clubsAll) ?> terbaru</span></div>
                <div class="table-wrap responsive-table">
                    <table class="table align-middle"><thead><tr><th>Klub</th><th>Admin</th><th>Data</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    <?php foreach ($clubsAll as $club): ?>
                        <tr><td data-label="Klub"><div class="club-table-cell"><?= club_logo($club['logo'] ?? null, $club['name'], 'club-logo-sm') ?><div><strong><?= h($club['name']) ?></strong><br><small><?= h($club['city'] ?: '-') ?><?= $club['province'] ? ', ' . h($club['province']) : '' ?></small></div></div></td><td data-label="Admin"><?= h($club['admin_loft'] ?: '-') ?></td><td data-label="Data"><?= (int)$club['member_count'] ?> anggota / <?= (int)$club['race_count'] ?> lomba</td><td data-label="Status"><span class="badge text-bg-<?= (int)$club['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$club['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td><td data-label="Aksi" class="text-end"><form method="post"><input type="hidden" name="action" value="toggle_club_active"><input type="hidden" name="club_id" value="<?= (int)$club['id'] ?>"><input type="hidden" name="is_active" value="<?= (int)$club['is_active'] === 1 ? 0 : 1 ?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$club['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            </div>
            <div>
                <div class="section-title"><h2>ETS</h2><span><?= count($etsAll) ?> perangkat</span></div>
                <div class="leaderboard">
                    <?php foreach ($etsAll as $device): ?>
                        <div class="leader-row"><span class="rank"><i data-lucide="scan-line"></i></span><div><strong><?= h($device['device_name'] ?: $device['serial_number']) ?></strong><span><?= h($device['nama_kandang'] ?: '-') ?> / <?= h($device['status'] ?? '-') ?> / <?= h($device['last_ip'] ?? '-') ?></span></div><b><?= !empty($device['last_sync_at']) ? date('H:i', strtotime($device['last_sync_at'])) : '-' ?></b></div>
                    <?php endforeach; if (!$etsAll): ?><div class="empty-state">Belum ada ETS terdaftar.</div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="section-header"><div class="section-title"><div class="section-title-left"><div class="section-icon"><i data-lucide="shield-check"></i></div><div><h2>Audit Log</h2><span class="section-note">Jejak aksi penting bersifat append-only di aplikasi.</span></div></div></div></section>
        <div class="table-wrap responsive-table">
            <table class="table align-middle">
                <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Entity</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($auditRows as $row): ?>
                    <tr><td data-label="Waktu"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td><td data-label="User"><?= h($row['nama_kandang'] ?: '-') ?></td><td data-label="Aksi"><strong><?= h($row['action']) ?></strong></td><td data-label="Entity"><?= h(($row['entity_type'] ?: '-') . ($row['entity_id'] ? '#' . $row['entity_id'] : '')) ?></td><td data-label="IP"><?= h($row['ip_address'] ?: '-') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php } endif; ?>

    <?php if ($page === 'settings' && $user): ?>
        <section class="page-head"><div><p class="eyebrow">Pengaturan wajib</p><h1>Profil Kandang</h1></div></section>
        <form class="form-panel narrow" method="post">
            <input type="hidden" name="action" value="save_settings">
            <label class="form-label">Nama Kandang/Club</label><input required class="form-control" name="nama_kandang" value="<?= h($user['nama_kandang']) ?>">
            <label class="form-label mt-3">Nama Pemilik</label><input required class="form-control" name="nama_pemilik" value="<?= h($user['nama_pemilik']) ?>">
            <label class="form-label mt-3">Latitude Kandang</label><input required class="form-control" id="homeLat" type="number" step="0.00000001" name="lat_kandang" value="<?= h($user['lat_kandang']) ?>">
            <label class="form-label mt-3">Longitude Kandang</label><input required class="form-control" id="homeLon" type="number" step="0.00000001" name="lon_kandang" value="<?= h($user['lon_kandang']) ?>">
            <div class="map-panel"><div class="map-title"><div><strong>Pilih Lokasi Kandang di Peta</strong><span>Klik peta untuk mengisi titik finish.</span></div><button class="btn btn-outline-secondary btn-sm locate-btn" type="button" data-map-target="homeMap"><i data-lucide="crosshair"></i>Lokasi Saya</button></div><div id="homeMap" class="map-box" data-lat="<?= h($defaultMapLat) ?>" data-lon="<?= h($defaultMapLon) ?>" data-input-lat="homeLat" data-input-lon="homeLon"></div></div>
            <div class="form-actions"><button class="btn btn-primary"><i data-lucide="save"></i>Simpan Kandang</button></div>
        </form>
    <?php endif; ?>
</main>

<?php if ($user): ?>
<nav class="bottom-nav" aria-label="Navigasi utama mobile">
    <?php
        $bottomItems = ['home' => ['Publik', 'globe-2'], 'dashboard' => ['Dasbor', 'layout-dashboard']];
        if (is_member()) {
            $bottomItems += ['birds' => ['Burung', 'bird'], 'join-club' => ['Klub', 'users-round'], 'available-races' => ['Lomba', 'flag'], 'clocking' => ['Clock', 'crosshair'], 'new-training' => ['Latihan', 'timer'], 'rankings' => ['Klasemen', 'medal'], 'ets' => ['ETS', 'scan-line']];
        } elseif (is_super_admin()) {
            $bottomItems += ['races' => ['Lomba', 'flag'], 'clubs' => ['Klub', 'users-round'], 'sponsors' => ['Sponsor', 'badge-dollar-sign'], 'super-admin' => ['Admin', 'shield-check']];
        } else {
            $bottomItems += ['races' => ['Lomba', 'flag'], 'clubs' => ['Klub', 'users-round'], 'sponsors' => ['Sponsor', 'badge-dollar-sign']];
        }
        $bottomItems['notifications'] = ['Notif', 'bell'];
        $bottomItems['settings'] = ['Kandang', 'settings'];
    ?>
    <?php foreach ($bottomItems as $key => [$label, $icon]): ?>
        <a class="bottom-nav-item <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
            <i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span><?php if ($key === 'notifications' && $unreadNotificationCount > 0): ?><span class="nav-badge"><?= $unreadNotificationCount ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php else: ?>
<nav class="bottom-nav public-bottom-nav" aria-label="Navigasi publik mobile">
    <a class="bottom-nav-item <?= $page === 'home' ? 'active' : '' ?>" href="index.php?page=home">
        <i data-lucide="trophy"></i><span>Publik</span>
    </a>
    <a class="bottom-nav-item <?= $page === 'klasemen' ? 'active' : '' ?>" href="index.php?page=klasemen">
        <i data-lucide="medal"></i><span>Klasemen</span>
    </a>
    <a class="bottom-nav-item <?= $page === 'login' ? 'active' : '' ?>" href="index.php?page=login">
        <i data-lucide="log-in"></i><span>Login</span>
    </a>
    <a class="bottom-nav-item <?= $page === 'register' ? 'active' : '' ?>" href="index.php?page=register">
        <i data-lucide="user-plus"></i><span>Daftar</span>
    </a>
</nav>
<?php endif; ?>

<div class="modal fade" id="birdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_bird" id="birdAction"><input type="hidden" name="id" id="birdId">
            <div class="modal-header"><h5 class="modal-title">Data Merpati</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Nomor Ring</label><input required class="form-control" name="nomor_ring" id="birdRing">
                <label class="form-label mt-3">RFID Tag</label><input class="form-control" name="rfid_tag" id="birdRfid" placeholder="Opsional untuk ring chip ETS">
                <label class="form-label mt-3">Nama Burung</label><input class="form-control" name="nama_burung" id="birdName" placeholder="Opsional, contoh: Arjuna">
                <label class="form-label mt-3">Warna</label><input required class="form-control" name="warna" id="birdColor">
                <label class="form-label mt-3">Jenis Kelamin</label><select class="form-select" name="jenis_kelamin" id="birdGender"><option value="">Tidak diisi</option><option>Jantan</option><option>Betina</option></select>
                <div class="row g-3">
                    <div class="col-sm-6"><label class="form-label mt-3">Tanggal Lahir</label><input class="form-control" type="date" name="tanggal_lahir" id="birdBirthDate"></div>
                    <div class="col-sm-6"><label class="form-label mt-3">Berat (gram)</label><input class="form-control" type="number" step="0.01" min="0" name="berat_gram" id="birdWeight"></div>
                </div>
                <label class="form-label mt-3">Bloodline</label><input class="form-control" name="bloodline" id="birdBloodline" placeholder="Opsional">
                <div class="row g-3">
                    <div class="col-sm-6"><label class="form-label mt-3">Induk Jantan</label><input class="form-control" name="induk_jantan" id="birdSire"></div>
                    <div class="col-sm-6"><label class="form-label mt-3">Induk Betina</label><input class="form-control" name="induk_betina" id="birdDam"></div>
                </div>
                <label class="form-label mt-3">Status</label>
                <select class="form-select" name="status" id="birdStatus">
                    <option value="aktif">Aktif (Player)</option>
                    <option value="hilang">Hilang</option>
                    <option value="pensiun">Pensiun</option>
                    <option value="terjual">Terjual</option>
                </select>
                <label class="form-label mt-3">Catatan</label><textarea class="form-control" name="catatan" id="birdCatatan" rows="2" placeholder="Opsional"></textarea>
                <label class="form-label mt-3">Foto</label><input class="form-control" type="file" accept="image/jpeg,image/png,image/webp" name="foto" id="birdPhoto">
                <div class="upload-status" id="uploadStatus" hidden>
                    <div class="upload-status-head"><strong id="uploadStatusText">Mengompresi...</strong><button class="btn btn-sm btn-outline-secondary" type="button" id="cancelCompression">Batal</button></div>
                    <div class="upload-progress"><span id="uploadProgressBar"></span></div>
                    <small id="uploadSavingInfo"></small>
                </div>
                <img class="photo-preview" id="photoPreview" alt="">
            </div>
            <div class="modal-footer"><button class="btn btn-primary"><i data-lucide="save"></i>Simpan</button></div>
        </form>
    </div>
</div>

<div class="photo-viewer" id="photoViewer" aria-hidden="true">
    <div class="photo-viewer-panel">
        <div class="photo-viewer-toolbar">
            <strong id="photoViewerTitle">Foto Merpati</strong>
            <div>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom="out"><i data-lucide="zoom-out"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom="reset"><i data-lucide="scan"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom="in"><i data-lucide="zoom-in"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-close-viewer><i data-lucide="x"></i></button>
            </div>
        </div>
        <div class="photo-viewer-stage">
            <img id="photoViewerImage" alt="">
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/compressorjs@1.2.1/dist/compressor.min.js"></script>
<?php if (GOOGLE_CLIENT_ID !== ''): ?><script src="https://accounts.google.com/gsi/client" async defer></script><?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
