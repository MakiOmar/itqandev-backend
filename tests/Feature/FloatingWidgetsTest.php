<?php

namespace Tests\Feature;

use App\Services\Appearance\BuilderStyleDocument;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\WidgetRegistry;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloatingWidgetsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_registry_defines_theme_switch_and_contact_float(): void
    {
        $all = WidgetRegistry::all();

        $this->assertSame('Actions', $all['theme_switch']['category']);
        $this->assertSame('Actions', $all['contact_float']['category']);
        $this->assertSame(['light_label', 'dark_label'], WidgetRegistry::translatableKeys('theme_switch'));

        $contactKeys = WidgetRegistry::translatableKeys('contact_float');
        foreach (['launcher_label', 'panel_title', 'panel_text', 'whatsapp_label', 'whatsapp_message', 'message_label'] as $key) {
            $this->assertContains($key, $contactKeys);
        }
        foreach (['whatsapp_number', 'form_slug', 'corner', 'launcher_icon'] as $key) {
            $this->assertNotContains($key, $contactKeys);
        }
    }

    public function test_normalize_keeps_only_whatsapp_digits_and_sanitizes_icons(): void
    {
        $block = $this->normalizedBlock('contact_float', [
            'whatsapp_number' => '+966 (55) 123-4567<script>',
            'launcher_icon' => ['library' => 'lucide', 'name' => 'x', 'body' => '<script>alert(1)</script>'],
        ]);

        $this->assertSame('966551234567', $block['settings']['whatsapp_number']);
        $this->assertSame('', $block['settings']['launcher_icon']);
    }

    public function test_whatsapp_number_is_capped_at_e164_length(): void
    {
        $block = $this->normalizedBlock('contact_float', ['whatsapp_number' => str_repeat('9', 30)]);

        $this->assertSame(str_repeat('9', 15), $block['settings']['whatsapp_number']);
    }

    public function test_public_render_overlays_translated_copy(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([[
            'type' => 'contact_float',
            'settings' => [
                'panel_title' => 'Contact us',
                'whatsapp_number' => '966551234567',
                'translations' => ['ar' => ['panel_title' => 'تواصل معنا']],
            ],
        ]]);

        $ar = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'ar'));
        $this->assertSame('تواصل معنا', $ar['settings']['panel_title']);
        $this->assertSame('966551234567', $ar['settings']['whatsapp_number']);

        $en = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'en'));
        $this->assertSame('Contact us', $en['settings']['panel_title']);
    }

    public function test_style_document_keeps_floating_keys_and_dark_colours(): void
    {
        $styles = BuilderStyleDocument::normalize([
            'desktop' => [
                'toggle_bg' => '#FFFFFF',
                'toggle_size' => ['value' => 48, 'unit' => 'px'],
                'toggle_font_weight' => '600',
                'launcher_bg' => '#25D366',
                'launcher_shadow' => ['color' => '#00000040', 'h' => 0, 'v' => 4, 'blur' => 12, 'spread' => 0],
                'panel_width' => ['value' => 360, 'unit' => 'px'],
                'whatsapp_bg' => 'url(javascript:alert(1))',
                'launcher_font_weight' => '950',
            ],
            'dark' => ['panel_bg' => '#0f172a', 'panel_width' => ['value' => 10, 'unit' => 'px']],
        ]);

        $desktop = $styles['desktop'];
        $this->assertSame('#ffffff', $desktop['toggle_bg']);
        $this->assertSame(['value' => 48.0, 'unit' => 'px'], $desktop['toggle_size']);
        $this->assertSame('600', $desktop['toggle_font_weight']);
        $this->assertSame('#25d366', $desktop['launcher_bg']);
        $this->assertArrayHasKey('launcher_shadow', $desktop);
        $this->assertSame(['value' => 360.0, 'unit' => 'px'], $desktop['panel_width']);
        $this->assertArrayNotHasKey('whatsapp_bg', $desktop);
        $this->assertArrayNotHasKey('launcher_font_weight', $desktop);
        $this->assertSame(['panel_bg' => '#0f172a'], $styles['dark']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizedBlock(string $type, array $settings): array
    {
        return $this->firstBlock(PageLayoutDocument::normalizeSectionsForPages([
            ['type' => $type, 'settings' => $settings],
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
