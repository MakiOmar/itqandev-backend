<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\PricingPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\PricingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_layout_includes_tiers_faq_and_arabic_overlays(): void
    {
        $sections = PricingPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"pricing"', $json);
        $this->assertStringContainsString('"type":"faq"', $json);
        $this->assertStringContainsString('Starter', $json);
        $this->assertStringContainsString('Growth', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.PricingPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_pricing_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(PricingPageSeeder::class);

        $page = Page::query()->where('slug', 'pricing')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('الأسعار', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/pricing', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'pricing')
            ->assertJsonPath('title', 'Pricing');

        $this->getJson('/api/public/pages/pricing', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'pricing')
            ->assertJsonPath('title', 'الأسعار');

        $this->seed(PricingPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'pricing')->count());
        $this->assertSame(1, $page->fresh()->translations()->where('locale', 'ar')->count());
    }

    public function test_admin_list_includes_seeded_pricing_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(PricingPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $slugs = collect($en->json())->pluck('slug')->all();
        $this->assertContains('pricing', $slugs);

        $ar = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $arRows = collect($ar->json());
        $this->assertTrue($arRows->contains(fn ($row) => ($row['slug'] ?? null) === 'pricing'));
        $pricing = $arRows->first(fn ($row) => ($row['slug'] ?? null) === 'pricing');
        $this->assertSame('الأسعار', $pricing['title'] ?? null);
    }
}
