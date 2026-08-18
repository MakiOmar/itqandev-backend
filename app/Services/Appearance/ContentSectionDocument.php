<?php

namespace App\Services\Appearance;

use App\Support\SiteLanguages;
use Illuminate\Support\Str;

/**
 * Normalize / present homepage-style section documents (homepage builder + CMS pages).
 */
final class ContentSectionDocument
{
    /**
     * @param  array<string, mixed>|list<mixed>|null  $input  Full `{ sections: [...] }` or a bare sections list.
     * @return list<array<string, mixed>>
     */
    public static function normalizeSections(mixed $input, bool $fallbackToHomepageDefaults = false): array
    {
        $rawSections = [];
        if (is_array($input)) {
            if (array_is_list($input)) {
                $rawSections = $input;
            } elseif (isset($input['sections']) && is_array($input['sections'])) {
                $rawSections = $input['sections'];
            }
        }

        $counts = [];
        $sections = [];

        foreach ($rawSections as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            // Homepage flat list accepts kits only (widgets belong in CMS layout columns).
            if (! KitRegistry::has($type)) {
                continue;
            }
            $kind = PageLeafRegistry::KIND_KIT;
            $countKey = PageLeafRegistry::countKey($kind, $type);
            $counts[$countKey] = ($counts[$countKey] ?? 0) + 1;
            $max = KitRegistry::maxInstances($type);
            if ($max !== null && $counts[$countKey] > $max) {
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
            $settings = AppearanceLocalizedSettings::normalize(
                $settings,
                KitRegistry::defaultSettings($type),
                KitRegistry::translatableKeys($type),
            );

            $sections[] = LayoutHideOn::appendTo(
                BuilderStyleDocument::appendTo([
                    'id' => $id,
                    'kind' => $kind,
                    'type' => $type,
                    'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'layout_width' => $layout,
                    'settings' => $settings,
                ], $row['styles'] ?? null),
                $row['hide_on'] ?? null,
            );
        }

        if ($sections === [] && $fallbackToHomepageDefaults) {
            return (new HomepageBuilderService)->defaultDocument()['sections'];
        }

        return $sections;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array{id: string, type: string, layout_width: string, settings: array<string, mixed>}>
     */
    public static function presentPublic(array $sections, ?string $locale = null): array
    {
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;
        $out = [];
        foreach ($sections as $section) {
            if (! ($section['enabled'] ?? false)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (! KitRegistry::has($type)) {
                continue;
            }
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            $settings = AppearanceLocalizedSettings::resolveForLocale(
                $settings,
                $locale,
                $defaultLocale,
                KitRegistry::translatableKeys($type),
            );
            $entry = KitRegistry::all()[$type] ?? null;
            $fields = is_array($entry['settings_fields'] ?? null) ? $entry['settings_fields'] : [];
            $settings = AppearanceMediaResolver::expandMediaFields($settings, $fields, $locale);
            $settings = AppearanceMediaResolver::expandRepeaterMediaFields($settings, $fields, $locale);
            if ($type === 'hero') {
                $enabled = filter_var($settings['floating_icons_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $settings['floating_icons_enabled'] = $enabled;
                $settings['floating_icons'] = $enabled
                    ? HeroFloatingIcons::presentPublic(
                        is_array($settings['floating_icons'] ?? null) ? $settings['floating_icons'] : [],
                        $locale,
                    )
                    : [];
            }
            $out[] = LayoutHideOn::appendTo(
                BuilderStyleDocument::appendTo([
                    'id' => (string) ($section['id'] ?? ''),
                    'kind' => PageLeafRegistry::KIND_KIT,
                    'type' => $type,
                    'layout_width' => (string) ($section['layout_width'] ?? 'boxed'),
                    'settings' => $settings,
                ], $section['styles'] ?? null),
                $section['hide_on'] ?? null,
            );
        }

        return $out;
    }
}
