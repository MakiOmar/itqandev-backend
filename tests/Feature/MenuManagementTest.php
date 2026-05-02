<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
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

    public function test_public_menu_unknown_slug_returns_empty_items(): void
    {
        $this->getJson('/api/public/menus/does-not-exist?locale=en')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', []);
    }

    public function test_viewer_cannot_list_menus(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->withHeaders($this->bearerHeaders($viewer))
            ->getJson('/api/v1/menus')
            ->assertForbidden();
    }

    public function test_admin_can_add_static_item_and_public_menu_resolves(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $menu = Menu::query()->where('slug', 'primary')->first();
        $this->assertNotNull($menu);

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson("/api/v1/menus/{$menu->id}/items", [
                'item_type' => 'static_route',
                'static_route_key' => 'services',
                'label' => 'Our services',
                'open_in_new_tab' => false,
            ])
            ->assertCreated();

        $this->getJson('/api/public/menus/primary?locale=en')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['label' => 'Our services', 'href' => '/en/services/']);
    }

    public function test_admin_can_reorder_root_items(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $menu = Menu::query()->where('slug', 'primary')->first();
        $this->assertNotNull($menu);

        $a = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => 0,
            'label' => 'First',
            'item_type' => MenuItem::TYPE_STATIC_ROUTE,
            'static_route_key' => 'home',
            'open_in_new_tab' => false,
        ]);
        $b = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => 1,
            'label' => 'Second',
            'item_type' => MenuItem::TYPE_STATIC_ROUTE,
            'static_route_key' => 'contact',
            'open_in_new_tab' => false,
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson("/api/v1/menus/{$menu->id}/items/reorder", [
                'items' => [
                    ['id' => $b->id, 'parent_id' => null, 'sort_order' => 0],
                    ['id' => $a->id, 'parent_id' => null, 'sort_order' => 1],
                ],
            ])
            ->assertOk();

        $this->assertSame(0, MenuItem::query()->find($b->id)?->sort_order);
        $this->assertSame(1, MenuItem::query()->find($a->id)?->sort_order);
    }
}
