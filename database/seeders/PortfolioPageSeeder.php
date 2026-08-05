<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\PortfolioPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `portfolio`) editable in Page Builder.
 * Public route `/{lang}/portfolio/` uses this layout when the pages module is on.
 * Migrates legacy CMS slug `work` → `portfolio`.
 */
class PortfolioPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $portfolioSlug = 'portfolio';

        $page = Page::query()->where('slug', $portfolioSlug)->first();

        if ($page === null) {
            $legacy = Page::query()->where('slug', 'work')->first();
            if ($legacy !== null) {
                $legacy->title = 'Portfolio';
                $legacy->slug = $portfolioSlug;
                $legacy->excerpt = 'Selected projects across web, mobile, and product platforms.';
                $legacy->sections = PortfolioPageLayout::sections();
                $legacy->save();
                $page = $legacy;
            } else {
                $page = Page::create([
                    'title' => 'Portfolio',
                    'slug' => $portfolioSlug,
                    'excerpt' => 'Selected projects across web, mobile, and product platforms.',
                    'status' => Page::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'content_locale' => null,
                    'sections' => PortfolioPageLayout::sections(),
                ]);
            }
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"type":"projects_list"')
                || ! str_contains($encoded, '"layout_revision":'.PortfolioPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->title = 'Portfolio';
                $page->excerpt = 'Selected projects across web, mobile, and product platforms.';
                $page->sections = PortfolioPageLayout::sections();
                $page->save();
            }
        }

        // Remove any leftover duplicate `work` page after rename.
        Page::query()->where('slug', 'work')->where('id', '!=', $page->id)->delete();

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'المحفظة',
                'excerpt' => 'مشاريع مختارة عبر الويب والموبايل ومنصات المنتجات.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
