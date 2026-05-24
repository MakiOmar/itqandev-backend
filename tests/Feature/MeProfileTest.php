<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_patch_me_updates_profile(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $this->withHeaders($this->bearerHeaders($user))
            ->patchJson('/api/me', [
                'name' => 'Updated Admin',
                'email' => 'admin@credocode.test',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Admin');
    }

    public function test_put_me_password_requires_current_password(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $this->withHeaders($this->bearerHeaders($user))
            ->putJson('/api/me/password', [
                'current_password' => 'wrong',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertStatus(422);

        $this->withHeaders($this->bearerHeaders($user))
            ->putJson('/api/me/password', [
                'current_password' => 'Admin@123456',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_me_requires_auth(): void
    {
        $this->patchJson('/api/me', ['name' => 'X', 'email' => 'x@example.com'])
            ->assertUnauthorized();
    }
}
