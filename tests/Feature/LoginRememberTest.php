<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRememberTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_remember_sets_remember_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'remember' => true,
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_leaves_remember_token_empty(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass'), 'remember_token' => null]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ])->assertOk();

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_remember_must_be_boolean(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'remember' => 'forever',
        ])->assertUnprocessable()->assertJsonValidationErrors(['remember']);
    }

    public function test_wrong_password_still_fails(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'nope',
            'remember' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
