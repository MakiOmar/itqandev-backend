<?php

namespace App\Services\Appearance;

use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\SiteLanguages;
use Illuminate\Support\Str;

final class HomepageBuilderService
{
    public const SETTINGS_KEY = 'homepage_builder';

    /**
     * Default seven sections matching the current hardcoded homepage order.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function defaultDocument(): array
    {
        $order = [
            'hero',
            'services_teaser',
            'case_studies',
            'testimonials',
            'tech_stack',
            'blog_preview',
            'cta',
        ];

        $sections = [];
        foreach ($order as $type) {
            $sections[] = $this->makeSection($type, true, $type === 'hero' ? 'full' : 'boxed');
        }

        return ['sections' => $sections];
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function loadAdminDocument(): array
    {
        $stored = ProjectSettingsStore::load();
        $raw = $stored[self::SETTINGS_KEY] ?? null;
        if (! is_array($raw) || ! isset($raw['sections']) || ! is_array($raw['sections']) || $raw['sections'] === []) {
            return $this->defaultDocument();
        }

        return $this->normalizeDocument($raw);
    }

    /**
     * Enabled sections for public shell (unknown types dropped).
     * Text/media settings are localized via settings.translations when locale ≠ default.
     * Media ids resolve to URL + {key}_alt for the presentation locale.
     *
     * @return list<array{id: string, type: string, layout_width: string, settings: array<string, mixed>}>
     */
    public function presentPublic(?string $locale = null): array
    {
        $doc = $this->loadAdminDocument();
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;
        $out = [];
        foreach ($doc['sections'] as $section) {
            if (! ($section['enabled'] ?? false)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (! HomepageSectionRegistry::has($type)) {
                continue;
            }
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            $settings = AppearanceLocalizedSettings::resolveForLocale(
                $settings,
                $locale,
                $defaultLocale,
                HomepageSectionRegistry::translatableKeys($type),
            );
            $entry = HomepageSectionRegistry::all()[$type] ?? null;
            $fields = is_array($entry['settings_fields'] ?? null) ? $entry['settings_fields'] : [];
            $settings = AppearanceMediaResolver::expandMediaFields($settings, $fields, $locale);
            $out[] = [
                'id' => (string) $section['id'],
                'type' => $type,
                'layout_width' => (string) ($section['layout_width'] ?? 'boxed'),
                'settings' => $settings,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{sections: list<array<string, mixed>>}
     */
    public function save(array $input): array
    {
        $normalized = $this->normalizeDocument($input);
        ProjectSettingsStore::merge([self::SETTINGS_KEY => $normalized]);
        MarketingSettingsCache::forgetAll();

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{sections: list<array<string, mixed>>}
     */
    public function normalizeDocument(array $input): array
    {
        $rawSections = is_array($input['sections'] ?? null) ? $input['sections'] : [];
        $counts = [];
        $sections = [];

        foreach ($rawSections as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if (! HomepageSectionRegistry::has($type)) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            $max = HomepageSectionRegistry::maxInstances($type);
            if ($max !== null && $counts[$type] > $max) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'sec_'.Str::lower(Str::random(10));
            }

            $layout = strtolower(trim((string) ($row['layout_width'] ?? 'boxed')));
            if (! in_array($layout, ['boxed', 'full'], true)) {
                $layout = 'boxed';
            }

            $settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
            $settings = $this->normalizeSettings($type, $settings);

            $sections[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'layout_width' => $layout,
                'settings' => $settings,
            ];
        }

        if ($sections === []) {
            return $this->defaultDocument();
        }

        return ['sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(string $type, array $settings): array
    {
        return AppearanceLocalizedSettings::normalize(
            $settings,
            HomepageSectionRegistry::defaultSettings($type),
            HomepageSectionRegistry::translatableKeys($type),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSection(string $type, bool $enabled, string $layoutWidth): array
    {
        return [
            'id' => 'sec_'.$type,
            'type' => $type,
            'enabled' => $enabled,
            'layout_width' => $layoutWidth,
            'settings' => HomepageSectionRegistry::defaultSettings($type),
        ];
    }
}
