<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_ping_is_not_cached(): void
    {
        $response = $this->getJson('/api/public/ping');

        $response->assertOk();
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function test_public_shell_has_short_public_cache_for_guests(): void
    {
        config([
            'http-cache.public_api_max_age' => 60,
            'http-cache.public_api_s_maxage' => 300,
        ]);

        $response = $this->getJson('/api/public/shell?locale=en');

        $response->assertOk();
        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=60', $cache);
        $this->assertStringContainsString('s-maxage=300', $cache);
        $vary = (string) $response->headers->get('Vary');
        $this->assertStringContainsString('Accept-Language', $vary);
        $this->assertStringContainsString('X-Content-Locale', $vary);
    }

    public function test_authenticated_request_to_public_api_is_not_publicly_cached(): void
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/public/shell?locale=en', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-cache', $cache);
    }

    public function test_authenticated_settings_api_is_private_no_cache(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $user = \App\Models\User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/settings', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
    }
}
