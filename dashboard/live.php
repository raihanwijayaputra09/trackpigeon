<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$pdo = db();
$userId = require_login();
$user = current_user($pdo);
if (!$user) redirect('../index.php?page=login');

$stmt = $pdo->prepare("
    SELECT l.*,
           (SELECT COUNT(*) FROM detail_latihan dl WHERE dl.latihan_id = l.id) AS total_burung,
           (SELECT COUNT(*) FROM detail_latihan dl WHERE dl.latihan_id = l.id AND dl.status_sampai = '1') AS burung_sampai
    FROM latihan l
    WHERE l.user_id = ? AND l.status = 'berlangsung'
    ORDER BY l.created_at DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$latihan = $stmt->fetch();

$rows = [];
if ($latihan) {
    $stmt = $pdo->prepare("
        SELECT b.nomor_ring, dl.status_sampai, dl.kecepatan_mpm, dl.jam_tiba, dl.waktu_tempuh_menit, dl.metode_checkin
        FROM detail_latihan dl
        JOIN burung b ON b.id = dl.burung_id
        WHERE dl.latihan_id = ?
        ORDER BY dl.status_sampai DESC, dl.kecepatan_mpm DESC
    ");
    $stmt->execute([(int)$latihan['id']]);
    $rows = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Race - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Nunito:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/styles.css" rel="stylesheet">
    <style>
        body { padding: var(--space-6); }
        .standalone-live { width: min(1180px, 100%); margin: 0 auto; }
        .live-page-head { margin-bottom: var(--space-5); }
        .live-page-title { background: var(--bg-surface); border: var(--border-thin); border-radius: var(--radius-lg); padding: var(--space-6); box-shadow: var(--shadow-sm); }
        .live-page-title h1 { margin: 0; font-size: var(--text-3xl); }
        .live-page-title p { margin: var(--space-1) 0 0; color: var(--gray-600); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-3); margin-top: var(--space-5); }
        .info-card { background: var(--gray-50); border: var(--border-thin); border-radius: var(--radius-md); padding: var(--space-4); }
        .info-card .label { color: var(--gray-500); font-size: var(--text-xs); font-weight: var(--font-semibold); text-transform: uppercase; }
        .info-card .value { margin-top: 3px; color: var(--gray-900); font-size: var(--text-xl); font-weight: var(--font-bold); }
        .live-refresh { display: flex; align-items: center; gap: var(--space-2); margin-top: var(--space-4); color: var(--gray-500); font-size: var(--text-sm); }
        .pulse { width: 8px; height: 8px; background: var(--success); border-radius: var(--radius-full); animation: live-pulse 1s infinite; }
        @keyframes live-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        .live-board { overflow-x: auto; background: var(--bg-surface); border: var(--border-thin); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
        .live-board table { width: 100%; min-width: 720px; border-collapse: collapse; font-size: var(--text-sm); }
        .live-board th { padding: 12px 14px; color: var(--gray-500); background: var(--gray-50); border-bottom: var(--border-thin); font-size: var(--text-xs); font-weight: var(--font-semibold); text-align: left; text-transform: uppercase; }
        .live-board td { padding: 14px; border-bottom: var(--border-thin); color: var(--gray-800); }
        .live-board tr:last-child td { border-bottom: 0; }
        .status-dalam { color: var(--warning-text); font-weight: var(--font-semibold); }
        .status-sampai { color: var(--success-text); font-weight: var(--font-bold); }
        .podium-1, .podium-2, .podium-3 { color: var(--warning-text); font-weight: var(--font-bold); }
        .btn-back { margin-bottom: var(--space-4); }
        @media (max-width: 480px) {
            body { padding: var(--space-3); }
            .live-page-title { padding: var(--space-4); }
            .live-page-title h1 { font-size: var(--text-2xl); }
        }
    </style>
</head>
<body>
<main class="standalone-live">
    <a href="../index.php?page=dashboard" class="btn btn-outline-secondary btn-back">Kembali ke Dashboard</a>

    <?php if ($latihan): ?>
        <?php
        $start = new DateTime($latihan['jam_lepas']);
        $now = new DateTime();
        $diff = $start->diff($now);
        ?>
        <section class="live-page-head">
            <div class="live-page-title">
                <p class="eyebrow">Live Race</p>
                <h1><?= h($latihan['nama_sesi'] ?: $latihan['nama_titik_lepas'] ?: 'Latihan Aktif') ?></h1>
                <p>Auto-refresh setiap 3 detik.</p>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="label">Jarak</div>
                        <div class="value"><?= number_format((float)$latihan['jarak_meter'], 0, ',', '.') ?> m</div>
                    </div>
                    <div class="info-card">
                        <div class="label">Lepas</div>
                        <div class="value"><?= date('H:i', strtotime($latihan['jam_lepas'])) ?></div>
                    </div>
                    <div class="info-card">
                        <div class="label">Tiba</div>
                        <div class="value"><?= (int)$latihan['burung_sampai'] ?>/<?= (int)$latihan['total_burung'] ?></div>
                    </div>
                    <div class="info-card">
                        <div class="label">Durasi</div>
                        <div class="value"><?= $diff->h ?>j <?= $diff->i ?>m</div>
                    </div>
                </div>
                <div class="live-refresh"><span class="pulse"></span><span>Menunggu data check-in terbaru</span></div>
            </div>
        </section>

        <section class="live-board" aria-label="Leaderboard live race">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ring</th>
                        <th>Status</th>
                        <th>MPM</th>
                        <th>Waktu Tiba</th>
                        <th>Waktu Tempuh</th>
                        <th>Metode</th>
                    </tr>
                </thead>
                <tbody id="leaderboard-body">
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $arrived = $row['status_sampai'] === '1';
                        $podium = $arrived && $index < 3 ? 'podium-' . ($index + 1) : '';
                        ?>
                        <tr>
                            <td class="<?= $podium ?>"><?= $arrived ? $index + 1 : '-' ?></td>
                            <td><strong><?= h($row['nomor_ring']) ?></strong></td>
                            <td class="<?= $arrived ? 'status-sampai' : 'status-dalam' ?>"><?= $arrived ? 'Sampai' : 'Terbang' ?></td>
                            <td><?= $row['kecepatan_mpm'] ? number_format((float)$row['kecepatan_mpm'], 0) : '-' ?></td>
                            <td><?= $row['jam_tiba'] ? date('H:i:s', strtotime($row['jam_tiba'])) : '-' ?></td>
                            <td><?= $row['waktu_tempuh_menit'] ? number_format((float)$row['waktu_tempuh_menit'], 0) . ' mnt' : '-' ?></td>
                            <td><?= h($row['metode_checkin'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php else: ?>
        <section class="live-page-title">
            <p class="eyebrow">Live Race</p>
            <h1>Tidak Ada Latihan Aktif</h1>
            <p>Buat sesi latihan dulu untuk mulai memantau kedatangan merpati.</p>
            <a href="../index.php?page=new-training" class="btn btn-primary">Buat Latihan</a>
        </section>
    <?php endif; ?>
</main>

<script>
let lastCount = 0;

async function refreshData() {
    try {
        const response = await fetch('../api/get-live-data.php');
        const data = await response.json();

        if (data.ok && data.burung) {
            const tbody = document.getElementById('leaderboard-body');
            if (!tbody) return;

            const burung = data.burung;
            const newCount = burung.filter((bird) => bird.status_sampai === '1').length;

            tbody.innerHTML = burung.map((bird, index) => {
                const arrived = bird.status_sampai === '1';
                const position = arrived ? index + 1 : '-';
                const podium = arrived && index < 3 ? `podium-${index + 1}` : '';
                const statusClass = arrived ? 'status-sampai' : 'status-dalam';
                const statusText = arrived ? 'Sampai' : 'Terbang';
                const mpm = bird.kecepatan_mpm ? Math.round(bird.kecepatan_mpm) : '-';
                const jamTiba = bird.jam_tiba ? bird.jam_tiba.substring(11, 19) : '-';
                const waktuTempuh = bird.waktu_tempuh_menit ? Math.round(bird.waktu_tempuh_menit) + ' mnt' : '-';
                const metode = bird.metode_checkin === 'rfid' ? 'RFID' : 'Manual';

                return `
                    <tr>
                        <td class="${podium}">${position}</td>
                        <td><strong>${bird.nomor_ring}</strong></td>
                        <td class="${statusClass}">${statusText}</td>
                        <td>${mpm}</td>
                        <td>${jamTiba}</td>
                        <td>${waktuTempuh}</td>
                        <td>${metode}</td>
                    </tr>
                `;
            }).join('');

            lastCount = newCount;
        }
    } catch (error) {
        console.log('Refresh error:', error);
    }
}

setInterval(refreshData, 3000);
refreshData();
</script>
</body>
</html>
