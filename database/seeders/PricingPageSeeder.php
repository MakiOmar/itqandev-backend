<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\PricingPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `pricing`) editable in Page Builder.
 * Public route `/{lang}/pricing/` prefers this layout when the pages module is on.
 * Ensures an Arabic page_translations row linked to the same record.
 */
class PricingPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'pricing')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Pricing',
                'slug' => 'pricing',
                'excerpt' => 'Transparent packages. Custom quotes for larger scope.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => PricingPageLayout::sections(),
            ]);
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"translations"')
                || ! str_contains($encoded, '"layout_revision":'.PricingPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->sections = PricingPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'الأسعار',
                'excerpt' => 'باقات شفافة. عروض مخصّصة للنطاق الأكبر.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
