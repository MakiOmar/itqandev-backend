<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_can_bulk_deactivate_and_activate_users(): void
    {
        $this->actingAsRole('admin');
        $a = User::factory()->create();
        $b = User::factory()->create();
        $untouched = User::factory()->create();
        $a->createToken('api');

        $this->postJson('/api/v1/users/bulk-status', ['ids' => [$a->id, $b->id], 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('updated', 2)
            ->assertJsonPath('status', 'inactive');

        $this->assertSame('inactive', $a->fresh()->status);
        $this->assertSame('inactive', $b->fresh()->status);
        $this->assertSame('active', $untouched->fresh()->status);
        $this->assertSame(0, $a->tokens()->count());

        $this->postJson('/api/v1/users/bulk-status', ['ids' => [$a->id], 'status' => 'active'])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertSame('active', $a->fresh()->status);
    }

    public function test_bulk_actions_never_touch_the_acting_user(): void
    {
        $admin = $this->actingAsRole('admin');
        $other = User::factory()->create();

        $this->postJson('/api/v1/users/bulk-status', ['ids' => [$admin->id, $other->id], 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertSame('active', $admin->fresh()->status);

        $this->postJson('/api/v1/users/bulk-delete', ['ids' => [$admin->id, $other->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 1);
        $this->assertNotNull($admin->fresh());
        $this->assertNull($other->fresh());
    }

    public function test_admin_can_bulk_delete_users_and_roles_are_detached(): void
    {
        $this->actingAsRole('admin');
        Role::findOrCreate('editor');
        $a = User::factory()->create();
        $a->assignRole('editor');
        $b = User::factory()->create();

        $this->postJson('/api/v1/users/bulk-delete', ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $a->id, 'model_type' => User::class]);
    }

    public function test_bulk_actions_validate_payload(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/users/bulk-delete', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->postJson('/api/v1/users/bulk-status', ['ids' => [999999], 'status' => 'banned'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'status']);
    }

    public function test_user_without_rights_cannot_use_bulk_actions(): void
    {
        $this->actingAsRole('viewer');
        $target = User::factory()->create();

        $this->postJson('/api/v1/users/bulk-status', ['ids' => [$target->id], 'status' => 'inactive'])
            ->assertForbidden();
        $this->postJson('/api/v1/users/bulk-delete', ['ids' => [$target->id]])
            ->assertForbidden();

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_inactive_user_cannot_log_in_or_use_existing_token(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'password' => 'secret-pass-123']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertForbidden();
    }

    public function test_admin_cannot_deactivate_themselves_via_update(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->putJson('/api/v1/users/'.$admin->id, ['status' => 'inactive'])
            ->assertStatus(422);

        $this->assertSame('active', $admin->fresh()->status);
    }
}
