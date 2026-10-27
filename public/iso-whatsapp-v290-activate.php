<?php

declare(strict_types=1);

/**
 * One-use activation for the v2.9.0 WhatsApp Business module on an active install.
 *
 * Patches the LIVE files in place instead of replacing them, so v2.7/v2.8 menu items and
 * providers that are not in this package are kept:
 *  - bootstrap/providers.php: registers App\Providers\WhatsAppServiceProvider
 *  - resources/views/layouts/app.blade.php: adds the WhatsApp menu link and page icon
 *
 * 1. Change ISO_ACTIVATE_KEY below to your own long random value before uploading.
 * 2. Open /iso-whatsapp-v290-activate.php?key=YOUR_KEY
 * 3. The file deletes itself after SUCCESS. If it reports that it could not, delete it manually.
 */
const ISO_ACTIVATE_KEY = 'CHANGE-ME';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (ISO_ACTIVATE_KEY === 'CHANGE-ME' || strlen(ISO_ACTIVATE_KEY) < 16 || !hash_equals(ISO_ACTIVATE_KEY, $key)) {
    http_response_code(403);
    exit("Forbidden. Set ISO_ACTIVATE_KEY in this file to a long random value (16+ characters) and pass it as ?key=\n");
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
}

$possibleRoots = array_values(array_unique([
    dirname(__DIR__),
    dirname(__DIR__, 2),
    __DIR__,
]));

$appRoot = null;
foreach ($possibleRoots as $root) {
    $root = realpath($root) ?: $root;

    if (
        is_file($root . '/bootstrap/app.php')
        && is_file($root . '/bootstrap/providers.php')
        && is_file($root . '/resources/views/layouts/app.blade.php')
    ) {
        $appRoot = $root;
        break;
    }
}

if ($appRoot === null) {
    fail('Laravel application root could not be identified. Checked: ' . implode(', ', $possibleRoots));
}

$required = [
    '/app/Providers/WhatsAppServiceProvider.php',
    '/routes/whatsapp.php',
    '/app/Services/WhatsApp/WhatsAppMessenger.php',
    '/resources/views/whatsapp/inbox.blade.php',
];
foreach ($required as $file) {
    if (!is_file($appRoot . $file)) {
        fail("Upload the v2.9.0 package files first. Missing: {$file}");
    }
}

$backups = [];

// 1. Provider registration
$providersPath = $appRoot . '/bootstrap/providers.php';
$providers = file_get_contents($providersPath);
if ($providers === false) {
    fail('Could not read bootstrap/providers.php');
}

$providerChanged = false;
if (!str_contains($providers, 'WhatsAppServiceProvider')) {
    $closing = strrpos($providers, '];');
    if ($closing === false || !preg_match('/return\s*\[/', $providers)) {
        fail('bootstrap/providers.php did not contain the expected "return [ ... ];" structure.');
    }

    $before = rtrim(substr($providers, 0, $closing));
    $separator = str_ends_with($before, ',') || str_ends_with($before, '[') ? '' : ',';
    $updated = $before . $separator . "\n    App\\Providers\\WhatsAppServiceProvider::class,\n" . substr($providers, $closing);

    $backups[] = backupFile($providersPath);
    writeFileAtomically($providersPath, $updated);
    $providerChanged = true;
}

// 2. Layout menu link and page icon
$layoutPath = $appRoot . '/resources/views/layouts/app.blade.php';
$layout = file_get_contents($layoutPath);
if ($layout === false) {
    fail('Could not read the layout.');
}

$layoutChanged = false;
$iconChanged = false;
$originalLayout = $layout;

if (!str_contains($layout, "route('whatsapp.inbox')")) {
    $link = <<<'BLADE'
                @if(\Illuminate\Support\Facades\Route::has('whatsapp.inbox') && $currentUser->hasPermission('whatsapp.view') && \Illuminate\Support\Facades\Schema::hasTable('whatsapp_contacts'))
                    @php($whatsappUnread = (int) \App\Models\WhatsAppContact::sum('unread_count'))
                    <a href="{{ route('whatsapp.inbox') }}" class="{{ request()->routeIs('whatsapp.*') ? 'active' : '' }}"><span class="nav-icon">💬</span><span class="nav-text"><strong>WhatsApp{{ $whatsappUnread > 0 ? ' (' . $whatsappUnread . ')' : '' }}</strong><span>Business inbox</span></span></a>
                @endif

BLADE;

    $anchors = [
        '<div class="nav-section-label">Admin</div>',
        '<form class="logout-form"',
    ];

    $inserted = false;
    foreach ($anchors as $anchor) {
        $position = strpos($layout, $anchor);
        if ($position === false) {
            continue;
        }

        $lineStart = strrpos(substr($layout, 0, $position), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $layout = substr($layout, 0, $lineStart) . $link . substr($layout, $lineStart);
        $inserted = true;
        break;
    }

    if (!$inserted) {
        fail('Could not find the Admin menu section or logout form in the layout. Add the WhatsApp link manually (see README_V2_9_0_WHATSAPP_BUSINESS.md).');
    }

    $layoutChanged = true;
}

$iconAnchor = "str_starts_with(\$routeName, 'cron_jobs') => '⚙️',";
if (!str_contains($layout, "str_starts_with(\$routeName, 'whatsapp')") && str_contains($layout, $iconAnchor)) {
    $layout = str_replace($iconAnchor, $iconAnchor . "\n            str_starts_with(\$routeName, 'whatsapp') => '💬',", $layout);
    $iconChanged = true;
}

if ($layout !== $originalLayout) {
    $backups[] = backupFile($layoutPath);
    writeFileAtomically($layoutPath, $layout);
}

// 3. Clear compiled views, cached services/routes/config and OPcache
$clearedViews = 0;
foreach (glob($appRoot . '/storage/framework/views/*.php') ?: [] as $compiledView) {
    if (is_file($compiledView) && @unlink($compiledView)) {
        $clearedViews++;
    }
}

$clearedCaches = [];
foreach (['services.php', 'packages.php', 'config.php', 'routes-v7.php'] as $cacheFile) {
    $path = $appRoot . '/bootstrap/cache/' . $cacheFile;
    if (is_file($path) && @unlink($path)) {
        $clearedCaches[] = $cacheFile;
    }
}

$opcacheReset = function_exists('opcache_reset') ? @opcache_reset() : false;

$activeProviders = (string) file_get_contents($providersPath);
$activeLayout = (string) file_get_contents($layoutPath);
if (!str_contains($activeProviders, 'WhatsAppServiceProvider') || !str_contains($activeLayout, "route('whatsapp.inbox')")) {
    fail('Post-write verification failed.');
}

echo "SUCCESS\n";
echo "Application root: {$appRoot}\n";
echo 'Provider registered: ' . ($providerChanged ? 'yes' : 'already registered') . "\n";
echo 'Menu link added: ' . ($layoutChanged ? 'yes' : 'already present') . "\n";
echo 'Page icon added: ' . ($iconChanged ? 'yes' : 'skipped') . "\n";
echo "Compiled Blade files cleared: {$clearedViews}\n";
echo 'Bootstrap caches cleared: ' . ($clearedCaches ? implode(', ', $clearedCaches) : 'none present') . "\n";
echo 'OPcache reset: ' . ($opcacheReset ? 'yes' : 'not available or disabled') . "\n";
echo "Backups:\n";
foreach ($backups as $backup) {
    echo "- {$backup}\n";
}
echo "\nNext: open /updates/v2-9-0 and click Apply v2.9.0 Update.\n";

// The key is embedded in this file, so it removes itself once the work is done.
if (@unlink(__FILE__)) {
    echo "This activation file has deleted itself.\n";
} else {
    echo "Could not delete this activation file. Delete it now: " . __FILE__ . "\n";
}
