<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Repairs installs where roles exist but role_has_permissions was never populated
 * (e.g. migrate without running DatabaseSeeder), which yields empty /me permissions
 * while users still have the admin role string.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'manage users',
            'manage roles',
            'manage projects',
            'manage categories',
            'manage skills',
            'manage services',
            'manage media',
            'manage testimonials',
            'manage blog',
            'manage seo',
            'view analytics',
            'manage system',
        ];

        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name);
        }

        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions(Permission::all());
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: do not detach permissions on rollback (unknown prior state).
    }
};
