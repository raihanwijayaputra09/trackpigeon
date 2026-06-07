<?php
declare(strict_types=1);

require __DIR__ . '/_redirect.php';

$pdo = db();
$user = current_user($pdo);
if (!$user) {
    dashboard_redirect('login');
}

$role = current_user_role();
if ($role === 'club_admin' || $role === 'superadmin') {
    dashboard_redirect('dashboard', ['area' => 'club-admin']);
}

dashboard_redirect('dashboard', ['area' => 'member']);

