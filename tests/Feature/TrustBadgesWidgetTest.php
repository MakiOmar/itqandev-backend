<?php

namespace Tests\Feature;

use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\PageLeafRegistry;
use App\Services\Appearance\WidgetRegistry;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustBadgesWidgetTest extends TestCase
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

    public function test_registry_defines_shared_badges_and_layout_options(): void
    {
        $widget = WidgetRegistry::all()['trust_badges'];
        $defaults = $widget['default_settings'];

        $this->assertCount(3, $defaults['badges']);
        $this->assertSame('stacked', $defaults['mobile_layout']);
        $this->assertSame('inline', $defaults['tablet_layout']);
        $this->assertSame(['badges' => ['text']], PageLeafRegistry::sharedRepeaterKeys('widget', 'trust_badges'));
        $this->assertNotContains('badges', WidgetRegistry::translatableKeys('trust_badges'));

        $style = collect($widget['settings_fields'])->firstWhere('key', 'divider_style');
        $this->assertSame(['solid', 'dashed', 'dotted', 'dash_dot'], array_column($style['options'], 'value'));
    }

    public function test_normalize_assigns_ids_sanitizes_icons_and_prunes_translations(): void
    {
        $block = $this->normalizedBlock([
            'badges' => [
                ['id' => 'itm_one', 'icon' => self::ICON, 'text' => 'Secure'],
                ['icon' => ['library' => 'lucide', 'name' => 'x', 'body' => '<script>alert(1)</script>'], 'text' => 'No id'],
            ],
            'translations' => [
                'ar' => ['badges' => [
                    'itm_one' => ['text' => '<b>آمن</b>', 'icon' => 'ignored'],
                    'itm_gone' => ['text' => 'deleted row'],
                ]],
            ],
        ]);

        $badges = $block['settings']['badges'];
        $this->assertSame('itm_one', $badges[0]['id']);
        $this->assertMatchesRegularExpression('/^itm_[a-z0-9]{8}$/', $badges[1]['id']);
        $this->assertSame(self::ICON, $badges[0]['icon']);
        $this->assertSame('', $badges[1]['icon']);
        $this->assertSame(['itm_one' => ['text' => 'آمن']], $block['settings']['translations']['ar']['badges']);
    }

    public function test_public_render_overlays_translated_text_by_row_id(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([[
            'type' => 'trust_badges',
            'settings' => [
                'badges' => [
                    ['id' => 'itm_one', 'icon' => self::ICON, 'text' => 'Secure'],
                    ['id' => 'itm_two', 'icon' => self::ICON, 'text' => 'Fast'],
                ],
                'translations' => ['ar' => ['badges' => ['itm_one' => ['text' => 'آمن']]]],
            ],
        ]]);

        $ar = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'ar'));
        $this->assertSame(['آمن', 'Fast'], array_column($ar['settings']['badges'], 'text'));
        $this->assertSame(self::ICON, $ar['settings']['badges'][0]['icon']);

        $en = $this->firstBlock(PageLayoutDocument::presentPublicForPages($sections, 'en'));
        $this->assertSame(['Secure', 'Fast'], array_column($en['settings']['badges'], 'text'));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizedBlock(array $settings): array
    {
        return $this->firstBlock(PageLayoutDocument::normalizeSectionsForPages([
            ['type' => 'trust_badges', 'settings' => $settings],
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
