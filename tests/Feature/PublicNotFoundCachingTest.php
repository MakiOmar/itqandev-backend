<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicNotFoundCachingTest extends TestCase
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

    public function test_unknown_page_slug_is_looked_up_once(): void
    {
        $this->getJson('/api/public/pages/no-such-page')->assertNotFound();

        $this->assertSame(0, $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages/no-such-page')->assertNotFound()));
    }

    public function test_unknown_form_slug_is_looked_up_once(): void
    {
        $this->getJson('/api/public/forms/no-such-form')->assertNotFound();

        $this->assertSame(0, $this->queriesAgainst('forms', fn () => $this->getJson('/api/public/forms/no-such-form')->assertNotFound()));
    }

    public function test_mixed_case_page_slug_shares_the_lowercase_cache_entry(): void
    {
        Page::query()->create([
            'title' => 'About',
            'slug' => 'about-us',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => [],
        ]);

        $this->getJson('/api/public/pages/about-us')->assertOk()->assertJsonPath('slug', 'about-us');

        $queries = $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages/About-Us')
            ->assertOk()
            ->assertJsonPath('slug', 'about-us'));
        $this->assertSame(0, $queries);
    }

    public function test_overlong_slug_is_rejected_by_the_route(): void
    {
        $queries = $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages/'.str_repeat('a', 300))->assertNotFound());
        $this->assertSame(0, $queries);
    }

    private function queriesAgainst(string $table, callable $request): int
    {
        $count = 0;
        DB::listen(function ($query) use ($table, &$count): void {
            if (preg_match('/\bfrom\s+["`]?'.preg_quote($table, '/').'["`]?/i', $query->sql)) {
                $count++;
            }
        });
        $request();

        return $count;
    }
}
