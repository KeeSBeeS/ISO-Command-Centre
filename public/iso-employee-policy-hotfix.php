<?php

declare(strict_types=1);

/**
 * Temporary one-use hotfix for the employee profile vehicle-policy error.
 * Delete this file immediately after it reports success.
 */

const ISO_HOTFIX_KEY = 'iso-employee-policy-hotfix-2026';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!hash_equals(ISO_HOTFIX_KEY, $key)) {
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
        && is_file($root . '/app/Http/Controllers/EmployeeController.php')
        && is_file($root . '/resources/views/employees/show.blade.php')
    ) {
        $appRoot = $root;
        break;
    }
}

if ($appRoot === null) {
    fail(
        "Laravel application root could not be identified. Script directory: "
        . __DIR__
        . ". Checked: "
        . implode(', ', $possibleRoots)
    );
}

$controllerPath = $appRoot . '/app/Http/Controllers/EmployeeController.php';
$viewPath = $appRoot . '/resources/views/employees/show.blade.php';

$controller = file_get_contents($controllerPath);
$view = file_get_contents($viewPath);

if ($controller === false || $view === false) {
    fail('Could not read the active controller or Blade view.');
}

$controllerChanged = false;
$viewChanged = false;
$backups = [];

$oldReturn = "        return view('employees.show', compact('employee', 'lateAttendanceStats', 'recentLateAttendance'));";
$newReturn = <<<'PHP'
        $vehiclePolicyValid = false;
        if (Schema::hasTable('employee_documents') && $employee->relationLoaded('documents')) {
            $vehiclePolicyValid = $employee->documents
                ->where('document_type', 'vehicle_policy')
                ->where('status', 'active')
                ->contains(function ($document) {
                    return !$document->has_expiry
                        || !$document->expires_at
                        || $document->expires_at->gte(now()->startOfDay());
                });
        }

        return view('employees.show', compact(
            'employee',
            'lateAttendanceStats',
            'recentLateAttendance',
            'vehiclePolicyValid'
        ));
PHP;

if (str_contains($controller, $oldReturn)) {
    $backups[] = backupFile($controllerPath);
    $controller = str_replace($oldReturn, $newReturn, $controller, $count);

    if ($count !== 1) {
        fail("Unexpected EmployeeController replacement count: {$count}");
    }

    writeFileAtomically($controllerPath, $controller);
    $controllerChanged = true;
} elseif (!str_contains($controller, "'vehiclePolicyValid'")) {
    fail('EmployeeController did not match the expected old or corrected structure. No controller change was applied.');
}

$oldCondition = '@if($vehiclePolicyValid)';
$newCondition = '@if($vehiclePolicyValid ?? false)';

if (str_contains($view, $oldCondition)) {
    $backups[] = backupFile($viewPath);
    $view = str_replace($oldCondition, $newCondition, $view, $count);

    if ($count < 1) {
        fail('The Blade condition was not replaced.');
    }

    writeFileAtomically($viewPath, $view);
    $viewChanged = true;
} elseif (!str_contains($view, $newCondition)) {
    fail('Employee Blade view did not contain the expected old or corrected condition.');
}

$clearedViews = 0;
$compiledViewDirectory = $appRoot . '/storage/framework/views';
if (is_dir($compiledViewDirectory)) {
    foreach (glob($compiledViewDirectory . '/*.php') ?: [] as $compiledView) {
        if (is_file($compiledView) && @unlink($compiledView)) {
            $clearedViews++;
        }
    }
}

$opcacheReset = false;
if (function_exists('opcache_reset')) {
    $opcacheReset = @opcache_reset();
}

$activeView = file_get_contents($viewPath);
$activeController = file_get_contents($controllerPath);

if (
    $activeView === false
    || !str_contains($activeView, '@if($vehiclePolicyValid ?? false)')
    || $activeController === false
    || !str_contains($activeController, "'vehiclePolicyValid'")
) {
    fail('Post-write verification failed.');
}

echo "SUCCESS\n";
echo "Application root: {$appRoot}\n";
echo "Controller: {$controllerPath}\n";
echo "Controller changed: " . ($controllerChanged ? 'yes' : 'already corrected') . "\n";
echo "View: {$viewPath}\n";
echo "View changed: " . ($viewChanged ? 'yes' : 'already corrected') . "\n";
echo "Compiled Blade files cleared: {$clearedViews}\n";
echo "OPcache reset: " . ($opcacheReset ? 'yes' : 'not available or disabled') . "\n";
echo "Backups:\n";
foreach ($backups as $backup) {
    echo "- {$backup}\n";
}
echo "\nDelete this hotfix file now: " . __FILE__ . "\n";
