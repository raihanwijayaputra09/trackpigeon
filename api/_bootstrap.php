<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function api_payload(): array
{
    static $payload = null;
    if ($payload !== null) {
        return $payload;
    }

    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    $payload = is_array($json) ? $json : [];
    foreach ($_POST as $key => $value) {
        $payload[$key] = $value;
    }
    foreach ($_GET as $key => $value) {
        $payload[$key] = $value;
    }
    return $payload;
}

function api_value(string $key, mixed $default = null): mixed
{
    $payload = api_payload();
    return $payload[$key] ?? $default;
}

function api_int(string $key, int $default = 0): int
{
    return (int)api_value($key, $default);
}

function api_string(string $key, string $default = ''): string
{
    return trim((string)api_value($key, $default));
}

function api_require_method(string $method): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
        api_out(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
    }
}

function api_require_user(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        api_out(['ok' => false, 'message' => 'Login diperlukan.'], 401);
    }
    return $user;
}

function api_can_manage_race(PDO $pdo, int $raceId, int $userId): array
{
    $race = user_can_manage_race($pdo, $raceId, $userId);
    if (!$race) {
        api_out(['ok' => false, 'message' => 'Lomba tidak ditemukan atau akses admin tidak tersedia.'], 404);
    }
    return $race;
}

function api_registration_for_clock(PDO $pdo, int $registrationId, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT rr.*, r.name, r.status AS race_status, r.actual_release_datetime, b.nomor_ring
        FROM race_registrations rr
        JOIN races r ON r.id = rr.race_id
        JOIN burung b ON b.id = rr.bird_id
        WHERE rr.id = ? AND rr.user_id = ?
    ");
    $stmt->execute([$registrationId, $userId]);
    $registration = $stmt->fetch();
    if (!$registration) {
        api_out(['ok' => false, 'message' => 'Registrasi lomba tidak ditemukan.'], 404);
    }
    return $registration;
}
