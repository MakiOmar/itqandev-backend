<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\HomePageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `home`) whose layout is a Page Builder copy of
 * the current Theme body or Appearance homepage. Operators then assign it as the
 * static front page in Settings → General (WordPress Reading equivalent).
 */
class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'home')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Home',
                'slug' => 'home',
                'excerpt' => 'Site home — edit this page in Page Builder.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => HomePageLayout::sections(),
            ]);
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'الرئيسية',
                'excerpt' => 'الصفحة الرئيسية — حرّرها من منشئ الصفحات.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
