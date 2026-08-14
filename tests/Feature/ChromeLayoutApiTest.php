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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChromeLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('project-settings.json', json_encode(['site_name' => 'Test']));
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
