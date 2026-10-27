<?php

declare(strict_types=1);

/**
 * Package Manager rescue script.
 *
 * Use this only when an installed update package stops the site from loading, so the
 * normal Package Manager rollback page cannot be reached. It restores the files of the
 * most recent package install from its on-disk backup without loading Laravel.
 *
 * Enable it by setting PACKAGE_RESCUE_KEY=<long random value> in .env, then open:
 *   https://isoadmin.co.za/public/iso-package-rescue.php?key=<that value>
 *
 * Database migrations are not reverted here. Once the site loads again, open
 * Admin > Package Manager and press "Roll Back" on the rescued install to finish.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');

$appRoot = null;
foreach ([dirname(__DIR__), __DIR__, dirname(__DIR__, 2)] as $candidate) {
    $candidate = realpath($candidate) ?: $candidate;
    if (is_file($candidate . '/bootstrap/app.php') && is_dir($candidate . '/storage')) {
        $appRoot = $candidate;
        break;
    }
}

function rescueExit(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message . "\n");
}

if ($appRoot === null) {
    rescueExit(500, 'Application root not found.');
}

function envValue(string $file, string $key): string
{
    if (!is_file($file)) {
        return '';
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)\s*$/', $line, $match)) {
            return trim($match[1], " \t\"'");
        }
    }

    return '';
}

$rescueKey = envValue($appRoot . '/.env', 'PACKAGE_RESCUE_KEY');
if ($rescueKey === '' || strlen($rescueKey) < 16) {
    rescueExit(403, 'Rescue is disabled. Set PACKAGE_RESCUE_KEY (at least 16 characters) in .env to enable it.');
}

$key = (string) ($_POST['key'] ?? $_GET['key'] ?? '');
if (!hash_equals($rescueKey, $key)) {
    rescueExit(403, 'Forbidden');
}

$installsDirectory = $appRoot . '/storage/app/package-manager/installs';
$journals = [];
foreach (glob($installsDirectory . '/*/journal.json') ?: [] as $path) {
    $journal = json_decode((string) file_get_contents($path), true);
    if (is_array($journal)) {
        $journal['_path'] = $path;
        $journals[] = $journal;
    }
}
usort($journals, fn ($a, $b) => ($b['install_id'] ?? 0) <=> ($a['install_id'] ?? 0));

$active = array_values(array_filter($journals, fn ($journal) => in_array($journal['status'] ?? '', ['installed', 'installing'], true)));
$latest = $active[0] ?? null;
$messages = [];

function restoreFile(string $source, string $target): bool
{
    $directory = dirname($target);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    $temporary = $directory . '/.pkg-tmp-' . bin2hex(random_bytes(6));
    if (!@copy($source, $temporary)) {
        return false;
    }
    if (!@rename($temporary, $target)) {
        @unlink($temporary);
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }

    return true;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $latest !== null && ($_POST['install'] ?? '') === ($latest['install_key'] ?? null)) {
    $journal = $latest;
    $installDirectory = dirname($journal['_path']);
    unset($journal['_path']);

    foreach (array_reverse(array_keys($journal['operations'] ?? [])) as $index) {
        $operation = $journal['operations'][$index];
        if (!empty($operation['reverted'])) {
            continue;
        }

        $target = $appRoot . '/' . $operation['path'];
        if ($operation['type'] === 'create') {
            $ok = !is_file($target) || @unlink($target);
        } else {
            $ok = restoreFile($installDirectory . '/' . $operation['backup'], $target);
        }

        if ($ok) {
            $journal['operations'][$index]['reverted'] = true;
        } else {
            $messages[] = 'Could not restore ' . $operation['path'];
        }
    }

    foreach (array_reverse($journal['created_directories'] ?? []) as $directory) {
        $path = $appRoot . '/' . $directory;
        if (is_dir($path) && count(scandir($path) ?: []) === 2) {
            @rmdir($path);
        }
    }

    foreach (glob($appRoot . '/bootstrap/cache/{config,routes-v7,events}.php', GLOB_BRACE) ?: [] as $cache) {
        @unlink($cache);
    }
    foreach (glob($appRoot . '/storage/framework/views/*.php') ?: [] as $view) {
        @unlink($view);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    $journal['status'] = 'rescued';
    $journal['rescued_at'] = date('c');
    file_put_contents($installDirectory . '/journal.json', json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    $messages[] = 'Files of v' . $journal['version'] . ' were rolled back to v' . $journal['previous_version'] . '. Open Package Manager and press Roll Back on this install to revert its database changes.';
    $latest = null;
}

header('Content-Type: text/html; charset=UTF-8');
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Package Rescue</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:30px auto;padding:0 16px;background:#071118;color:#e8f3ee}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #22343c;text-align:left}button{padding:10px 14px;border-radius:10px;border:0;background:#e5484d;color:#fff;font-weight:700;cursor:pointer}.msg{padding:12px;border-radius:10px;background:#12333a;margin:10px 0}</style>
</head>
<body>
<h1>📦 Package Rescue</h1>
<p>Restores the files of the most recent package install. Use the normal Package Manager page whenever the site still loads.</p>
<?php foreach ($messages as $message): ?><div class="msg"><?= $e($message) ?></div><?php endforeach; ?>
<?php if ($latest !== null): ?>
    <form method="post">
        <input type="hidden" name="key" value="<?= $e($key) ?>">
        <input type="hidden" name="install" value="<?= $e($latest['install_key']) ?>">
        <p><strong>Latest install:</strong> v<?= $e($latest['version']) ?> (from v<?= $e($latest['previous_version']) ?>) · <?= $e($latest['status']) ?> · <?= $e($latest['started_at'] ?? '') ?></p>
        <button type="submit" onclick="return confirm('Restore the files from before v<?= $e($latest['version']) ?>?')">↩️ Restore files to v<?= $e($latest['previous_version']) ?></button>
    </form>
<?php else: ?>
    <p>No installed package is waiting to be rescued.</p>
<?php endif; ?>
<h2>Install journals</h2>
<table>
    <tr><th>Version</th><th>From</th><th>Status</th><th>Started</th></tr>
    <?php foreach ($journals as $journal): ?>
        <tr><td>v<?= $e($journal['version'] ?? '') ?></td><td>v<?= $e($journal['previous_version'] ?? '') ?></td><td><?= $e($journal['status'] ?? '') ?></td><td><?= $e($journal['started_at'] ?? '') ?></td></tr>
    <?php endforeach; ?>
</table>
</body>
</html>
