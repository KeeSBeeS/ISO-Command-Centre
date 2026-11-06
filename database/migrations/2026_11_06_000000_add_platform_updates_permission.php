<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3.0.0: the permission for System Settings → Updates. System
 * Administrators can always use it; other roles get it from the role editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return; // Not installed yet: /install seeds it.
        }

        if (! DB::table('permissions')->where('slug', 'platform_updates.manage')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Manage Platform Updates',
                'slug' => 'platform_updates.manage',
                'module' => 'Settings',
                'description' => 'Install new versions from GitHub, switch back to older versions and manage the recovery key.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('slug', 'platform_updates.manage')->value('id');
        $roleId = Schema::hasTable('roles') ? DB::table('roles')->where('slug', 'system-administrator')->value('id') : null;
        if ($roleId && Schema::hasTable('permission_role')
            && ! DB::table('permission_role')->where(['permission_id' => $permissionId, 'role_id' => $roleId])->exists()) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Rolling back restores a database backup instead.
     */
    public function down(): void {}
};
