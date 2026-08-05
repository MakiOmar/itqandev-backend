<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Appearance\ContactPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\ContactPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
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
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
    }

    public function test_seeder_creates_published_contact_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(ContactPageSeeder::class);

        $page = Page::query()->where('slug', 'contact')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('تواصل معنا', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/contact', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'contact')
            ->assertJsonPath('title', 'Contact');

        $this->getJson('/api/public/pages/contact', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'contact')
            ->assertJsonPath('title', 'تواصل معنا');

        // Idempotent
        $this->seed(ContactPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'contact')->count());
        $this->assertSame(1, $page->fresh()->translations()->where('locale', 'ar')->count());
    }

    public function test_admin_list_includes_seeded_contact_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(ContactPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $slugs = collect($en->json())->pluck('slug')->all();
        $this->assertContains('contact', $slugs);

        $ar = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $arRows = collect($ar->json());
        $this->assertTrue($arRows->contains(fn ($row) => ($row['slug'] ?? null) === 'contact'));
        $contact = $arRows->first(fn ($row) => ($row['slug'] ?? null) === 'contact');
        $this->assertSame('تواصل معنا', $contact['title'] ?? null);
    }
}
