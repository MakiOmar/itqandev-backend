<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicServerPingTest extends TestCase
{
    public function test_public_ping_is_reachable_without_authentication(): void
    {
        $response = $this->getJson('/api/public/ping');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'message',
                'timestamp',
                'server_ms',
                'app_env',
                'laravel_version',
                'php_version',
                'database' => [
                    'status',
                    'connection',
                    'error',
                ],
            ],
        ]);
        $serverMs = $response->json('data.server_ms');
        $this->assertIsInt($serverMs);
        $this->assertGreaterThanOrEqual(0, $serverMs);
    }
}
