<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures `manage fonts` exists on upgrades that ran migrate without re-seeding.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('manage fonts');

        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions(Permission::all());
            }
        }

        foreach (['company', 'editor'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo('manage fonts');
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::where('name', 'manage fonts')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
