<?php

namespace Tests\Feature;

use App\Models\BuilderGlobal;
use App\Models\ChromeLayout;
use App\Models\Page;
use App\Services\Appearance\ChromeLayoutService;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderContentCachingTest extends TestCase
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

    public function test_page_shows_an_edited_global_on_the_next_request(): void
    {
        $global = $this->global('Shared v1');
        Page::query()->create([
            'title' => 'Promo',
            'slug' => 'promo',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => $this->sectionsWith($global),
        ]);
        $this->getJson('/api/public/pages/promo')->assertOk()->assertSee('Shared v1');

        $this->editGlobal($global, 'Shared v2');

        $this->getJson('/api/public/pages/promo')->assertOk()->assertSee('Shared v2')->assertDontSee('Shared v1');
    }

    public function test_chrome_layout_shows_an_edited_global_on_the_next_request(): void
    {
        $global = $this->global('Footer v1');
        $layout = ChromeLayout::query()->create([
            'kind' => ChromeLayout::KIND_FOOTER,
            'name' => 'Footer',
            'slug' => 'footer',
            'status' => ChromeLayout::STATUS_PUBLISHED,
            'document' => ['sections' => $this->sectionsWith($global)],
        ]);
        $layouts = app(ChromeLayoutService::class);
        $this->assertStringContainsString('Footer v1', json_encode($layouts->presentById($layout->id, 'en')));

        $this->editGlobal($global, 'Footer v2');

        $this->assertStringContainsString('Footer v2', json_encode($layouts->presentById($layout->id, 'en')));
    }

    private function global(string $text): BuilderGlobal
    {
        return BuilderGlobal::query()->create([
            'name' => 'Promo heading',
            'slug' => 'promo-heading',
            'status' => BuilderGlobal::STATUS_PUBLISHED,
            'document' => $this->headingDocument($text),
        ]);
    }

    private function editGlobal(BuilderGlobal $global, string $text): void
    {
        $global->update(['document' => $this->headingDocument($text)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function headingDocument(string $text): array
    {
        return ['kind' => 'widget', 'type' => 'heading', 'enabled' => true, 'settings' => ['text' => $text]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sectionsWith(BuilderGlobal $global): array
    {
        return [[
            'id' => 'band_1',
            'type' => 'layout',
            'rows' => [['id' => 'row_1', 'columns' => [['id' => 'col_1', 'blocks' => [[
                'id' => 'glb_place',
                'kind' => 'global',
                'global_id' => $global->id,
            ]]]]]],
        ]];
    }
}
