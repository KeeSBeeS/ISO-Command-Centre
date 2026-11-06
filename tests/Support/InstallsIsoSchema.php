<?php

namespace Tests\Support;

use App\Http\Controllers\InstallController;
use App\Models\Role;
use App\Models\User;
use ReflectionMethod;

/**
 * Builds the ISO schema the way /install does (InstallController), since
 * most tables come from it rather than from migrations.
 */
trait InstallsIsoSchema
{
    protected function installIsoSchema(): void
    {
        $installer = new InstallController;
        foreach (['createTables', 'seedPermissionsAndRoles'] as $method) {
            (new ReflectionMethod($installer, $method))->invoke($installer);
        }
        $this->artisan('migrate', ['--force' => true]);
        is_dir(storage_path('app')) || mkdir(storage_path('app'), 0755, true);
        file_put_contents(storage_path('app/isoadmin_installed.lock'), 'test');
    }

    protected function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $user->roles()->attach(Role::where('slug', $slug)->value('id'));

        return $user;
    }
}
