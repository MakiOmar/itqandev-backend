<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Appearance\ContactPageLayout;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;

/**
 * Seeds a published CMS page (slug `contact`) editable in Page Builder.
 * Public route `/{lang}/contact/` prefers this layout when the pages module is on.
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

        if (Page::query()->where('slug', 'contact')->exists()) {
            return;
        }

        Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'excerpt' => 'Get in touch with our team about your next project.',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => null,
            'sections' => ContactPageLayout::sections(),
        ]);
    }
}
