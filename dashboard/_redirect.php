<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function dashboard_redirect(string $page, array $extra = []): never
{
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $root = $scriptDir;
    $dashboardPos = strpos($root, '/dashboard');
    if ($dashboardPos !== false) {
        $root = substr($root, 0, $dashboardPos);
    }
    $root = $root === '/' ? '' : rtrim($root, '/');
    $query = array_merge($_GET, $extra, ['page' => $page]);
    unset($query['page_alias']);
    header('Location: ' . $root . '/index.php?' . http_build_query($query));
    exit;
}
