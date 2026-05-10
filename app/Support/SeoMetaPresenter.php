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
     * @param  Collection<int, SeoMeta>|null  $metas
     */
    public static function pickLocalized(?Collection $metas, ?string $presentationLocale, string $primaryContentLocale): ?SeoMeta
    {
        if ($metas === null || $metas->isEmpty()) {
            return null;
        }

        $attempts = [];
        foreach ([$presentationLocale, $primaryContentLocale] as $code) {
            $c = is_string($code) ? strtolower(trim($code)) : '';
            if ($c !== '') {
                $attempts[$c] = true;
            }
        }

        foreach (array_keys($attempts) as $code) {
            $found = $metas->first(fn (SeoMeta $m) => strtolower((string) $m->locale) === $code);
            if ($found) {
                return $found;
            }
        }

        return $metas->first();
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
        ];
    }
}
