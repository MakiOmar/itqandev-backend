<?php

namespace App\Support\Media;

use App\Models\AppMedia;
use App\Support\SiteLanguages;

/**
 * Resolve locale-aware alt / description for a media library asset.
 * Primary lives on media columns; secondary locales use media_translations.
 */
final class LocalizedMediaMeta
{
    public static function alt(AppMedia $media, ?string $locale = null, ?string $defaultLocale = null): ?string
    {
        $meta = self::resolve($media, $locale, $defaultLocale);

        return $meta['alt_text'];
    }

    public static function description(AppMedia $media, ?string $locale = null, ?string $defaultLocale = null): ?string
    {
        $meta = self::resolve($media, $locale, $defaultLocale);

        return $meta['description'];
    }

    /**
     * @return array{alt_text: ?string, description: ?string}
     */
    public static function resolve(AppMedia $media, ?string $locale = null, ?string $defaultLocale = null): array
    {
        $defaultLocale = strtolower(trim((string) ($defaultLocale ?: SiteLanguages::defaultCode())));
        $locale = strtolower(trim((string) ($locale ?: $defaultLocale)));

        $primaryAlt = self::nullableString($media->alt_text);
        $primaryDescription = self::nullableString($media->description);

        if ($locale === '' || $locale === $defaultLocale) {
            return [
                'alt_text' => $primaryAlt,
                'description' => $primaryDescription,
            ];
        }

        $row = null;
        if ($media->relationLoaded('translations')) {
            $row = $media->translations->first(
                static fn ($t) => strtolower((string) $t->locale) === $locale
            );
        } else {
            $row = $media->translations()->where('locale', $locale)->first();
        }

        $translatedAlt = $row ? self::nullableString($row->alt_text) : null;
        $translatedDescription = $row ? self::nullableString($row->description) : null;

        return [
            'alt_text' => $translatedAlt ?? $primaryAlt,
            'description' => $translatedDescription ?? $primaryDescription,
        ];
    }

    /**
     * @return array<string, array{alt_text: ?string, description: ?string}>
     */
    public static function translationsBag(AppMedia $media): array
    {
        if (! $media->relationLoaded('translations')) {
            $media->load('translations');
        }

        $out = [];
        foreach ($media->translations as $row) {
            $code = strtolower(trim((string) $row->locale));
            if ($code === '') {
                continue;
            }
            $out[$code] = [
                'alt_text' => self::nullableString($row->alt_text),
                'description' => self::nullableString($row->description),
            ];
        }

        return $out;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
