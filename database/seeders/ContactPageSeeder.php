<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\ContactPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `contact`) editable in Page Builder.
 * Public route `/{lang}/contact/` prefers this layout when the pages module is on.
 * Ensures an Arabic page_translations row linked to the same record.
 */
class ContactPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('pages')) {
            return;
        }

        if (FeatureModules::enabled('forms')) {
            $this->call(ContactFormSeeder::class);
        }

        $page = Page::query()->where('slug', 'contact')->first();

        if ($page === null) {
            $page = Page::create([
                'title' => 'Contact',
                'slug' => 'contact',
                'excerpt' => 'Get in touch with our team about your next project.',
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'sections' => ContactPageLayout::sections(),
            ]);
        } else {
            // Refresh starter layout when Arabic overlays or layout revision are missing.
            $encoded = json_encode($page->sections ?? []);
            $needsLayoutRefresh =
                $encoded === false
                || ! str_contains($encoded, '"translations"')
                || ! str_contains($encoded, '"layout_revision":'.ContactPageLayout::LAYOUT_REVISION);
            if ($needsLayoutRefresh) {
                $page->sections = ContactPageLayout::sections();
                $page->save();
            }
        }

        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'title' => 'تواصل معنا',
                'excerpt' => 'تواصل مع فريقنا بشأن مشروعك القادم.',
            ]
        );

        // Bust admin list + public page caches (model boot may not run on translation-only writes).
        Page::bumpPublicCacheVersion();
    }
}
