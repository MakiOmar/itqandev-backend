<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** Seeded before signing in: after Sanctum::actingAs, static Role lookups default to the sanctum guard. */
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'editor', 'viewer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function actingAsRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function newUserPayload(array $roleIds): array
    {
        return [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'Secret@123456',
            'role_ids' => $roleIds,
        ];
    }

    public function test_admin_creates_a_user_with_a_role_over_sanctum(): void
    {
        $this->actingAsRole('admin');
        $editor = Role::findByName('editor', 'web');

        $this->postJson('/api/v1/users', $this->newUserPayload([$editor->id]))
            ->assertCreated()
            ->assertJsonPath('roles.0.name', 'editor');

        $this->assertTrue(User::where('email', 'new.staff@example.com')->firstOrFail()->hasRole('editor'));
    }

    public function test_admin_changes_a_users_roles_over_sanctum(): void
    {
        $this->actingAsRole('admin');
        $target = User::factory()->create();
        $target->assignRole('viewer');
        $admin = Role::findByName('admin', 'web');

        $this->putJson("/api/v1/users/{$target->id}", ['role_ids' => [$admin->id]])
            ->assertOk()
            ->assertJsonPath('roles.0.name', 'admin');

        $this->assertSame(['admin'], $target->fresh()->getRoleNames()->all());
    }

    public function test_rejected_super_admin_role_does_not_leave_a_user_behind(): void
    {
        $this->actingAsRole('admin');
        $superAdmin = Role::findByName('super_admin', 'web');

        $this->postJson('/api/v1/users', $this->newUserPayload([$superAdmin->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertDatabaseMissing('users', ['email' => 'new.staff@example.com']);
    }

    public function test_rejected_role_change_leaves_the_user_untouched(): void
    {
        $this->actingAsRole('admin');
        $target = User::factory()->create(['name' => 'Before']);
        $superAdmin = Role::findByName('super_admin', 'web');

        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'After', 'role_ids' => [$superAdmin->id]])
            ->assertUnprocessable();

        $this->assertSame('Before', $target->fresh()->name);
    }

    public function test_super_admin_can_assign_super_admin(): void
    {
        $this->actingAsRole('super_admin');
        $superAdmin = Role::findByName('super_admin', 'web');

        $this->postJson('/api/v1/users', $this->newUserPayload([$superAdmin->id]))
            ->assertCreated()
            ->assertJsonPath('roles.0.name', 'super_admin');
    }

    public function test_permission_granted_through_a_custom_role_works_over_sanctum(): void
    {
        Permission::findOrCreate('manage pages', 'web');
        Role::findOrCreate('contributor', 'web')->givePermissionTo('manage pages');
        $this->actingAsRole('contributor');

        $this->getJson('/api/v1/pages')->assertOk();
    }
}
