<?php

namespace App\Services\ContentExport;

use App\Support\SiteLanguages;
use Illuminate\Support\Facades\Cache;

final class TranslatableListCacheInvalidator
{
    public static function flushPrefix(string $prefix): void
    {
        Cache::forget($prefix);
        Cache::forget($prefix.':loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = is_array($row) && isset($row['code']) ? (string) $row['code'] : '';
            if ($code !== '') {
                Cache::forget($prefix.':loc:'.strtolower($code));
            }
        }

        // Legacy list keys used before aligning controllers with this prefix (import/export invalidation).
        if ($prefix === 'skills:list:v3:json') {
            self::flushLegacyLocaleKeys('skills:list:v3:loc');
        }
        if ($prefix === 'services:list:v3:json') {
            self::flushLegacyLocaleKeys('services:list:v3:loc');
        }
    }

    private static function flushLegacyLocaleKeys(string $legacyBase): void
    {
        Cache::forget($legacyBase.':none');
        foreach (SiteLanguages::all() as $row) {
            $code = is_array($row) && isset($row['code']) ? strtolower(trim((string) $row['code'])) : '';
            if ($code !== '') {
                Cache::forget($legacyBase.':'.$code);
            }
        }
    }
}
