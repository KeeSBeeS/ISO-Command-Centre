<?php

declare(strict_types=1);

/**
 * One-use hotfix for Undefined variable $vehiclePolicyValid.
 * Upload into the active public directory, run once, then delete.
 */

const ISO_VIEW_HOTFIX_KEY = 'iso-employee-policy-view-hotfix-2026';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!hash_equals(ISO_VIEW_HOTFIX_KEY, $key)) {
    http_response_code(403);
    exit("Forbidden\n");
}

function hotfixFail(string $message): never
{
    http_response_code(500);
    exit("FAILED: {$message}\n");
}

function hotfixWrite(string $path, string $contents): void
{
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        hotfixFail("Could not write temporary file: {$temporary}");
    }

    @chmod($temporary, fileperms($path) & 0777);

    if (!rename($temporary, $path)) {
        @unlink($temporary);
        hotfixFail("Could not replace active Blade file: {$path}");
    }
}

$possibleRoots = array_values(array_unique([
    dirname(__DIR__),
    dirname(__DIR__, 2),
    __DIR__,
]));

$appRoot = null;
foreach ($possibleRoots as $candidate) {
    $candidate = realpath($candidate) ?: $candidate;

    if (
        is_file($candidate . '/bootstrap/app.php')
        && is_file($candidate . '/resources/views/employees/show.blade.php')
    ) {
        $appRoot = $candidate;
        break;
    }
}

if ($appRoot === null) {
    hotfixFail(
        'Could not identify the active Laravel root. Script directory: '
        . __DIR__
        . '. Checked: '
        . implode(', ', $possibleRoots)
    );
}

$viewPath = $appRoot . '/resources/views/employees/show.blade.php';
$view = file_get_contents($viewPath);

if ($view === false) {
    hotfixFail("Could not read active Blade file: {$viewPath}");
}

$original = $view;
$backupPath = $viewPath . '.bak-' . date('Ymd-His');

if (!copy($viewPath, $backupPath)) {
    hotfixFail("Could not create backup: {$backupPath}");
}

$defaultBlock = <<<'BLADE'
@php
    $vehiclePolicyValid = $vehiclePolicyValid ?? false;
@endphp
BLADE;

if (!str_contains($view, '$vehiclePolicyValid = $vehiclePolicyValid ?? false;')) {
    $sectionPattern = "/@section\(\s*['\"]content['\"]\s*\)/";
    $view = preg_replace(
        $sectionPattern,
        "$0\n{$defaultBlock}",
        $view,
        1,
        $sectionCount
    );

    if ($view === null || $sectionCount !== 1) {
        hotfixFail('Could not inject the default variable block after @section(content).');
    }
}

$view = preg_replace(
    '/@if\s*\(\s*\$vehiclePolicyValid\s*\)/',
    '@if(($vehiclePolicyValid ?? false))',
    $view,
    -1,
    $conditionCount
);

if ($view === null) {
    hotfixFail('Regex error while replacing the vehicle policy condition.');
}

if (
    $conditionCount === 0
    && !str_contains($view, '@if(($vehiclePolicyValid ?? false))')
    && !str_contains($view, '@if($vehiclePolicyValid ?? false)')
) {
    hotfixFail('Could not find the vehicle policy Blade condition to patch.');
}

if ($view !== $original) {
    hotfixWrite($viewPath, $view);
}

$clearedViews = 0;
$compiledDirectory = $appRoot . '/storage/framework/views';
if (is_dir($compiledDirectory)) {
    foreach (glob($compiledDirectory . '/*.php') ?: [] as $compiledView) {
        if (is_file($compiledView) && @unlink($compiledView)) {
            $clearedViews++;
        }
    }
}

$opcacheReset = false;
if (function_exists('opcache_reset')) {
    $opcacheReset = @opcache_reset();
}

$verified = file_get_contents($viewPath);
if (
    $verified === false
    || !str_contains($verified, '$vehiclePolicyValid = $vehiclePolicyValid ?? false;')
    || (
        !str_contains($verified, '@if(($vehiclePolicyValid ?? false))')
        && !str_contains($verified, '@if($vehiclePolicyValid ?? false)')
    )
) {
    hotfixFail('Post-write verification failed. Restore the backup if required: ' . $backupPath);
}

echo "SUCCESS\n";
echo "Application root: {$appRoot}\n";
echo "Active Blade file: {$viewPath}\n";
echo "Backup: {$backupPath}\n";
echo "Condition replacements: {$conditionCount}\n";
echo "Compiled Blade files cleared: {$clearedViews}\n";
echo "OPcache reset: " . ($opcacheReset ? 'yes' : 'not available or disabled') . "\n";
echo "\nDelete this file now: " . __FILE__ . "\n";
