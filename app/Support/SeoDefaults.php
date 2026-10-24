<?php

namespace App\Support;

/**
 * Site-wide SEO defaults (`seo_defaults` in project settings): a global block plus optional
 * per-content-type blocks. Public heads resolve item SEO first, then the content, then the
 * type block, then the global block (see docs/CONFIGURATION.md → SEO defaults).
 */
final class SeoDefaults
{
    public const SETTINGS_KEY = 'seo_defaults';

    /** Content types that can override the global defaults. */
    public const TYPES = ['page', 'blog_post', 'project', 'service'];

    public const TWITTER_CARDS = ['summary', 'summary_large_image'];

    public const TITLE_TEMPLATE_MAX = 160;

    public const DESCRIPTION_MAX = 320;

    /** Fields editors translate per locale; images and card types are shared by every locale. */
    public const LOCALIZED_FIELDS = ['title_template', 'meta_description'];

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        $types = [];
        foreach (self::TYPES as $type) {
            $types[$type] = self::emptyBlock();
        }

        return self::emptyBlock() + ['types' => $types];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $rawTypes = is_array($raw['types'] ?? null) ? $raw['types'] : [];

        $types = [];
        foreach (self::TYPES as $type) {
            $types[$type] = self::normalizeBlock($rawTypes[$type] ?? null);
        }

        return self::normalizeBlock($raw) + ['types' => $types];
    }

    /**
     * Translation overlay for one locale: only the localized text fields.
     *
     * @return array<string, mixed>
     */
    public static function normalizeTranslation(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = self::localizedFields($raw);
        $rawTypes = is_array($raw['types'] ?? null) ? $raw['types'] : [];
        foreach (self::TYPES as $type) {
            if (is_array($rawTypes[$type] ?? null)) {
                $fields = self::localizedFields($rawTypes[$type]);
                if ($fields !== []) {
                    $out['types'][$type] = $fields;
                }
            }
        }

        return $out;
    }

    /**
     * Defaults for a non-primary locale. Descriptions come only from that locale's translation
     * (no other-language text); title templates are format patterns and fall back to the
     * primary template when the locale has none.
     *
     * @param  array<string, mixed>  $defaults  Normalized primary-locale defaults.
     * @param  array<string, mixed>  $overlay  Normalized translation for the locale.
     * @return array<string, mixed>
     */
    public static function localize(array $defaults, array $overlay): array
    {
        $out = self::localizeBlock($defaults, $overlay);
        foreach (self::TYPES as $type) {
            $out['types'][$type] = self::localizeBlock(
                is_array($defaults['types'][$type] ?? null) ? $defaults['types'][$type] : self::emptyBlock(),
                is_array($overlay['types'][$type] ?? null) ? $overlay['types'][$type] : [],
            );
        }

        return $out;
    }

    /**
     * Public payload: same-site image paths become absolute so marketing heads can use them as-is.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function forPublic(array $defaults): array
    {
        $out = self::normalize($defaults);
        $out['og_image'] = self::absoluteImage($out['og_image']);
        foreach (self::TYPES as $type) {
            $out['types'][$type]['og_image'] = self::absoluteImage($out['types'][$type]['og_image']);
        }

        return $out;
    }

    /**
     * @return array{title_template: string, meta_description: string, og_image: ?string, twitter_card: string}
     */
    private static function emptyBlock(): array
    {
        return ['title_template' => '', 'meta_description' => '', 'og_image' => null, 'twitter_card' => ''];
    }

    /**
     * @return array{title_template: string, meta_description: string, og_image: ?string, twitter_card: string}
     */
    private static function normalizeBlock(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $card = is_string($raw['twitter_card'] ?? null) ? trim($raw['twitter_card']) : '';

        return [
            'title_template' => self::plainText($raw['title_template'] ?? null, self::TITLE_TEMPLATE_MAX),
            'meta_description' => self::plainText($raw['meta_description'] ?? null, self::DESCRIPTION_MAX),
            'og_image' => self::safeImageUrl($raw['og_image'] ?? null),
            'twitter_card' => in_array($card, self::TWITTER_CARDS, true) ? $card : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private static function localizedFields(array $raw): array
    {
        $out = [];
        if (array_key_exists('title_template', $raw)) {
            $out['title_template'] = self::plainText($raw['title_template'], self::TITLE_TEMPLATE_MAX);
        }
        if (array_key_exists('meta_description', $raw)) {
            $out['meta_description'] = self::plainText($raw['meta_description'], self::DESCRIPTION_MAX);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private static function localizeBlock(array $block, array $overlay): array
    {
        $template = is_string($overlay['title_template'] ?? null) ? trim($overlay['title_template']) : '';
        $description = is_string($overlay['meta_description'] ?? null) ? trim($overlay['meta_description']) : '';

        return [
            'title_template' => $template !== '' ? $template : (string) ($block['title_template'] ?? ''),
            'meta_description' => $description,
            'og_image' => $block['og_image'] ?? null,
            'twitter_card' => (string) ($block['twitter_card'] ?? ''),
        ];
    }

    private static function plainText(mixed $value, int $max): string
    {
        if (! is_string($value)) {
            return '';
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return mb_substr($text, 0, $max);
    }

    /** Only http(s) URLs or same-site absolute paths; anything else (javascript:, data:, //host) is dropped. */
    private static function safeImageUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $url = trim($value);
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return null;
    }

    private static function absoluteImage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
