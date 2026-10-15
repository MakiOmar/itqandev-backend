<?php

namespace Tests\Feature;

use App\Addons\AddonRegistry;
use App\Addons\AddonServiceProvider;
use App\Models\User;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\WidgetRegistry;
use App\Services\ContentTypes\ContentTypeRegistry;
use App\Support\FeatureModules;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Fixtures\Addon\ReservedPrefixContentType;
use Tests\Fixtures\Addon\SampleAddonServiceProvider;
use Tests\Fixtures\Addon\SampleItem;
use Tests\TestCase;

/**
 * The base must host an installed addon end to end without core edits (docs/ADDONS.md).
 */
class AddonHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
        Schema::create('sample_addon_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->text('content')->nullable();
            $table->string('status')->default('published');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        AddonRegistry::forget(SampleAddonServiceProvider::MODULE);
        AddonRegistry::forget('sample_bad');
        parent::tearDown();
    }

    private function installSampleAddon(bool $enabled): void
    {
        config(['features.addons' => $enabled ? SampleAddonServiceProvider::MODULE : '']);
        $this->app->register(SampleAddonServiceProvider::class, true);
        $this->app->forgetInstance(ContentTypeRegistry::class);
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@credocode.test')->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function layoutWithSampleNote(): array
    {
        return [[
            'type' => 'layout',
            'rows' => [[
                'columns' => [[
                    'blocks' => [[
                        'id' => 'blk_note',
                        'kind' => 'widget',
                        'type' => 'sample_note',
                        'settings' => ['text' => 'Keep me'],
                    ]],
                ]],
            ]],
        ]];
    }

    /** @param  list<array<string, mixed>>  $sections */
    private function firstBlock(array $sections): ?array
    {
        return $sections[0]['rows'][0]['columns'][0]['blocks'][0] ?? null;
    }

    public function test_disabled_addon_contributes_nothing_but_saved_blocks_survive(): void
    {
        $this->installSampleAddon(false);

        $this->assertFalse(FeatureModules::enabled(SampleAddonServiceProvider::MODULE));
        $this->assertArrayHasKey(SampleAddonServiceProvider::MODULE, FeatureModules::all());
        $this->assertArrayNotHasKey('sample_note', WidgetRegistry::all());
        $this->assertNull(app(ContentTypeRegistry::class)->get('sample_item'));

        $this->getJson('/api/v1/sample-addon/ping')->assertNotFound();
        SampleItem::query()->create(['title' => 'Blue mug', 'slug' => 'blue-mug']);
        $this->getJson('/api/public/content/sample-items/blue-mug')->assertNotFound();

        $settings = $this->getJson('/api/settings', $this->bearer($this->admin()))->assertOk();
        $this->assertSame([], $settings->json('data.addons'));

        $saved = PageLayoutDocument::normalizeSectionsForPages($this->layoutWithSampleNote());
        $block = $this->firstBlock($saved);
        $this->assertSame('sample_note', $block['type'] ?? null);
        $this->assertSame('Keep me', $block['settings']['text'] ?? null);

        $public = PageLayoutDocument::presentPublicForPages($saved, 'en');
        $this->assertNull($this->firstBlock($public));
    }

    public function test_enabled_addon_is_wired_through_registries_routes_and_settings(): void
    {
        $this->installSampleAddon(true);
        $admin = $this->admin();

        $registries = $this->getJson('/api/appearance/registries', $this->bearer($admin))->assertOk();
        $this->assertContains('sample_note', array_column($registries->json('data.widgets'), 'type'));
        $this->assertContains('sample_item', array_column($registries->json('data.content_types'), 'key'));

        $this->getJson('/api/v1/sample-addon/ping')->assertOk()->assertJsonPath('data', 'pong');

        SampleItem::query()->create(['title' => 'Blue mug', 'slug' => 'blue-mug', 'excerpt' => 'A mug.']);
        $this->getJson('/api/public/content/sample-items/blue-mug')
            ->assertOk()
            ->assertJsonPath('data.type', 'sample_item')
            ->assertJsonPath('data.title', 'Blue mug')
            ->assertJsonPath('data.path', '/sample-items/blue-mug/');
        $this->getJson('/api/public/content/no-such-prefix/blue-mug')->assertNotFound();
        $this->getJson('/api/public/content-sitemap')->assertOk()->assertJsonPath('data', ['/sample-items/blue-mug/']);

        $settings = $this->getJson('/api/settings', $this->bearer($admin))->assertOk();
        $this->assertTrue($settings->json('data.features.sample_addon'));
        $this->assertSame('/admin/addons/sample_addon/', $settings->json('data.addons.0.href'));
        $this->assertSame('عناصر تجريبية', $settings->json('data.addons.0.label.ar'));

        $this->getJson('/api/me/addon-nav', $this->bearer($admin))->assertOk()->assertJsonCount(1, 'data');
        $editor = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me/addon-nav', $this->bearer($editor))->assertOk()->assertJsonCount(0, 'data');

        $public = PageLayoutDocument::presentPublicForPages(
            PageLayoutDocument::normalizeSectionsForPages($this->layoutWithSampleNote()),
            'en',
        );
        $this->assertSame('Keep me', $this->firstBlock($public)['settings']['text'] ?? null);
    }

    public function test_module_can_be_pinned_in_config_over_the_env_list(): void
    {
        config(['features.modules.'.SampleAddonServiceProvider::MODULE => false]);
        $this->installSampleAddon(true);

        $this->assertFalse(FeatureModules::enabled(SampleAddonServiceProvider::MODULE));
    }

    public function test_addon_cannot_claim_a_built_in_module_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AddonRegistry::register($this->addon('blog', widgets: []));
    }

    public function test_addon_widget_type_must_not_shadow_a_built_in_widget(): void
    {
        AddonRegistry::register($this->addon('sample_bad', widgets: ['heading' => [
            'label' => 'Heading', 'category' => 'Bad', 'default_settings' => [], 'settings_fields' => [],
        ]]));

        $this->expectException(InvalidArgumentException::class);
        WidgetRegistry::all();
    }

    public function test_addon_content_type_must_not_claim_a_reserved_prefix(): void
    {
        config(['features.addons' => 'sample_bad']);
        AddonRegistry::register($this->addon('sample_bad', widgets: [], contentTypes: [ReservedPrefixContentType::class]));
        $this->app->forgetInstance(ContentTypeRegistry::class);

        $this->expectExceptionObject(new InvalidArgumentException('Addon [sample_bad]: public prefix [blog] is reserved or invalid.'));
        app(ContentTypeRegistry::class)->configured();
    }

    /**
     * @param  array<string, array<string, mixed>>  $widgets
     * @param  list<class-string>  $contentTypes
     */
    private function addon(string $key, array $widgets, array $contentTypes = []): AddonServiceProvider
    {
        return new class($this->app, $key, $widgets, $contentTypes) extends AddonServiceProvider
        {
            /**
             * @param  array<string, array<string, mixed>>  $leafs
             * @param  list<class-string>  $types
             */
            public function __construct($app, private string $key, private array $leafs, private array $types)
            {
                parent::__construct($app);
            }

            public function moduleKey(): string
            {
                return $this->key;
            }

            public function widgets(): array
            {
                return $this->leafs;
            }

            public function contentTypes(): array
            {
                return $this->types;
            }
        };
    }
}
