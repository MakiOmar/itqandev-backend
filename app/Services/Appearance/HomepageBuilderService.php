<?php

namespace App\Services\Appearance;

use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\SiteLanguages;

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
            $sections[] = [
                'id' => 'sec_'.$type,
                'type' => $type,
                'enabled' => true,
                'layout_width' => $type === 'hero' ? 'full' : 'boxed',
                'settings' => HomepageSectionRegistry::defaultSettings($type),
            ];
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
    public function presentPublic(?string $locale = null): array
    {
        $doc = $this->loadAdminDocument();

        return ContentSectionDocument::presentPublic($doc['sections'], $locale);
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
        return [
            'sections' => ContentSectionDocument::normalizeSections($input, true),
        ];
    }
}
