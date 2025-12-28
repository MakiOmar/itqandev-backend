<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Database\Seeders\WebDevDemoSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage users',
            'manage roles',
            'manage projects',
            'manage categories',
            'manage skills',
            'manage media',
            'manage testimonials',
            'manage blog',
            'manage seo',
            'view analytics',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo(Permission::all());

        $companyRole = Role::findOrCreate('company');
        $companyRole->givePermissionTo([
            'manage projects',
            'manage categories',
            'manage skills',
            'manage media',
            'manage testimonials',
            'manage seo',
        ]);

        $editorRole = Role::findOrCreate('editor');
        $editorRole->givePermissionTo([
            'manage projects',
            'manage categories',
            'manage skills',
            'manage media',
            'manage testimonials',
            'manage seo',
            'manage blog',
        ]);

        $viewerRole = Role::findOrCreate('viewer');
        $viewerRole->givePermissionTo(['view analytics']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@credocode.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@123456'),
            ]
        );

        $admin->assignRole($adminRole);

        $this->call(WebDevDemoSeeder::class);
    }
}
