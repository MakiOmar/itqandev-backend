<?php

namespace App\Support;

use App\Services\PublicMarketingShellService;
use Illuminate\Support\Facades\Cache;

final class MarketingSettingsCache
{
    public const SETTINGS_CACHE_KEY = 'project-settings';

    public static function forgetAll(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
        PublicMarketingShellService::forgetShellCaches();
    }
}
