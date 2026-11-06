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

    private function publishedPage(string $slug, ?int $parentId = null): Page
    {
        return Page::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'parent_id' => $parentId,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => [],
        ]);
    }
}
