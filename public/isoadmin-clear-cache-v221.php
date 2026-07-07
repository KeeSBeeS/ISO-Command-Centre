<?php
/*
 * ISO Admin v2.2.1 route-cache repair.
 * Upload this file to /public and open:
 * /isoadmin-clear-cache-v221.php?key=iso-v221-route-repair
 * Delete this file after use.
 */
$key = $_GET['key'] ?? '';
if (!hash_equals('iso-v221-route-repair', $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$base = dirname(__DIR__);
$targets = [
    $base . '/bootstrap/cache/routes.php',
    $base . '/bootstrap/cache/routes-v7.php',
    $base . '/bootstrap/cache/config.php',
    $base . '/bootstrap/cache/events.php',
    $base . '/bootstrap/cache/packages.php',
    $base . '/bootstrap/cache/services.php',
    $base . '/bootstrap/cache/compiled.php',
];

$deleted = [];
$skipped = [];
foreach ($targets as $target) {
    if (is_file($target)) {
        if (@unlink($target)) {
            $deleted[] = basename($target);
        } else {
            $skipped[] = basename($target) . ' (not writable)';
        }
    }
}

$viewPaths = glob($base . '/storage/framework/views/*.php') ?: [];
$viewDeleted = 0;
foreach ($viewPaths as $viewPath) {
    if (is_file($viewPath) && @unlink($viewPath)) {
        $viewDeleted++;
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "ISO Admin v2.2.1 cache repair completed.\n";
echo "Deleted cache files: " . ($deleted ? implode(', ', $deleted) : 'none found') . "\n";
if ($skipped) {
    echo "Skipped: " . implode(', ', $skipped) . "\n";
}
echo "Compiled views deleted: {$viewDeleted}\n";
echo "Now open /updates/v2-2-1 while logged in as Director. Delete this repair file after use.\n";
