<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\ProjectSettingsStore;
use App\Support\StaticHomepage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class PublicPagesQueryTest extends TestCase
{
    use CountsTableQueries, RefreshDatabase;

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

    public function test_page_list_query_count_does_not_grow_with_rows(): void
    {
        $home = $this->publishedPage('home');
        ProjectSettingsStore::merge(['page_on_front' => $home->id]);

        $this->app->forgetScopedInstances();
        $few = $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages')->assertOk());

        foreach (range(1, 5) as $n) {
            $this->publishedPage('page-'.$n);
        }
        $this->app->forgetScopedInstances();
        $many = $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages')
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonFragment(['slug' => 'home', 'public_path' => '/']));

        $this->assertSame($few, $many);
    }

    public function test_front_page_memo_follows_page_updates_in_the_same_request(): void
    {
        $home = $this->publishedPage('home');
        ProjectSettingsStore::merge(['page_on_front' => $home->id]);
        $this->assertTrue(StaticHomepage::isFrontPageSlug('home'));

        $home->update(['slug' => 'start']);

        $this->assertFalse(StaticHomepage::isFrontPageSlug('home'));
        $this->assertTrue(StaticHomepage::isFrontPageSlug('start'));
    }

    public function test_draft_parent_slug_and_id_stay_out_of_public_payloads(): void
    {
        $parent = Page::query()->create([
            'title' => 'Secret launch',
            'slug' => 'secret-launch',
            'status' => Page::STATUS_DRAFT,
            'sections' => [],
        ]);
        $child = $this->publishedPage('pricing-faq', $parent->id);

        $list = $this->getJson('/api/public/pages')->assertOk();
        $this->assertStringNotContainsString('secret-launch', $list->getContent());
        $list->assertJsonFragment(['slug' => 'pricing-faq', 'path' => 'pricing-faq', 'parent_id' => null]);

        $this->getJson('/api/public/pages/pricing-faq')
            ->assertOk()
            ->assertJsonPath('id', $child->id)
            ->assertJsonPath('path', 'pricing-faq')
            ->assertJsonPath('public_path', '/pages/pricing-faq/')
            ->assertJsonPath('parent_id', null);
    }

    public function test_published_parent_builds_nested_path_and_parent_tags(): void
    {
        $parent = $this->publishedPage('company');
        $this->publishedPage('team', $parent->id, [$this->headingWith('{{page.parent.title}}|{{page.parent.url}}')]);

        $response = $this->getJson('/api/public/pages/team')
            ->assertOk()
            ->assertJsonPath('path', 'company/team')
            ->assertJsonPath('public_path', '/pages/company/team/')
            ->assertJsonPath('parent_id', $parent->id);
        $this->assertSame('Company|/pages/company', $response->json('sections.0.rows.0.columns.0.blocks.0.settings.text'));
    }

    public function test_draft_parent_title_does_not_resolve_in_dynamic_tags(): void
    {
        $parent = Page::query()->create([
            'title' => 'Secret launch',
            'slug' => 'secret-launch',
            'status' => Page::STATUS_DRAFT,
            'sections' => [],
        ]);
        $this->publishedPage('pricing-faq', $parent->id, [$this->headingWith('Parent: {{page.parent.title}}')]);

        $body = $this->getJson('/api/public/pages/pricing-faq')->assertOk()->getContent();

        $this->assertStringContainsString('Parent: ', $body);
        $this->assertStringNotContainsString('Secret launch', $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function headingWith(string $text): array
    {
        return [
            'id' => 'band_hero',
            'type' => 'layout',
            'enabled' => true,
            'layout_width' => 'boxed',
            'settings' => [],
            'rows' => [[
                'id' => 'row_hero',
                'columns' => [[
                    'id' => 'col_hero',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'hero',
                        'kind' => 'widget',
                        'type' => 'heading',
                        'enabled' => true,
                        'settings' => ['text' => $text, 'tag' => 'h2'],
                    ]],
                ]],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function publishedPage(string $slug, ?int $parentId = null, array $sections = []): Page
    {
        return Page::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'parent_id' => $parentId,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => $sections,
        ]);
    }
}
