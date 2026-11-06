<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\PublicMenuResolver;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingShellCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
            ],
        ]);
    }

    public function test_shell_reflects_menu_and_service_edits_immediately(): void
    {
        $menu = Menu::query()->firstOrCreate(['slug' => 'primary'], ['name' => 'Primary']);
        $item = $this->link($menu, 'Old label', 0);
        $service = Service::query()->create(['slug' => 'web', 'name' => 'Old service', 'is_published' => true]);
        $this->assertShell('Old label', 'Old service');

        $item->update(['label' => 'New label']);
        $service->update(['name' => 'New service']);

        $this->assertShell('New label', 'New service');
    }

    public function test_menu_reorder_is_visible_immediately(): void
    {
        $menu = Menu::query()->create(['slug' => 'reorder-test', 'name' => 'Reorder']);
        $first = $this->link($menu, 'First', 0);
        $second = $this->link($menu, 'Second', 1);
        $this->assertSame(['First', 'Second'], $this->labels('reorder-test'));

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/menus/'.$menu->id.'/items/reorder', ['items' => [
            ['id' => $first->id, 'parent_id' => null, 'sort_order' => 1],
            ['id' => $second->id, 'parent_id' => null, 'sort_order' => 0],
        ]])->assertOk();

        $this->assertSame(['Second', 'First'], $this->labels('reorder-test'));
    }

    public function test_unpublishing_a_linked_project_removes_it_from_the_menu(): void
    {
        $menu = Menu::query()->create(['slug' => 'publish-test', 'name' => 'Publish']);
        $project = Project::query()->create(['slug' => 'site', 'title' => 'Site', 'status' => 'published']);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'sort_order' => 0, 'label' => null,
            'item_type' => MenuItem::TYPE_PROJECT, 'reference_id' => $project->id, 'open_in_new_tab' => false,
        ]);
        $this->assertSame(['Site'], $this->labels('publish-test'));

        $project->update(['status' => 'draft']);

        $this->assertSame([], $this->labels('publish-test'));
    }

    private function assertShell(string $menuLabel, string $serviceName): void
    {
        $data = $this->getJson('/api/public/shell?locale=en')->assertOk()->json('data');
        $this->assertSame($menuLabel, $data['menu']['items'][0]['label'] ?? null);
        $this->assertSame($serviceName, $data['services'][0]['name'] ?? null);
    }

    /**
     * @return list<string>
     */
    private function labels(string $slug): array
    {
        return array_column(PublicMenuResolver::resolvePublishedTree($slug, 'en'), 'label');
    }

    private function link(Menu $menu, string $label, int $order): MenuItem
    {
        return MenuItem::query()->create([
            'menu_id' => $menu->id,
            'sort_order' => $order,
            'label' => $label,
            'item_type' => MenuItem::TYPE_CUSTOM_LINK,
            'url' => 'https://example.com/'.$order,
            'open_in_new_tab' => false,
        ]);
    }
}
