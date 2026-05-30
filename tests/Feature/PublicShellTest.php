<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_shell_is_reachable_without_authentication(): void
    {
        $response = $this->getJson('/api/public/shell?locale=en');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'site_meta' => [
                    'site_languages',
                    'default_locale',
                    'features',
                ],
                'menu' => [
                    'slug',
                    'locale',
                    'items',
                ],
                'services',
            ],
        ]);
    }
}
