<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Page;
use App\Support\ProjectSettingsStore;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PublicShellCacheBoundsTest extends TestCase
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
        Menu::query()->firstOrCreate(['slug' => 'primary'], ['name' => 'Primary']);
    }

    public function test_unknown_paths_share_one_not_found_shell(): void
    {
        $this->shell('/en/no-such-page-1')->assertJsonPath('data.theme_context', 'not_found');

        $this->assertSame(0, $this->shellsBuiltFor('/en/no-such-page-2'));
        $this->assertSame(0, $this->shellsBuiltFor('/en/pages/missing-slug'));
    }

    public function test_spellings_of_one_path_and_unknown_contexts_reuse_the_entry(): void
    {
        Page::query()->create([
            'title' => 'About', 'slug' => 'about', 'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(), 'sections' => [],
        ]);
        $this->shell('/en/about/');

        $this->assertSame(0, $this->shellsBuiltFor('/EN/About'));
        $this->assertSame(0, $this->shellsBuiltFor('/en/about', 'definitely-not-a-context'));
    }

    public function test_site_content_ignores_unknown_locales(): void
    {
        $this->getJson('/api/public/site-content?locale=zz-random')->assertOk();

        $this->assertFalse(Cache::has('public:site-content:loc:zz-random'));
        $this->assertTrue(Cache::has('public:site-content:loc:en'));
    }

    private function shell(string $path, ?string $context = null): \Illuminate\Testing\TestResponse
    {
        $this->app->forgetScopedInstances();
        $query = http_build_query(array_filter(['locale' => 'en', 'path' => $path, 'theme_context' => $context]));

        return $this->getJson('/api/public/shell?'.$query)->assertOk();
    }

    /** Full shell payloads written to the cache while requesting `$path`. */
    private function shellsBuiltFor(string $path, ?string $context = null): int
    {
        $built = 0;
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$built) {
            if (str_starts_with($event->key, 'public:shell:') && is_array($event->value) && isset($event->value['site_meta'])) {
                $built++;
            }
        });
        $this->shell($path, $context);

        return $built;
    }
}
