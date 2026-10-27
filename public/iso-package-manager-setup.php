<?php

declare(strict_types=1);

/**
 * One-time setup for the v2.9.0 Package Manager.
 *
 * Adds the Package Manager routes and menu link to the live site without replacing
 * routes/web.php or the layout, so newer live routes and menu items are kept.
 * Safe to run more than once. Delete this file after it reports success.
 */

const ISO_SETUP_KEY = 'iso-package-manager-setup-2026';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!hash_equals(ISO_SETUP_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Forbidden\n");
}

function fail(string $message): never
{
    http_response_code(500);
    exit("FAILED: {$message}\n");
}

function backupFile(string $path): string
{
    $backup = $path . '.bak-' . date('Ymd-His');
    if (!copy($path, $backup)) {
        fail("Could not create backup for {$path}");
    }

    return $backup;
}

function writeFileAtomically(string $path, string $contents): void
{
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        fail("Could not write temporary file {$temporary}");
    }

    @chmod($temporary, fileperms($path) & 0777);

    if (!rename($temporary, $path)) {
        @unlink($temporary);
        fail("Could not replace {$path}");
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
}

$appRoot = null;
foreach ([dirname(__DIR__), dirname(__DIR__, 2), __DIR__] as $candidate) {
    $candidate = realpath($candidate) ?: $candidate;
    if (is_file($candidate . '/bootstrap/app.php') && is_file($candidate . '/routes/web.php')) {
        $appRoot = $candidate;
        break;
    }
}

if ($appRoot === null) {
    fail('Laravel application root could not be identified from ' . __DIR__);
}

foreach ([
    'routes/packages.php',
    'config/packages.php',
    'app/Http/Controllers/PackageManagerController.php',
    'app/Services/PackageManager.php',
    'app/Services/PackageArchive.php',
    'resources/views/packages/index.blade.php',
] as $required) {
    if (!is_file($appRoot . '/' . $required)) {
        fail("{$required} is missing. Upload all files from the v2.9.0 ZIP first.");
    }
}

echo "Application root: {$appRoot}\n\n";

// 1. Routes: append a require of routes/packages.php to the end of routes/web.php.
$webPath = $appRoot . '/routes/web.php';
$web = (string) file_get_contents($webPath);
if (str_contains($web, "routes/packages.php") || str_contains($web, "/packages.php'")) {
    echo "Routes: already registered.\n";
} else {
    $backup = backupFile($webPath);
    writeFileAtomically($webPath, rtrim($web) . "\n\nrequire __DIR__ . '/packages.php';\n");
    echo "Routes: registered (backup: " . basename($backup) . ").\n";
}

// 2. Menu: add the Package Manager link to the Admin section, just above Logout.
$layoutPath = $appRoot . '/resources/views/layouts/app.blade.php';
$layout = (string) file_get_contents($layoutPath);
$anchor = '<form class="logout-form"';
if (str_contains($layout, "route('packages.index')")) {
    echo "Menu: link already present.\n";
} elseif (!str_contains($layout, $anchor)) {
    echo "Menu: logout anchor not found, link not added. Open /settings/packages directly.\n";
} else {
    $link = "@if(auth()->user()->hasPermission('packages.manage'))\n"
        . "                    <a href=\"{{ route('packages.index') }}\" class=\"{{ request()->routeIs('packages.*') ? 'active' : '' }}\"><span class=\"nav-icon\">📦</span><span class=\"nav-text\"><strong>Package Manager</strong><span>Updates and rollbacks</span></span></a>\n"
        . "                @endif\n                ";
    $position = strpos($layout, $anchor);
    $backup = backupFile($layoutPath);
    writeFileAtomically($layoutPath, substr($layout, 0, $position) . $link . substr($layout, $position));
    echo "Menu: Package Manager link added (backup: " . basename($backup) . ").\n";
}

// 3. Clear compiled views and cached routes/config so the changes load immediately.
foreach (glob($appRoot . '/bootstrap/cache/{config,routes-v7,routes}.php', GLOB_BRACE) ?: [] as $cache) {
    @unlink($cache);
}
foreach (glob($appRoot . '/storage/framework/views/*.php') ?: [] as $view) {
    @unlink($view);
}
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
echo "Caches: cleared.\n\n";

echo "SUCCESS. Open /settings/packages (Admin > Package Manager) to finish setup, then DELETE public/iso-package-manager-setup.php.\n";
