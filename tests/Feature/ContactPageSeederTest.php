<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Appearance\ContactPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\ContactPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_layout_includes_form_and_contact_info(): void
    {
        $sections = ContactPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"form"', $json);
        $this->assertStringContainsString('"form_slug":"contact"', $json);
        $this->assertStringContainsString('"type":"contact_info"', $json);
        $this->assertStringContainsString('"type":"map"', $json);
        $this->assertStringContainsString('"type":"faq"', $json);
    }

    public function test_seeder_creates_published_contact_page(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(ContactPageSeeder::class);

        $page = Page::query()->where('slug', 'contact')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);

        $this->getJson('/api/public/pages/contact')
            ->assertOk()
            ->assertJsonPath('slug', 'contact')
            ->assertJsonPath('title', 'Contact');

        // Idempotent
        $this->seed(ContactPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'contact')->count());
    }
}
