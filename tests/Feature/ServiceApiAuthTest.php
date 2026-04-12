<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServiceApiAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_04_12_100000_create_services_tables.php',
            '--force' => true,
        ]);
    }

    public function test_v1_services_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/services', [
            'name' => 'Test',
            'slug' => 'test-slug-unique',
        ]);

        $response->assertUnauthorized();
    }
}
