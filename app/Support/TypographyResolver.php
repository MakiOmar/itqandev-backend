<?php

namespace App\Support;

use App\Models\Font;

/**
 * Resolves public typography payload from project settings + fonts table.
 */
final class TypographyResolver
{
    public const MODE_SYSTEM = 'system';

    public const MODE_CUSTOM = 'custom';

    private const GOOGLE_CSS_LTR = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap';

    private const GOOGLE_CSS_RTL = 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap';

    private const FALLBACK_LTR = "Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

    private const FALLBACK_RTL = "Cairo, 'Segoe UI', Tahoma, Arial, sans-serif";

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function resolveFromSettings(array $settings): array
    {
        $mode = self::normalizeMode($settings['font_mode'] ?? null);
        $ltrId = self::normalizeFontId($settings['font_ltr_id'] ?? null);
        $rtlId = self::normalizeFontId($settings['font_rtl_id'] ?? null);

        if ($mode === self::MODE_CUSTOM) {
            $ltrFont = $ltrId ? Font::query()->find($ltrId) : null;
            $rtlFont = $rtlId ? Font::query()->find($rtlId) : null;

            return [
                'mode' => self::MODE_CUSTOM,
                'ltr' => $ltrFont instanceof Font
                    ? self::faceFromFont($ltrFont, self::FALLBACK_LTR)
                    : self::systemFace(true),
                'rtl' => $rtlFont instanceof Font
                    ? self::faceFromFont($rtlFont, self::FALLBACK_RTL)
                    : self::systemFace(false),
            ];
        }

        return [
            'mode' => self::MODE_SYSTEM,
            'ltr' => self::systemFace(true),
            'rtl' => self::systemFace(false),
        ];
    }

    public static function normalizeMode(mixed $raw): string
    {
        $mode = strtolower(trim((string) ($raw ?? '')));

        return $mode === self::MODE_CUSTOM ? self::MODE_CUSTOM : self::MODE_SYSTEM;
    }

    public static function normalizeFontId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function systemFace(bool $ltr): array
    {
        return [
            'css_family' => $ltr ? 'Inter' : 'Cairo',
            'fallback_stack' => $ltr ? self::FALLBACK_LTR : self::FALLBACK_RTL,
            'google_css_href' => $ltr ? self::GOOGLE_CSS_LTR : self::GOOGLE_CSS_RTL,
            'sources' => new \stdClass,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faceFromFont(?Font $font, string $fallbackStack): array
    {
        if (! $font instanceof Font) {
            return [
                'css_family' => 'system-ui',
                'fallback_stack' => $fallbackStack,
                'google_css_href' => null,
                'sources' => (object) [],
            ];
        }

        $sources = [];
        foreach ($font->sourceMap() as $format => $url) {
            if (is_string($url) && trim($url) !== '') {
                $sources[$format] = $url;
            }
        }

        return [
            'css_family' => $font->css_family,
            'fallback_stack' => self::stackWithFamily($font->css_family, $fallbackStack),
            'google_css_href' => null,
            'sources' => (object) $sources,
        ];
    }

    private static function stackWithFamily(string $cssFamily, string $baseStack): string
    {
        $family = trim($cssFamily);
        if ($family === '') {
            return $baseStack;
        }
        if (str_starts_with(strtolower($baseStack), strtolower($family))) {
            return $baseStack;
        }

        return "'{$family}', {$baseStack}";
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function isFontReferencedInSettings(array $settings, int $fontId): bool
    {
        return self::normalizeFontId($settings['font_ltr_id'] ?? null) === $fontId
            || self::normalizeFontId($settings['font_rtl_id'] ?? null) === $fontId;
    }
}
