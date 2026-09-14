<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_session_cookie_authenticates_me_without_authorization_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withCredentials()
            ->withUnencryptedCookie('auth_session', $this->sessionCookie($user, $token))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_auth_session_cookie_does_not_authenticate(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie('auth_session', 'not-json')
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->withCredentials()
            ->withUnencryptedCookie('auth_session', json_encode([
                'token' => 'sanctum_cookie',
            ], JSON_THROW_ON_ERROR))
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_bearer_token_still_authenticates_me(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    private function sessionCookie(User $user, string $token): string
    {
        return json_encode([
            'user' => [
                'id' => (string) $user->id,
                'email' => $user->email,
            ],
            'token' => $token,
            'expiresAt' => (time() + 86400) * 1000,
        ], JSON_THROW_ON_ERROR);
    }
}
