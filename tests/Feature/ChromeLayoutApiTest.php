<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\HeaderBuilderService;
use App\Services\PublicMarketingShellService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChromeLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        \App\Support\ProjectSettingsStore::save(['site_name' => 'Test']);
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    public function test_can_crud_header_layout(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        $create = $this->withHeaders($headers)->postJson('/api/appearance/headers', [
            'name' => 'Alt header',
            'status' => 'published',
        ]);
        $create->assertCreated();
        $id = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $id);

        $this->withHeaders($headers)->getJson('/api/appearance/headers/'.$id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Alt header');

        $this->withHeaders($headers)->putJson('/api/appearance/headers/'.$id, [
            'name' => 'Alt header renamed',
            'sections' => (new HeaderBuilderService)->defaultDocument()['sections'],
        ])->assertOk()->assertJsonPath('data.name', 'Alt header renamed');

        $this->withHeaders($headers)->deleteJson('/api/appearance/headers/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('chrome_layouts', ['id' => $id]);
    }

    public function test_set_site_default_requires_published(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        $draft = ChromeLayout::query()->create([
            'kind' => 'header',
            'name' => 'Draft H',
            'slug' => 'draft-h',
            'status' => 'draft',
            'document' => (new HeaderBuilderService)->defaultDocument(),
            'is_site_default' => false,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/appearance/headers/'.$draft->id.'/set-site-default')
            ->assertStatus(422);
    }

    public function test_type_defaults_reject_draft_ids(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        $draft = ChromeLayout::query()->create([
            'kind' => 'header',
            'name' => 'Draft H2',
            'slug' => 'draft-h2',
            'status' => 'draft',
            'document' => (new HeaderBuilderService)->defaultDocument(),
            'is_site_default' => false,
        ]);

        $this->withHeaders($headers)->putJson('/api/appearance/chrome-type-defaults', [
            'page' => ['header_id' => $draft->id, 'footer_id' => null],
        ])->assertStatus(422);
    }

    public function test_resolver_skips_draft_record_assignment(): void
    {
        $site = ChromeLayout::query()->where('kind', 'header')->where('is_site_default', true)->first();
        if ($site === null) {
            $site = ChromeLayout::query()->create([
                'kind' => 'header',
                'name' => 'Pub H',
                'slug' => 'pub-h',
                'status' => 'published',
                'document' => (new HeaderBuilderService)->defaultDocument(),
                'is_site_default' => true,
            ]);
        }

        $draft = ChromeLayout::query()->create([
            'kind' => 'header',
            'name' => 'Draft assign',
            'slug' => 'draft-assign',
            'status' => 'draft',
            'document' => ['sections' => []],
            'is_site_default' => false,
        ]);

        $page = Page::query()->create([
            'title' => 'P',
            'slug' => 'p-chrome',
            'status' => Page::STATUS_PUBLISHED,
            'sections' => [],
            'header_layout_id' => $draft->id,
        ]);

        $resolved = app(ChromeLayoutResolver::class)->resolve('header', 'page', $page, 'en');
        $this->assertNotEmpty($resolved['sections']);
        $this->assertSame(
            (int) $site->id,
            (int) app(ChromeLayoutService::class)->findSiteDefault('header')?->id
        );
    }

    /**
     * Header/footer builders offer the page builder's widgets and kits; they must save,
     * sanitize and present exactly like page layouts.
     */
    public function test_header_and_footer_layouts_round_trip_page_widgets_and_kits(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        foreach (['headers', 'footers'] as $slug) {
            $create = $this->withHeaders($headers)->postJson('/api/appearance/'.$slug, [
                'name' => 'Widgets '.$slug,
                'status' => 'published',
            ])->assertCreated();
            $id = (int) $create->json('data.id');

            $sections = [[
                'id' => 'band_w',
                'type' => 'layout',
                'rows' => [[
                    'id' => 'row_w',
                    'columns' => [[
                        'id' => 'col_w',
                        'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                        'blocks' => [
                            ['id' => 'blk_brand', 'kind' => 'kit', 'type' => $slug === 'headers' ? 'header_brand' : 'footer_brand', 'settings' => []],
                            ['id' => 'blk_heading', 'kind' => 'widget', 'type' => 'heading', 'settings' => ['text' => 'Hello', 'level' => 'h3']],
                            ['id' => 'blk_rich', 'kind' => 'widget', 'type' => 'rich_text', 'settings' => ['html' => '<p>Hi</p><script>alert(1)</script>']],
                            ['id' => 'blk_cta', 'kind' => 'kit', 'type' => 'cta', 'settings' => []],
                        ],
                    ]],
                ]],
            ]];

            $this->withHeaders($headers)->putJson('/api/appearance/'.$slug.'/'.$id, ['sections' => $sections])
                ->assertOk();

            $blocks = $this->withHeaders($headers)->getJson('/api/appearance/'.$slug.'/'.$id)
                ->assertOk()
                ->json('data.sections.0.rows.0.columns.0.blocks');
            $this->assertSame(
                ['blk_brand', 'blk_heading', 'blk_rich', 'blk_cta'],
                array_column($blocks, 'id'),
            );
            $byId = array_column($blocks, null, 'id');
            $this->assertSame('widget', $byId['blk_heading']['kind']);
            $this->assertSame('h3', $byId['blk_heading']['settings']['level']);
            $this->assertSame('kit', $byId['blk_cta']['kind']);
            $this->assertStringNotContainsString('<script', json_encode($byId['blk_rich']['settings']));

            $presented = app(ChromeLayoutService::class)->presentById($id, 'en');
            $publicTypes = array_column($presented['sections'][0]['rows'][0]['columns'][0]['blocks'], 'type');
            $this->assertContains('heading', $publicTypes);
            $this->assertContains('rich_text', $publicTypes);
            $this->assertContains('cta', $publicTypes);
        }
    }

    public function test_legacy_appearance_header_endpoint_still_works(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        $get = $this->withHeaders($headers)->getJson('/api/appearance/header')->assertOk();
        $sections = $get->json('data.sections');
        $this->assertIsArray($sections);

        $this->withHeaders($headers)->putJson('/api/appearance/header', ['sections' => $sections])
            ->assertOk();
    }
}
