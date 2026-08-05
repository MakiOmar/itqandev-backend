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

    public function test_footer_defaults_use_layout_sections(): void
    {
        $doc = (new FooterBuilderService)->defaultDocument();
        $this->assertArrayHasKey('sections', $doc);
        $this->assertNotEmpty($doc['sections']);
        $this->assertSame('layout', $doc['sections'][0]['type']);
        $json = json_encode($doc);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('footer_brand', $json);
        $this->assertStringContainsString('footer_copyright', $json);
    }

    public function test_footer_public_presents_layout_sections(): void
    {
        $service = new FooterBuilderService;
        $service->save($service->defaultDocument());
        $public = $service->presentPublic();
        $this->assertArrayHasKey('sections', $public);
        $this->assertNotEmpty($public['sections']);
        $this->assertSame('layout', $public['sections'][0]['type']);
    }

    public function test_header_defaults_and_public_include_menu_kit(): void
    {
        $header = new \App\Services\Appearance\HeaderBuilderService;
        $doc = $header->defaultDocument();
        $this->assertNotEmpty($doc['sections']);
        $json = json_encode($doc);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('header_menu', $json);
        $this->assertStringContainsString('header_brand', $json);

        $header->save($doc);
        $public = $header->presentPublic('en');
        $this->assertArrayHasKey('sections', $public);
        $this->assertNotEmpty($public['sections']);
    }

    public function test_legacy_footer_zones_migrate_to_layout(): void
    {
        $legacy = [
            'mode' => 'builder',
            'zones' => [
                'main' => [
                    'enabled' => true,
                    'columns' => [
                        [
                            'id' => 'c1',
                            'span' => 4,
                            'blocks' => [
                                [
                                    'id' => 'b1',
                                    'type' => 'brand',
                                    'enabled' => true,
                                    'settings' => ['tagline' => 'Hello'],
                                ],
                            ],
                        ],
                    ],
                ],
                'top' => ['enabled' => false, 'columns' => []],
                'bottom' => ['enabled' => false, 'columns' => []],
            ],
        ];
        $migrated = \App\Services\Appearance\ChromeLayoutSupport::migrateLegacyFooterZones($legacy);
        $this->assertNotNull($migrated);
        $this->assertArrayHasKey('sections', $migrated);
        $this->assertNotEmpty($migrated['sections']);
        $json = json_encode($migrated);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('footer_brand', $json);
    }

    public function test_registry_includes_chrome_kits(): void
    {
        $types = array_column(\App\Services\Appearance\KitRegistry::forAdmin(), 'type');
        $this->assertContains('header_menu', $types);
        $this->assertContains('footer_brand', $types);
        $this->assertContains('footer_copyright', $types);
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
        $this->assertTrue($image['translatable'] ?? false);
    }

    public function test_hero_save_normalizes_viewport_and_floating_icons(): void
    {
        $service = new HomepageBuilderService;
        $saved = $service->save([
            'sections' => [
                [
                    'type' => 'hero',
                    'enabled' => true,
                    'settings' => [
                        'full_viewport' => '1',
                        'nav_top_space' => 240,
                        'watermark_enabled' => true,
                        'watermark_text' => 'Brand',
                        'floating_icons_enabled' => true,
                        'floating_icons' => [
                            [
                                'id' => 'icon_a',
                                'enabled' => true,
                                'media_id' => 5,
                                'motion' => 'diagonal',
                                'x' => 10,
                                'y' => 20,
                                'size' => 48,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $settings = $saved['sections'][0]['settings'];
        $this->assertTrue($settings['full_viewport']);
        $this->assertSame(200, $settings['nav_top_space']);
        $this->assertTrue($settings['watermark_enabled']);
        $this->assertTrue($settings['floating_icons_enabled']);
        $this->assertCount(1, $settings['floating_icons']);
        $this->assertSame('diagonal', $settings['floating_icons'][0]['motion']);
        $this->assertSame(5, $settings['floating_icons'][0]['media_id']);

        $fieldKeys = array_column(
            collect(HomepageSectionRegistry::forAdmin())->firstWhere('type', 'hero')['settings_fields'],
            'key'
        );
        $this->assertContains('full_viewport', $fieldKeys);
        $this->assertContains('floating_icons', $fieldKeys);
        $this->assertContains('watermark_text', $fieldKeys);
        $this->assertContains('particles_enabled', $fieldKeys);
        $this->assertContains('particles_color', $fieldKeys);
    }
}
