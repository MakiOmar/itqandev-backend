<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\AboutPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `about`) editable in Page Builder.
 * Public route `/{lang}/about/` prefers this layout when the pages module is on.
 * Ensures an Arabic page_translations row linked to the same record.
 */
class AboutPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'about')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'About',
                'slug' => 'about',
                'excerpt' => 'Building digital products since 2014 — craft, clarity, and lasting partnerships.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => AboutPageLayout::sections(),
            ]);
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"translations"')
                || ! str_contains($encoded, '"layout_revision":'.AboutPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->sections = AboutPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'من نحن',
                'excerpt' => 'نبني منتجات رقمية منذ ٢٠١٤ — حرفية ووضوح وشراكات تدوم.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
