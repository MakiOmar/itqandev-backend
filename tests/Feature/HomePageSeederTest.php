<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Appearance\HomePageLayout;
use App\Support\FeatureModules;
use Database\Seeders\HomePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_wraps_homepage_kits_into_page_builder_bands(): void
    {
        $sections = HomePageLayout::sections();
        $this->assertNotEmpty($sections);
        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"layout"', $json);
        $this->assertTrue(
            str_contains($json, '"type":"hero"')
            || str_contains($json, '"type":"case_studies"')
            || HomePageLayout::bandTreeHasBlocks($sections)
        );
    }

    public function test_seeder_creates_published_home_page_with_arabic_translation(): void
    {
        if (! FeatureModules::enabled('pages')) {
            $this->markTestSkipped('Pages module is disabled.');
        }

        $this->seed(HomePageSeeder::class);

        $page = Page::query()->where('slug', 'home')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue(
            $page->translations()->where('locale', 'ar')->exists()
        );
    }

    public function test_seeder_does_not_overwrite_an_existing_home_page(): void
    {
        if (! FeatureModules::enabled('pages')) {
            $this->markTestSkipped('Pages module is disabled.');
        }

        $this->seed(HomePageSeeder::class);
        $page = Page::query()->where('slug', 'home')->first();
        $this->assertNotNull($page);
        $page->title = 'Custom Home';
        $page->save();

        $this->seed(HomePageSeeder::class);
        $page->refresh();
        $this->assertSame('Custom Home', $page->title);
    }
}
