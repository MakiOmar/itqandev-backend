<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\ServicesPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `services`) editable in Page Builder.
 * Public route `/{lang}/services/` prefers this layout when the pages module is on.
 * Ensures an Arabic page_translations row linked to the same record.
 */
class ServicesPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'services')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Services',
                'slug' => 'services',
                'excerpt' => 'Full product lifecycle — discovery, design, engineering, and launch support.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => ServicesPageLayout::sections(),
            ]);
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"translations"')
                || ! str_contains($encoded, '"layout_revision":'.ServicesPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->sections = ServicesPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'الخدمات',
                'excerpt' => 'دورة حياة المنتج كاملة — استكشاف وتصميم وهندسة ودعم الإطلاق.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
