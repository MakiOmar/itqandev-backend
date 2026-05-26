<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Adds a dedicated test admin account for login debugging.
 *
 * Run: php artisan db:seed --class=TestAdminSeeder
 *
 * Credentials:
 *   Email:    testadmin@credocode.test
 *   Password: TestAdmin@123456
 */
class TestAdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::findOrCreate('admin');

        $user = User::updateOrCreate(
            ['email' => 'testadmin@credocode.test'],
            [
                'name' => 'Test Admin',
                // Plain password — User model `hashed` cast handles bcrypt.
                'password' => 'TestAdmin@123456',
            ]
        );

        if (! $user->hasRole($adminRole)) {
            $user->assignRole($adminRole);
        }

        $this->command?->info('Test admin ready: testadmin@credocode.test / TestAdmin@123456');
    }
}
