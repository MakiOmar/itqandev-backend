<?php

namespace App\Support;

use App\Models\SeoMeta;
use Illuminate\Support\Collection;

/**
 * Resolve which SEO meta row applies for a localized content view.
 */
final class SeoMetaPresenter
{
    /**
     * Only the presentation locale's row: another language's title or description must never
     * appear in this locale's head (empty fields fall back to the SEO defaults instead).
     * Rows saved without a locale belong to the record's primary content locale.
     *
     * @param  Collection<int, SeoMeta>|null  $metas
     */
    public static function pickLocalized(?Collection $metas, ?string $presentationLocale, string $primaryContentLocale): ?SeoMeta
    {
        if ($metas === null || $metas->isEmpty()) {
            return null;
        }

        $primary = strtolower(trim($primaryContentLocale));
        $present = is_string($presentationLocale) ? strtolower(trim($presentationLocale)) : '';
        if ($present === '') {
            $present = $primary;
        }

        $exact = $metas->first(fn (SeoMeta $m) => strtolower(trim((string) $m->locale)) === $present);
        if ($exact !== null || $present !== $primary) {
            return $exact;
        }

        return $metas->first(fn (SeoMeta $m) => trim((string) $m->locale) === '');
    }

    /** @return array<string, mixed>|null */
    public static function toPublicSnippet(?SeoMeta $meta): ?array
    {
        if (! $meta instanceof SeoMeta) {
            return null;
        }

        return [
            'locale' => $meta->locale,
            'meta_title' => $meta->meta_title,
            'meta_description' => $meta->meta_description,
            'canonical_url' => $meta->canonical_url,
            'og_title' => $meta->og_title,
            'og_description' => $meta->og_description,
            'og_image' => $meta->og_image,
            'twitter_card' => $meta->twitter_card,
            'schema' => $meta->schema,
        ];
    }
}
