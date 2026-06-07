<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$safeUser = static function (?array $user): ?array {
    if (!$user) {
        return null;
    }
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'] ?? null,
        'email' => $user['email'] ?? null,
        'nama_kandang' => $user['nama_kandang'] ?? null,
        'nama_pemilik' => $user['nama_pemilik'] ?? null,
        'role' => normalize_role($user['role'] ?? 'member'),
        'plan' => $user['plan'] ?? 'free',
        'is_active' => (int)($user['is_active'] ?? 1),
    ];
};

if ($method === 'GET') {
    api_out([
        'ok' => true,
        'authenticated' => current_user_id() !== null && current_user($pdo) !== null,
        'user' => $safeUser(current_user($pdo)),
    ]);
}

api_require_method('POST');

$action = api_string('action', 'login');
if ($action === 'logout') {
    session_destroy();
    api_out(['ok' => true, 'message' => 'Logout berhasil.']);
}

$identity = api_string('username', api_string('email'));
$password = (string)api_value('password', '');
if ($identity === '' || $password === '') {
    api_out(['ok' => false, 'message' => 'Username/email dan password wajib diisi.'], 422);
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$identity, $identity]);
$user = $stmt->fetch();
if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
    api_out(['ok' => false, 'message' => 'Username atau password salah.'], 401);
}
if ((int)($user['is_active'] ?? 1) !== 1) {
    api_out(['ok' => false, 'message' => 'Akun sedang nonaktif.'], 403);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_role'] = normalize_role($user['role'] ?? 'member');
log_audit($pdo, (int)$user['id'], 'auth.login.api', 'user', (int)$user['id']);

api_out([
    'ok' => true,
    'message' => 'Login berhasil.',
    'user' => $safeUser($user),
]);

