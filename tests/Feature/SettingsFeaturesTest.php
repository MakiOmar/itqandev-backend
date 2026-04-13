<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Settings updates require auth; full persist tests need a working migrated DB (see phpunit sqlite limits in this repo).
 */
class SettingsFeaturesTest extends TestCase
{
    public function test_put_settings_requires_authentication(): void
    {
        Storage::fake('local');

        $response = $this->putJson('/api/settings', [
            'features' => [
                'projects' => false,
            ],
        ]);

        $response->assertUnauthorized();
    }
}
