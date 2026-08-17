<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Services\PublicMenuResolver;
use App\Support\FeatureModules;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PagesModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    private function actingEditor(): User
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_feature_modules_includes_pages(): void
    {
        $this->assertContains('pages', FeatureModules::canonicalKeys());
        $this->assertTrue(FeatureModules::enabled('pages'));
    }

    public function test_admin_can_create_and_list_page(): void
    {
        $this->actingEditor();

        $create = $this->postJson('/api/v1/pages', [
            'title' => 'About Us',
            'slug' => 'about-us',
            'excerpt' => 'Who we are',
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'cta',
                    'enabled' => true,
                    'layout_width' => 'boxed',
                    'settings' => [
                        'title' => 'Get in touch',
                        'button_label' => 'Contact',
                    ],
                ],
            ],
        ]);

        $create->assertCreated();
        $this->assertSame('about-us', $create->json('slug'));
        $this->assertNotEmpty($create->json('sections'));

        $list = $this->getJson('/api/v1/pages');
        $list->assertOk();
        $this->assertCount(1, $list->json());
    }

    public function test_public_show_requires_published_and_locale_content(): void
    {
        $page = Page::create([
            'title' => 'Hello EN',
            'slug' => 'hello',
            'excerpt' => 'EN excerpt',
            'status' => Page::STATUS_PUBLISHED,
            'content_locale' => 'en',
            'published_at' => now(),
            'sections' => [],
        ]);
        $page->translations()->create([
            'locale' => 'ar',
            'title' => 'مرحبا',
            'excerpt' => 'وصف',
        ]);

        $en = $this->getJson('/api/public/pages/hello', [
            'X-Content-Locale' => 'en',
        ]);
        $en->assertOk();
        $this->assertSame('Hello EN', $en->json('title'));

        $ar = $this->getJson('/api/public/pages/hello', [
            'X-Content-Locale' => 'ar',
        ]);
        $ar->assertOk();
        $this->assertSame('مرحبا', $ar->json('title'));

        Page::query()->whereKey($page->id)->first()?->update(['status' => Page::STATUS_DRAFT]);
        $this->getJson('/api/public/pages/hello', ['X-Content-Locale' => 'en'])->assertNotFound();
    }

    public function test_menu_resolves_page_item(): void
    {
        $page = Page::create([
            'title' => 'Pricing',
            'slug' => 'pricing-page',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => [],
        ]);

        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary-test']);
        MenuItem::create([
            'menu_id' => $menu->id,
            'sort_order' => 0,
            'label' => null,
            'item_type' => MenuItem::TYPE_PAGE,
            'reference_id' => $page->id,
            'open_in_new_tab' => false,
        ]);

        $tree = PublicMenuResolver::resolvePublishedTree('primary-test', 'en');
        $this->assertCount(1, $tree);
        $this->assertSame('Pricing', $tree[0]['label']);
        $this->assertStringContainsString('/pages/pricing-page/', $tree[0]['href']);
    }

    public function test_nested_page_and_exclude_from_search(): void
    {
        $this->actingEditor();

        $parent = $this->postJson('/api/v1/pages', [
            'title' => 'Legal',
            'slug' => 'legal',
            'status' => 'published',
            'sections' => [],
        ]);
        $parent->assertCreated();
        $parentId = (int) $parent->json('id');

        $child = $this->postJson('/api/v1/pages', [
            'title' => 'Privacy',
            'slug' => 'privacy',
            'status' => 'published',
            'parent_id' => $parentId,
            'exclude_from_search' => true,
            'sections' => [],
        ]);
        $child->assertCreated();
        $this->assertSame($parentId, $child->json('parent_id'));
        $this->assertTrue($child->json('exclude_from_search'));
        $this->assertSame('legal/privacy', $child->json('path'));
        $this->assertSame('/pages/legal/privacy/', $child->json('public_path'));

        $cycle = $this->putJson('/api/v1/pages/'.$parentId, [
            'parent_id' => (int) $child->json('id'),
        ]);
        $cycle->assertStatus(422);

        $list = $this->getJson('/api/public/pages', ['X-Content-Locale' => 'en']);
        $list->assertOk();
        $slugs = collect($list->json())->pluck('slug')->all();
        $this->assertContains('legal', $slugs);
        $this->assertNotContains('privacy', $slugs);

        $direct = $this->getJson('/api/public/pages/privacy', ['X-Content-Locale' => 'en']);
        $direct->assertOk();
        $this->assertSame('Privacy', $direct->json('title'));
        $this->assertTrue($direct->json('exclude_from_search'));
        $this->assertSame('legal/privacy', $direct->json('path'));
        $this->assertSame('/pages/legal/privacy/', $direct->json('public_path'));

        $menu = Menu::create(['name' => 'Nested', 'slug' => 'nested-pages-test']);
        MenuItem::create([
            'menu_id' => $menu->id,
            'sort_order' => 0,
            'label' => null,
            'item_type' => MenuItem::TYPE_PAGE,
            'reference_id' => (int) $child->json('id'),
            'open_in_new_tab' => false,
        ]);
        $tree = PublicMenuResolver::resolvePublishedTree('nested-pages-test', 'en');
        $this->assertCount(1, $tree);
        $this->assertStringContainsString('/pages/legal/privacy/', $tree[0]['href']);
    }

    public function test_admin_list_filters_by_content_locale(): void
    {
        $this->actingEditor();

        Page::create([
            'title' => 'Only EN',
            'slug' => 'only-en',
            'status' => Page::STATUS_PUBLISHED,
            'content_locale' => 'en',
            'published_at' => now(),
            'sections' => [],
        ]);

        $arOnly = Page::create([
            'title' => 'Primary title',
            'slug' => 'ar-extra',
            'status' => Page::STATUS_PUBLISHED,
            'content_locale' => 'en',
            'published_at' => now(),
            'sections' => [],
        ]);
        $arOnly->translations()->create([
            'locale' => 'ar',
            'title' => 'عنوان عربي',
            'excerpt' => 'وصف',
        ]);

        $enList = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $enList->assertOk();
        $this->assertGreaterThanOrEqual(2, count($enList->json()));

        $arList = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'ar']);
        $arList->assertOk();
        $titles = collect($arList->json())->pluck('title')->all();
        $this->assertContains('عنوان عربي', $titles);
        $this->assertNotContains('Only EN', $titles);
    }

    public function test_feature_module_off_blocks_public_and_admin(): void
    {
        config(['features.modules.pages' => false]);

        $this->getJson('/api/public/pages')->assertForbidden();

        $this->actingEditor();
        $this->getJson('/api/v1/pages')->assertForbidden();
    }

    public function test_admin_create_wraps_legacy_and_accepts_layout_tree(): void
    {
        $this->actingEditor();

        $legacy = $this->postJson('/api/v1/pages', [
            'title' => 'Legacy wrap',
            'slug' => 'legacy-wrap',
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'cta',
                    'enabled' => true,
                    'layout_width' => 'boxed',
                    'settings' => ['title' => 'CTA'],
                ],
            ],
        ]);
        $legacy->assertCreated();
        $this->assertSame('layout', $legacy->json('sections.0.type'));
        $this->assertSame('cta', $legacy->json('sections.0.rows.0.columns.0.blocks.0.type'));

        $layout = $this->postJson('/api/v1/pages', [
            'title' => 'Layout tree',
            'slug' => 'layout-tree',
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'layout',
                    'layout_width' => 'full',
                    'rows' => [
                        [
                            'stack_below' => 'tablet',
                            'columns' => [
                                [
                                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 6],
                                    'blocks' => [
                                        ['type' => 'cta', 'settings' => ['title' => 'Left']],
                                    ],
                                ],
                                [
                                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 6],
                                    'blocks' => [
                                        ['type' => 'tech_stack', 'settings' => []],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $layout->assertCreated();
        $this->assertCount(2, $layout->json('sections.0.rows.0.columns'));

        $public = $this->getJson('/api/public/pages/layout-tree', [
            'X-Content-Locale' => 'en',
        ]);
        $public->assertOk();
        $this->assertSame('layout', $public->json('sections.0.type'));
        $this->assertSame(
            ['mobile' => 12, 'tablet' => 6, 'desktop' => 6],
            $public->json('sections.0.rows.0.columns.0.span'),
        );
        $this->assertSame('cta', $public->json('sections.0.rows.0.columns.0.blocks.0.type'));
    }
}
