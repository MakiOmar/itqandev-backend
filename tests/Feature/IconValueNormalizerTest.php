<?php

namespace Tests\Feature;

use App\Services\Appearance\ControlNormalizer;
use App\Services\Appearance\IconValueNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IconValueNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private const STAR = '<path fill="none" stroke="currentColor" stroke-width="2" d="M12 2l3 7h7l-5.5 4 2 7-6.5-4.5L5.5 20l2-7L2 9h7z"/>';

    public function test_keeps_a_safe_bundled_icon(): void
    {
        $out = IconValueNormalizer::normalize([
            'library' => 'lucide', 'name' => 'star', 'body' => self::STAR, 'view_box' => '0 0 24 24',
        ]);

        $this->assertSame(['library' => 'lucide', 'name' => 'star', 'body' => self::STAR, 'view_box' => '0 0 24 24'], $out);
    }

    public function test_keeps_a_hex_icon_color(): void
    {
        $out = IconValueNormalizer::normalize([
            'library' => 'lucide', 'name' => 'star', 'body' => self::STAR, 'color' => ' #FF0066 ',
        ]);

        $this->assertIsArray($out);
        $this->assertSame('#ff0066', $out['color']);
    }

    /**
     * @dataProvider unsafeColors
     */
    public function test_drops_non_hex_icon_colors(string $color): void
    {
        $out = IconValueNormalizer::normalize([
            'library' => 'lucide', 'name' => 'star', 'body' => self::STAR, 'color' => $color,
        ]);

        $this->assertIsArray($out);
        $this->assertArrayNotHasKey('color', $out);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeColors(): array
    {
        return [
            'css injection' => ['red;background:url(https://evil.test/x)'],
            'named colour' => ['red'],
            'bad length' => ['#12345'],
            'url' => ['url(https://evil.test/a)'],
        ];
    }

    public function test_keeps_grouped_markup_and_defaults_view_box(): void
    {
        $body = '<g fill="none" stroke="currentColor"><path d="m22 7l-9 5L2 7"/><rect width="20" height="16" x="2" y="4" rx="2"/></g>';
        $out = IconValueNormalizer::normalize(['library' => 'lucide', 'name' => 'mail', 'body' => $body, 'view_box' => 'bad']);

        $this->assertIsArray($out);
        $this->assertSame('0 0 24 24', $out['view_box']);
    }

    /**
     * @dataProvider unsafeBodies
     */
    public function test_rejects_unsafe_markup(string $body): void
    {
        $this->assertSame('', IconValueNormalizer::normalize(['library' => 'lucide', 'name' => 'x', 'body' => $body]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeBodies(): array
    {
        return [
            'script' => ['<script>alert(1)</script>'],
            'event handler' => ['<path d="M0 0" onload="alert(1)"/>'],
            'foreign object' => ['<foreignObject><div>x</div></foreignObject>'],
            'external image' => ['<image href="https://evil.test/x.png"/>'],
            'use href' => ['<use href="https://cdn.test/sprite.svg#a"/>'],
            'css url' => ['<path d="M0 0" fill="url(https://evil.test/a)"/>'],
            'style attribute' => ['<path d="M0 0" style="fill:red"/>'],
            'loose text' => ['hello <path d="M0 0"/>'],
        ];
    }

    public function test_rejects_unknown_library_and_bad_legacy_names(): void
    {
        $this->assertSame('', IconValueNormalizer::normalize(['library' => 'fontawesome', 'name' => 'star', 'body' => self::STAR]));
        $this->assertSame('star', IconValueNormalizer::normalize(' Star '));
        $this->assertSame('', IconValueNormalizer::normalize('<svg>'));
    }

    public function test_upload_requires_existing_media_and_ignores_client_url(): void
    {
        $this->assertSame('', IconValueNormalizer::normalize([
            'library' => 'svg', 'media_id' => 999999, 'url' => 'https://third-party.test/icon.svg',
        ]));
    }

    public function test_control_normalizer_applies_icon_rules(): void
    {
        $fields = [['key' => 'icon', 'type' => 'icon']];
        $out = ControlNormalizer::normalizeSettings(
            ['icon' => ['library' => 'lucide', 'name' => 'x', 'body' => '<script>alert(1)</script>']],
            $fields,
        );

        $this->assertSame('', $out['icon']);
    }
}
