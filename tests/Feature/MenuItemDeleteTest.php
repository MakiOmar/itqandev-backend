<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuItemDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_menu_item(): void
    {
        Permission::findOrCreate('manage menus');
        $role = Role::findOrCreate('admin');
        $role->givePermissionTo('manage menus');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $menu = Menu::query()->create(['name' => 'Primary', 'slug' => 'primary']);
        $item = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => 0,
            'label' => 'Home',
            'item_type' => MenuItem::TYPE_CUSTOM_LINK,
            'url' => '/',
            'open_in_new_tab' => false,
        ]);

        $this->deleteJson('/api/v1/menu-items/'.$item->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_missing_menu_item_delete_returns_404(): void
    {
        Permission::findOrCreate('manage menus');
        $role = Role::findOrCreate('admin');
        $role->givePermissionTo('manage menus');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/menu-items/999999')
            ->assertNotFound();
    }
}
