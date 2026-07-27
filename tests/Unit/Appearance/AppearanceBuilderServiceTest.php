<?php

namespace Tests\Unit\Appearance;

use App\Services\Appearance\FooterBuilderService;
use App\Services\Appearance\HomepageBuilderService;
use App\Services\Appearance\HomepageSectionRegistry;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppearanceBuilderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_homepage_defaults_include_seven_sections(): void
    {
        $doc = (new HomepageBuilderService)->defaultDocument();
        $this->assertCount(7, $doc['sections']);
        $types = array_column($doc['sections'], 'type');
        $this->assertSame(
            ['hero', 'services_teaser', 'case_studies', 'testimonials', 'tech_stack', 'blog_preview', 'cta'],
            $types
        );
    }

    public function test_homepage_rejects_unknown_types_and_enforces_max_instances(): void
    {
        $service = new HomepageBuilderService;
        $saved = $service->save([
            'sections' => [
                ['type' => 'hero', 'enabled' => true, 'settings' => ['headline' => 'A']],
                ['type' => 'hero', 'enabled' => true, 'settings' => ['headline' => 'B']],
                ['type' => 'not_a_real_type', 'enabled' => true],
                ['type' => 'cta', 'enabled' => false],
            ],
        ]);

        $types = array_column($saved['sections'], 'type');
        $this->assertSame(['hero', 'cta'], $types);
        $this->assertSame('A', $saved['sections'][0]['settings']['headline']);
    }

    public function test_homepage_public_present_skips_disabled(): void
    {
        $service = new HomepageBuilderService;
        $service->save([
            'sections' => [
                ['type' => 'hero', 'enabled' => true],
                ['type' => 'cta', 'enabled' => false],
            ],
        ]);
        $public = $service->presentPublic();
        $this->assertCount(1, $public);
        $this->assertSame('hero', $public[0]['type']);
        $this->assertArrayNotHasKey('enabled', $public[0]);
    }

    public function test_homepage_public_merges_locale_translations(): void
    {
        $service = new HomepageBuilderService;
        $service->save([
            'sections' => [
                [
                    'type' => 'hero',
                    'enabled' => true,
                    'settings' => [
                        'headline' => 'Hello EN',
                        'translations' => [
                            'ar' => ['headline' => 'مرحبا'],
                        ],
                    ],
                ],
            ],
        ]);

        $en = $service->presentPublic('en');
        $this->assertSame('Hello EN', $en[0]['settings']['headline']);
        $this->assertArrayNotHasKey('translations', $en[0]['settings']);

        $ar = $service->presentPublic('ar');
        $this->assertSame('مرحبا', $ar[0]['settings']['headline']);
        $this->assertArrayNotHasKey('translations', $ar[0]['settings']);
    }

    public function test_footer_defaults_hardcoded_mode(): void
    {
        $doc = (new FooterBuilderService)->defaultDocument();
        $this->assertSame('hardcoded', $doc['mode']);
        $this->assertTrue($doc['zones']['main']['enabled']);
        $this->assertCount(4, $doc['zones']['main']['columns']);
    }

    public function test_footer_public_hardcoded_omits_zones(): void
    {
        $service = new FooterBuilderService;
        $service->save($service->defaultDocument());
        $public = $service->presentPublic();
        $this->assertSame(['mode' => 'hardcoded'], $public);
    }

    public function test_footer_builder_mode_presents_enabled_blocks(): void
    {
        $service = new FooterBuilderService;
        $doc = $service->defaultDocument();
        $doc['mode'] = 'builder';
        $service->save($doc);
        $public = $service->presentPublic();
        $this->assertSame('builder', $public['mode']);
        $this->assertArrayHasKey('main', $public['zones']);
        $this->assertNotEmpty($public['zones']['main']['columns']);
    }

    public function test_registry_for_admin_lists_homepage_types(): void
    {
        $admin = HomepageSectionRegistry::forAdmin();
        $types = array_column($admin, 'type');
        $this->assertContains('hero', $types);
        $this->assertContains('cta', $types);

        $hero = collect($admin)->firstWhere('type', 'hero');
        $this->assertIsArray($hero);
        $this->assertNotEmpty($hero['settings_fields']);
        $fieldKeys = array_column($hero['settings_fields'], 'key');
        $this->assertContains('image', $fieldKeys);
        $this->assertContains('headline', $fieldKeys);
        $headline = collect($hero['settings_fields'])->firstWhere('key', 'headline');
        $this->assertTrue($headline['translatable'] ?? false);
        $image = collect($hero['settings_fields'])->firstWhere('key', 'image');
        $this->assertFalse($image['translatable'] ?? true);
    }
}
