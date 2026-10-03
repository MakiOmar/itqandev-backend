<?php

namespace Tests\Feature;

use App\Services\Appearance\BuilderStyleDocument;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\WidgetRegistry;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextLogoWidgetTest extends TestCase
{
    use RefreshDatabase;

    private const ICON = ['library' => 'lucide', 'name' => 'check', 'body' => '<path fill="none" stroke="currentColor" d="M20 6L9 17l-5-5"/>', 'view_box' => '0 0 24 24'];

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'Arabic', 'rtl' => true],
            ],
        ]);
    }

    public function test_registry_defines_text_logo_with_translatable_copy(): void
    {
        $widget = WidgetRegistry::all()['text_logo'];

        $this->assertSame('Typography', $widget['category']);
        $this->assertStringContainsString('[[', $widget['default_settings']['name']);
        $translatable = WidgetRegistry::translatableKeys('text_logo');
        foreach (['name', 'tagline', 'mark_text'] as $key) {
            $this->assertContains($key, $translatable);
        }
        foreach (['mark_type', 'mark_icon', 'layout', 'link_url'] as $key) {
            $this->assertNotContains($key, $translatable);
        }
    }

    public function test_normalize_sanitizes_mark_icon(): void
    {
        $valid = $this->normalizedBlock(['name' => 'ITQAN[[DEV]]', 'mark_type' => 'icon', 'mark_icon' => self::ICON]);
        $this->assertSame(self::ICON, $valid['settings']['mark_icon']);
        $this->assertSame('ITQAN[[DEV]]', $valid['settings']['name']);

        $unsafe = $this->normalizedBlock([
            'mark_icon' => ['library' => 'lucide', 'name' => 'x', 'body' => '<script>alert(1)</script>'],
        ]);
        $this->assertSame('', $unsafe['settings']['mark_icon']);
    }

    public function test_public_render_overlays_translated_name(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([[
            'type' => 'text_logo',
            'settings' => [
                'name' => 'ITQAN[[DEV]]',
                'tagline' => 'Web & App Development',
                'translations' => ['ar' => ['name' => 'إتقان[[ديف]]', 'tagline' => 'تطوير الويب والتطبيقات']],
            ],
        ]]);

        $ar = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'ar'));
        $this->assertSame('إتقان[[ديف]]', $ar['settings']['name']);
        $this->assertSame('تطوير الويب والتطبيقات', $ar['settings']['tagline']);

        $en = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'en'));
        $this->assertSame('ITQAN[[DEV]]', $en['settings']['name']);
    }

    public function test_style_document_keeps_logo_keys_and_dark_colours(): void
    {
        $styles = BuilderStyleDocument::normalize([
            'desktop' => [
                'logo_mark_bg' => '#F5A623',
                'logo_mark_bg_end' => '#fde68a',
                'logo_mark_size' => ['value' => 40, 'unit' => 'px'],
                'logo_mark_font_weight' => '900',
                'logo_name_transform' => 'uppercase',
                'logo_tagline_spacing' => ['value' => -4, 'unit' => 'px'],
                'logo_name_font_weight' => '950',
                'logo_name_color' => 'url(javascript:alert(1))',
            ],
            'dark' => ['logo_name_color' => '#ffffff', 'logo_mark_size' => ['value' => 10, 'unit' => 'px']],
        ]);

        $desktop = $styles['desktop'];
        $this->assertSame('#f5a623', $desktop['logo_mark_bg']);
        $this->assertSame(['value' => 40.0, 'unit' => 'px'], $desktop['logo_mark_size']);
        $this->assertSame('900', $desktop['logo_mark_font_weight']);
        $this->assertSame('uppercase', $desktop['logo_name_transform']);
        $this->assertSame(['value' => -4.0, 'unit' => 'px'], $desktop['logo_tagline_spacing']);
        $this->assertArrayNotHasKey('logo_name_font_weight', $desktop);
        $this->assertArrayNotHasKey('logo_name_color', $desktop);
        $this->assertSame(['logo_name_color' => '#ffffff'], $styles['dark']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizedBlock(array $settings): array
    {
        return $this->firstBlock(PageLayoutDocument::normalizeSectionsForPages([
            ['type' => 'text_logo', 'settings' => $settings],
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function firstBlock(array $sections): array
    {
        return $sections[0]['rows'][0]['columns'][0]['blocks'][0];
    }
}
