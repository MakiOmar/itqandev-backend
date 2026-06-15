<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Font;
use App\Services\ActivityLogService;
use App\Support\FeatureModules;
use App\Support\MarketingSettingsCache;
use App\Support\SiteLanguages;
use App\Support\SiteSettingsPresenter;
use App\Support\TranslatableContentPresenter;
use App\Support\TypographyResolver;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    private const SETTINGS_FILE_PATH = 'project-settings.json';

    private function settingsCacheTtlSeconds(): int
    {
        $ttl = (int) config('app.settings_cache_ttl', 600);

        return max(60, min($ttl, 86400));
    }

    /**
     * Attach module toggles from config/features.php for API consumers (never read from persisted JSON).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withResolvedFeatures(array $settings): array
    {
        $settings['features'] = FeatureModules::all();

        return $settings;
    }

    /**
     * Default settings payload.
     * Keeps canonical keys and compatibility aliases so older clients still work.
     *
     * @return array<string, mixed>
     */
    private function getDefaultSettings(): array
    {
        $appName = config('app.name', 'Dashboard');
        $appDescription = config('app.description', '');
        $supportEmail = config('mail.from.address', '');

        return [
            // Branding
            'name' => $appName,
            'site_name' => $appName,
            'logo' => null,
            'site_logo' => null,
            'logoDark' => null,
            'logoLight' => null,
            'logo_dark' => null,
            'logo_light' => null,
            'dark_logo' => null,
            'light_logo' => null,
            'site_logo_dark' => null,
            'site_logo_light' => null,
            'favicon' => null,
            'site_favicon' => null,
            'primaryColor' => null,
            'secondaryColor' => null,
            'primary_color' => null,
            'secondary_color' => null,

            // General settings
            'description' => $appDescription,
            'site_description' => $appDescription,
            'supportEmail' => $supportEmail,
            'site_email' => $supportEmail,
            'supportPhone' => null,
            'site_phone' => null,
            'site_address' => null,

            // Social
            'social_facebook' => null,
            'social_twitter' => null,
            'social_linkedin' => null,
            'social_instagram' => null,

            // Media
            'upload_max_size' => null,
            'media_convert_to_webp' => true,

            // Multilingual site content (admin + API)
            'site_languages' => SiteLanguages::defaults(),
            'default_locale' => 'en',

            // Typography (system = Google Inter/Cairo; custom = self-hosted fonts)
            'font_mode' => TypographyResolver::MODE_SYSTEM,
            'font_ltr_id' => null,
            'font_rtl_id' => null,

            'marketing_site_content' => [],
            'settings_translations' => [],
        ];
    }

    /**
     * Load settings from local storage file.
     *
     * @return array<string, mixed>
     */
    private function loadStoredSettings(): array
    {
        if (!Storage::disk('local')->exists(self::SETTINGS_FILE_PATH)) {
            return [];
        }

        $content = Storage::disk('local')->get(self::SETTINGS_FILE_PATH);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist settings to local storage file.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    private function saveStoredSettings(array $settings): void
    {
        Storage::disk('local')->put(
            self::SETTINGS_FILE_PATH,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Resolve first existing key from $keys.
     * Uses array_key_exists so null/empty values can intentionally overwrite old values.
     *
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     * @param mixed $fallback
     * @return mixed
     */
    private function resolveFirst(array $source, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return $source[$key];
            }
        }

        return $fallback;
    }

    /**
     * Normalize settings and keep aliases synchronized.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeSettingsPayload(array $input): array
    {
        $withoutFeatures = $input;
        unset($withoutFeatures['features']);

        $defaults = $this->getDefaultSettings();
        $settings = array_merge($defaults, $withoutFeatures);

        // Resolve aliases from raw input first, then fallback to merged defaults/settings.
        // This prevents default null keys (e.g. logo) from masking legacy alias values (e.g. site_logo).
        $siteName = $this->resolveFirst($input, ['site_name', 'name'], $settings['site_name']);
        $description = $this->resolveFirst($input, ['site_description', 'description'], $settings['site_description']);
        $siteEmail = $this->resolveFirst($input, ['site_email', 'supportEmail'], $settings['site_email']);
        $sitePhone = $this->resolveFirst($input, ['site_phone', 'supportPhone'], $settings['site_phone']);

        $logo = $this->absolutizePublicUrl($this->resolveFirst($input, ['logo', 'site_logo'], $settings['logo']));
        $logoDark = $this->absolutizePublicUrl($this->resolveFirst($input, ['logoDark', 'logo_dark', 'dark_logo', 'site_logo_dark'], $settings['logoDark']));
        $logoLight = $this->absolutizePublicUrl($this->resolveFirst($input, ['logoLight', 'logo_light', 'light_logo', 'site_logo_light'], $settings['logoLight']));
        $favicon = $this->absolutizePublicUrl($this->resolveFirst($input, ['favicon', 'site_favicon'], $settings['favicon']));
        $primaryColor = $this->resolveFirst($input, ['primaryColor', 'primary_color'], $settings['primaryColor']);
        $secondaryColor = $this->resolveFirst($input, ['secondaryColor', 'secondary_color'], $settings['secondaryColor']);

        $settings['name'] = $siteName;
        $settings['site_name'] = $siteName;
        $settings['description'] = $description;
        $settings['site_description'] = $description;
        $settings['supportEmail'] = $siteEmail;
        $settings['site_email'] = $siteEmail;
        $settings['supportPhone'] = $sitePhone;
        $settings['site_phone'] = $sitePhone;

        $settings['logo'] = $logo;
        $settings['site_logo'] = $logo;
        $settings['logoDark'] = $logoDark;
        $settings['logo_dark'] = $logoDark;
        $settings['dark_logo'] = $logoDark;
        $settings['site_logo_dark'] = $logoDark;
        $settings['logoLight'] = $logoLight;
        $settings['logo_light'] = $logoLight;
        $settings['light_logo'] = $logoLight;
        $settings['site_logo_light'] = $logoLight;
        $settings['favicon'] = $favicon;
        $settings['site_favicon'] = $favicon;
        $settings['primaryColor'] = $primaryColor;
        $settings['primary_color'] = $primaryColor;
        $settings['secondaryColor'] = $secondaryColor;
        $settings['secondary_color'] = $secondaryColor;

        $rawLangs = $this->resolveFirst($input, ['site_languages'], $settings['site_languages'] ?? []);
        if (! is_array($rawLangs)) {
            $rawLangs = [];
        }
        $settings['site_languages'] = SiteLanguages::normalizeList($rawLangs);

        $defaultLocale = $this->resolveFirst($input, ['default_locale'], $settings['default_locale'] ?? 'en');
        $defaultLocale = strtolower(trim((string) $defaultLocale));
        $codes = array_column($settings['site_languages'], 'code');
        if ($codes === [] || ! in_array($defaultLocale, $codes, true)) {
            $defaultLocale = $codes[0] ?? 'en';
        }
        $settings['default_locale'] = $defaultLocale;

        if (array_key_exists('media_convert_to_webp', $input)) {
            $settings['media_convert_to_webp'] = filter_var(
                $input['media_convert_to_webp'],
                FILTER_VALIDATE_BOOL
            );
        } elseif (! array_key_exists('media_convert_to_webp', $settings)) {
            $settings['media_convert_to_webp'] = true;
        } else {
            $settings['media_convert_to_webp'] = filter_var(
                $settings['media_convert_to_webp'],
                FILTER_VALIDATE_BOOL
            );
        }

        $fontMode = TypographyResolver::normalizeMode(
            $this->resolveFirst($input, ['font_mode'], $settings['font_mode'] ?? TypographyResolver::MODE_SYSTEM)
        );
        $settings['font_mode'] = $fontMode;

        $fontLtrId = TypographyResolver::normalizeFontId(
            $this->resolveFirst($input, ['font_ltr_id'], $settings['font_ltr_id'] ?? null)
        );
        $fontRtlId = TypographyResolver::normalizeFontId(
            $this->resolveFirst($input, ['font_rtl_id'], $settings['font_rtl_id'] ?? null)
        );

        if ($fontMode === TypographyResolver::MODE_CUSTOM) {
            if ($fontLtrId && ! Font::query()->whereKey($fontLtrId)->exists()) {
                $fontLtrId = null;
            }
            if ($fontRtlId && ! Font::query()->whereKey($fontRtlId)->exists()) {
                $fontRtlId = null;
            }
        } else {
            $fontLtrId = null;
            $fontRtlId = null;
        }

        $settings['font_ltr_id'] = $fontLtrId;
        $settings['font_rtl_id'] = $fontRtlId;

        if (array_key_exists('marketing_site_content', $input)) {
            $settings['marketing_site_content'] = is_array($input['marketing_site_content'])
                ? $input['marketing_site_content']
                : [];
        } elseif (! is_array($settings['marketing_site_content'] ?? null)) {
            $settings['marketing_site_content'] = [];
        }

        if (array_key_exists('settings_translations', $input)) {
            $rawTranslations = is_array($input['settings_translations'])
                ? $input['settings_translations']
                : [];
            $existingTranslations = is_array($settings['settings_translations'] ?? null)
                ? $settings['settings_translations']
                : [];
            $incoming = SiteSettingsPresenter::normalizeTranslationsPayload($rawTranslations);
            $settings['settings_translations'] = SiteSettingsPresenter::mergeTranslationsStorage(
                $existingTranslations,
                $incoming
            );
        } elseif (! is_array($settings['settings_translations'] ?? null)) {
            $settings['settings_translations'] = [];
        }

        return $this->withResolvedFeatures($settings);
    }

    /** Ensure stored media URLs are absolute (APP_URL) for cross-origin Qwik dev/preview. */
    private function absolutizePublicUrl(mixed $url): ?string
    {
        if ($url === null) {
            return null;
        }
        if (! is_string($url)) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return url($url);
    }

    /**
     * Convert PHP ini size string (e.g., "100M", "2G", "10485760") to bytes
     * Handles: K, M, G suffixes and plain byte values
     * 
     * @param string $sizeString
     * @return int
     */
    private function convertIniSizeToBytes(string $sizeString): int
    {
        $sizeString = trim($sizeString);
        
        // Handle empty string
        if (empty($sizeString)) {
            return 0;
        }
        
        $last = strtolower($sizeString[strlen($sizeString) - 1]);
        $value = (int) $sizeString;

        // If last character is a letter (K, M, G), multiply accordingly
        if (in_array($last, ['k', 'm', 'g'], true)) {
            switch ($last) {
                case 'g':
                    $value *= 1024;
                    // fall through
                case 'm':
                    $value *= 1024;
                    // fall through
                case 'k':
                    $value *= 1024;
            }
        }
        // Otherwise, it's already in bytes (plain number)

        return $value;
    }

    /**
     * Normalized settings from cache/storage (no request-specific keys like max_file_size).
     *
     * @return array<string, mixed>
     */
    private function loadNormalizedSettings(): array
    {
        $settings = Cache::remember(MarketingSettingsCache::SETTINGS_CACHE_KEY, $this->settingsCacheTtlSeconds(), function () {
            $stored = $this->loadStoredSettings();

            return $this->normalizeSettingsPayload($stored);
        });

        return $this->normalizeSettingsPayload(is_array($settings) ? $settings : []);
    }

    /**
     * Public marketing payload: branding + locales only (no auth). Same cache as GET /settings.
     */
    public function publicMeta(Request $request): JsonResponse
    {
        $locale = TranslatableContentPresenter::requestedPresentationLocale($request);
        if ($locale === null) {
            $queryLocale = strtolower(trim((string) $request->query('locale', '')));
            $locale = $queryLocale !== '' ? $queryLocale : null;
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildPublicMetaData($locale),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function localizedSettings(?string $locale = null): array
    {
        return SiteSettingsPresenter::apply($this->loadNormalizedSettings(), $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicMetaData(?string $locale = null): array
    {
        $settings = $this->localizedSettings($locale);

        return [
            'site_name' => $settings['site_name'] ?? null,
            'name' => $settings['name'] ?? null,
            'site_description' => $settings['site_description'] ?? null,
            'description' => $settings['description'] ?? null,
            'site_address' => $settings['site_address'] ?? null,
            'logo' => $settings['logo'] ?? null,
            'site_logo' => $settings['site_logo'] ?? null,
            'logoDark' => $settings['logoDark'] ?? null,
            'logo_dark' => $settings['logo_dark'] ?? null,
            'dark_logo' => $settings['dark_logo'] ?? null,
            'site_logo_dark' => $settings['site_logo_dark'] ?? null,
            'logoLight' => $settings['logoLight'] ?? null,
            'logo_light' => $settings['logo_light'] ?? null,
            'light_logo' => $settings['light_logo'] ?? null,
            'site_logo_light' => $settings['site_logo_light'] ?? null,
            'site_languages' => is_array($settings['site_languages'] ?? null)
                ? $settings['site_languages']
                : [],
            'default_locale' => $settings['default_locale'] ?? 'en',
            'features' => FeatureModules::all(),
            'typography' => TypographyResolver::resolveFromSettings($settings),
        ];
    }

    /**
     * Get project settings
     * Returns branding, general settings, and module toggles from config/features.php (not persisted).
     *
     * OPTIMIZATION: Cached for 5 minutes to reduce database/config lookups
     * Settings rarely change, so aggressive caching is safe
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $this->authorize('manageSettings');

        // Get actual PHP server limits (not application config)
        // These are fetched on each request to reflect real-time server config
        $uploadMaxFilesize = ini_get('upload_max_filesize') ?: '8M'; // Default fallback if ini_get fails
        $postMaxSize = ini_get('post_max_size') ?: '8M'; // Default fallback if ini_get fails

        // Convert to bytes
        $uploadMaxBytes = $this->convertIniSizeToBytes($uploadMaxFilesize);
        $postMaxBytes = $this->convertIniSizeToBytes($postMaxSize);

        // Use the smaller of the two as the effective limit
        // post_max_size must be >= upload_max_filesize for uploads to work
        $maxFileSize = min($uploadMaxBytes, $postMaxBytes);

        $settings = $this->loadNormalizedSettings();

        // Add max_file_size from PHP ini_get() (not cached, always current)
        // This reflects the actual server limits (upload_max_filesize, post_max_size)
        // not application config, so client validation matches server capabilities
        $settings['max_file_size'] = $maxFileSize;

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update project settings
     * 
     * OPTIMIZATION: Clears cache on update to ensure fresh data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        // Validate full settings payload (canonical + compatibility aliases).
        $hexColor = 'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/';

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'site_name' => 'sometimes|string|max:120',
            'logo' => 'sometimes|nullable|url|max:2048',
            'site_logo' => 'sometimes|nullable|url|max:2048',
            'logoDark' => 'sometimes|nullable|url|max:2048',
            'logoLight' => 'sometimes|nullable|url|max:2048',
            'logo_dark' => 'sometimes|nullable|url|max:2048',
            'logo_light' => 'sometimes|nullable|url|max:2048',
            'dark_logo' => 'sometimes|nullable|url|max:2048',
            'light_logo' => 'sometimes|nullable|url|max:2048',
            'site_logo_dark' => 'sometimes|nullable|url|max:2048',
            'site_logo_light' => 'sometimes|nullable|url|max:2048',
            'favicon' => 'sometimes|nullable|url|max:2048',
            'site_favicon' => 'sometimes|nullable|url|max:2048',
            'primaryColor' => ['sometimes', 'nullable', $hexColor],
            'secondaryColor' => ['sometimes', 'nullable', $hexColor],
            'primary_color' => ['sometimes', 'nullable', $hexColor],
            'secondary_color' => ['sometimes', 'nullable', $hexColor],
            'description' => 'sometimes|nullable|string|max:500',
            'site_description' => 'sometimes|nullable|string|max:500',
            'supportEmail' => 'sometimes|nullable|email|max:255',
            'site_email' => 'sometimes|nullable|email|max:255',
            'supportPhone' => 'sometimes|nullable|string|max:50',
            'site_phone' => 'sometimes|nullable|string|max:50',
            'site_address' => 'sometimes|nullable|string|max:500',
            'social_facebook' => 'sometimes|nullable|url|max:255',
            'social_twitter' => 'sometimes|nullable|url|max:255',
            'social_linkedin' => 'sometimes|nullable|url|max:255',
            'social_instagram' => 'sometimes|nullable|url|max:255',
            'upload_max_size' => 'sometimes|nullable|integer|min:1|max:1000',
            'media_convert_to_webp' => 'sometimes|boolean',
            'site_languages' => 'sometimes|array',
            'site_languages.*.code' => 'required_with:site_languages|string|max:16',
            'site_languages.*.label' => 'nullable|string|max:120',
            'site_languages.*.native_label' => 'nullable|string|max:120',
            'site_languages.*.rtl' => 'sometimes|boolean',
            'default_locale' => 'sometimes|string|max:16',
            'font_mode' => ['sometimes', 'string', Rule::in([TypographyResolver::MODE_SYSTEM, TypographyResolver::MODE_CUSTOM])],
            'font_ltr_id' => ['sometimes', 'nullable', 'integer', 'exists:fonts,id'],
            'font_rtl_id' => ['sometimes', 'nullable', 'integer', 'exists:fonts,id'],
            'marketing_site_content' => 'sometimes|array',
            'settings_translations' => 'sometimes|array',
        ]);

        // Load existing settings, apply updates, normalize aliases, then persist.
        $existingSettings = $this->loadStoredSettings();
        unset($existingSettings['features']);

        $incomingTranslations = null;
        $validatedScalars = $validated;
        if (array_key_exists('settings_translations', $validatedScalars)) {
            $incomingTranslations = $validatedScalars['settings_translations'];
            unset($validatedScalars['settings_translations']);
        }

        $mergedSettings = array_merge($existingSettings, $validatedScalars);
        $normalizedSettings = $this->normalizeSettingsPayload($mergedSettings);

        if ($incomingTranslations !== null && is_array($incomingTranslations)) {
            $existingTranslations = is_array($normalizedSettings['settings_translations'] ?? null)
                ? $normalizedSettings['settings_translations']
                : [];
            $normalizedSettings['settings_translations'] = SiteSettingsPresenter::mergeTranslationsStorage(
                $existingTranslations,
                SiteSettingsPresenter::normalizeTranslationsPayload($incomingTranslations)
            );
        }

        $persisted = $normalizedSettings;
        unset($persisted['features']);
        $this->saveStoredSettings($persisted);

        // Invalidate and refresh cache to keep GET /settings fast and consistent.
        MarketingSettingsCache::forgetAll();
        Cache::put(MarketingSettingsCache::SETTINGS_CACHE_KEY, $normalizedSettings, $this->settingsCacheTtlSeconds());

        ActivityLogService::record('settings.updated', null, [], $request);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $normalizedSettings,
        ]);
    }
}
