<?php

namespace App\Updates;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Checks a freshly installed release works before the update is kept.
 */
class HealthCheck
{
    /**
     * @return list<string> problems found; empty means healthy
     */
    public function problems(): array
    {
        $checks = [
            'The database cannot be reached' => fn () => DB::select('select 1'),
            'Some database changes were not applied' => function () {
                $migrator = app('migrator');
                $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
                $pending = array_diff($files, $migrator->getRepository()->getRan());
                if ($pending) {
                    throw new RuntimeException(implode(', ', $pending));
                }
            },
            'No System Administrator account was found' => function () {
                if (! User::whereHas('roles', fn ($query) => $query->where('slug', 'system-administrator'))->exists()) {
                    throw new RuntimeException('No active user has the System Administrator role.');
                }
            },
            'The installation lock file is missing' => function () {
                if (! is_file(storage_path('app/isoadmin_installed.lock'))) {
                    throw new RuntimeException(storage_path('app/isoadmin_installed.lock'));
                }
            },
            'The core tables are missing' => function () {
                $missing = array_filter(['users', 'roles', 'permissions', 'role_user', 'permission_role'], fn ($table) => ! Schema::hasTable($table));
                if ($missing) {
                    throw new RuntimeException(implode(', ', $missing));
                }
            },
            'The pages cannot be displayed' => fn () => view('auth.login')->render(),
            'Storage is not writable' => function () {
                if (! is_writable(storage_path('framework'))) {
                    throw new RuntimeException(storage_path('framework'));
                }
            },
        ];

        $problems = [];
        foreach ($checks as $problem => $check) {
            try {
                $check();
            } catch (Throwable $e) {
                $problems[] = $problem.': '.$e->getMessage();
            }
        }

        return $problems;
    }
}
