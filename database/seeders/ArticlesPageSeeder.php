<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\ArticlesPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `articles`) editable in Page Builder.
 * Public route `/{lang}/blog/` prefers this layout when the pages module is on.
 */
class ArticlesPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'articles')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Articles',
                'slug' => 'articles',
                'excerpt' => 'Updates, tips, and insights from our team.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => ArticlesPageLayout::sections(),
            ]);
        } else {
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"type":"blog_posts_list"')
                || ! str_contains($encoded, '"layout_revision":'.ArticlesPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->title = 'Articles';
                $page->excerpt = 'Updates, tips, and insights from our team.';
                $page->sections = ArticlesPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'المقالات',
                'excerpt' => 'تحديثات ونصائح ورؤى من فريقنا.',
            ]
        );

        Page::bumpPublicCacheVersion();
    }
}
