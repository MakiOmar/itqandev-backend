<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
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

    public function test_viewer_gets_403_on_authenticated_v1_list(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->withHeaders($this->bearerHeaders($viewer))
            ->getJson('/api/v1/categories')
            ->assertForbidden();
    }

    public function test_company_can_list_categories(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/categories')
            ->assertOk();
    }

    public function test_me_includes_user_wrapper_and_permissions(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'name',
                    'role',
                    'roles',
                    'permissions',
                ],
            ]);
    }

    public function test_viewer_cannot_list_users(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->withHeaders($this->bearerHeaders($viewer))
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_company_cannot_clear_cache(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->postJson('/api/v1/cache/clear')
            ->assertForbidden();
    }

    public function test_admin_can_clear_cache(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $this->withHeaders($this->bearerHeaders($user))
            ->postJson('/api/v1/cache/clear')
            ->assertOk();
    }

    public function test_company_cannot_list_blog_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/blog-posts')
            ->assertForbidden();
    }

    public function test_editor_can_list_blog_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/blog-posts')
            ->assertOk();
    }

    public function test_company_cannot_view_system_health(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/system/health')
            ->assertForbidden();
    }

    public function test_admin_can_view_system_health(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/system/health')
            ->assertOk();
    }
}
