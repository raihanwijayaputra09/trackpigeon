<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');

$pdo = db();
$user = api_require_user($pdo);
$userId = (int)$user['id'];
$clubId = api_int('club_id');
$memberUserId = api_int('user_id', api_int('member_user_id'));
$status = api_string('status', 'approved');

if ($clubId <= 0 || $memberUserId <= 0) {
    api_out(['ok' => false, 'message' => 'club_id dan user_id wajib dikirim.'], 422);
}
if (!in_array($status, ['approved', 'rejected', 'banned'], true)) {
    api_out(['ok' => false, 'message' => 'Status anggota tidak valid.'], 422);
}

if (!user_is_club_admin($pdo, $userId, $clubId)) {
    api_out(['ok' => false, 'message' => 'Hanya admin klub yang bisa mengubah anggota.'], 403);
}

if ($status === 'rejected') {
    $stmt = $pdo->prepare('DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND role <> "admin"');
    $stmt->execute([$clubId, $memberUserId]);
} else {
    $stmt = $pdo->prepare('UPDATE club_members SET status = ? WHERE club_id = ? AND user_id = ? AND role <> "admin"');
    $stmt->execute([$status, $clubId, $memberUserId]);
}

notify_user($pdo, $memberUserId, 'club_membership', 'Status klub diperbarui', 'Status keanggotaan klub kamu: ' . $status, 'index.php?page=clubs');
log_audit($pdo, $userId, 'club.member.update.api', 'club', $clubId, ['member_user_id' => $memberUserId, 'status' => $status]);

api_out([
    'ok' => true,
    'message' => 'Status anggota diperbarui.',
    'club_id' => $clubId,
    'user_id' => $memberUserId,
    'status' => $status,
]);
