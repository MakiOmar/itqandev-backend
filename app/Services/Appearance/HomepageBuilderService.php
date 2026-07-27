<?php

namespace App\Services\Appearance;

use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
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
     *
     * @return list<array{id: string, type: string, layout_width: string, settings: array<string, mixed>}>
     */
    public function presentPublic(): array
    {
        $doc = $this->loadAdminDocument();
        $out = [];
        foreach ($doc['sections'] as $section) {
            if (! ($section['enabled'] ?? false)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (! HomepageSectionRegistry::has($type)) {
                continue;
            }
            $out[] = [
                'id' => (string) $section['id'],
                'type' => $type,
                'layout_width' => (string) ($section['layout_width'] ?? 'boxed'),
                'settings' => is_array($section['settings'] ?? null) ? $section['settings'] : [],
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
        $defaults = HomepageSectionRegistry::defaultSettings($type);
        $merged = array_merge($defaults, $settings);

        foreach ($merged as $key => $value) {
            if (is_string($value)) {
                $merged[$key] = trim($value);
            }
            if (in_array($key, ['limit'], true)) {
                $merged[$key] = max(1, min(24, (int) $value));
            }
        }

        return $merged;
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
