<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\WorkPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `work`) editable in Page Builder.
 * Public route `/{lang}/work/` prefers this layout when the pages module is on.
 */
class WorkPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'work')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Work',
                'slug' => 'work',
                'excerpt' => 'Selected projects across web, mobile, and product platforms.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => WorkPageLayout::sections(),
            ]);
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"type":"projects_list"')
                || ! str_contains($encoded, '"layout_revision":'.WorkPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->sections = WorkPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'أعمالنا',
                'excerpt' => 'مشاريع مختارة عبر الويب والموبايل ومنصات المنتجات.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
