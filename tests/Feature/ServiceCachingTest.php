<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_reflects_an_edit_immediately(): void
    {
        $service = $this->service('Old name');
        $this->actingAdmin();
        $this->getJson('/api/v1/services')->assertOk()->assertJsonPath('0.name', 'Old name');

        $this->putJson('/api/v1/services/'.$service->id, ['name' => 'New name', 'slug' => $service->slug])->assertOk();

        $this->getJson('/api/v1/services')->assertJsonPath('0.name', 'New name');
    }

    public function test_public_list_caches_plain_arrays_and_drops_bulk_deleted_services(): void
    {
        $first = $this->service('First');
        $this->service('Second');
        $this->getJson('/api/public/services')->assertOk()->assertJsonCount(2, 'data');

        $cached = Cache::get(CacheKey::versioned(Service::class).':public:list:loc:none');
        $this->assertIsArray($cached);
        $this->assertIsArray($cached[0]);

        $this->actingAdmin();
        $this->postJson('/api/v1/services/bulk-delete', ['ids' => [$first->id]])->assertOk();

        $this->getJson('/api/public/services')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Second');
    }

    private function service(string $name): Service
    {
        return Service::query()->create([
            'slug' => strtolower($name),
            'name' => $name,
            'is_published' => true,
            'sort_order' => Service::query()->count(),
        ]);
    }

    private function actingAdmin(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
    }
}
