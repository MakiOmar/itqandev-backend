<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceTranslation;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PublicServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_04_12_100000_create_services_tables.php',
            '--force' => true,
        ]);
    }

    public function test_public_services_returns_localized_payload(): void
    {
        $service = Service::query()->create([
            'slug' => 'test-svc',
            'content_locale' => 'en',
            'icon' => 'web',
            'sort_order' => 0,
            'is_published' => true,
            'name' => 'English name',
            'short_description' => 'English short',
            'description' => 'English body',
            'process' => ['a'],
            'deliverables' => ['b'],
        ]);
        ServiceTranslation::query()->create([
            'service_id' => $service->id,
            'locale' => 'ar',
            'name' => 'اسم',
            'short_description' => 'قصير',
            'description' => 'وصف',
            'process' => ['خطوة'],
            'deliverables' => ['تسليم'],
        ]);

        $response = $this->getJson('/api/public/services', [
            'X-Content-Locale' => 'ar',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'اسم');
        $response->assertJsonPath('data.0.shortDescription', 'قصير');
        $response->assertJsonPath('data.0.process.0', 'خطوة');
    }
}
