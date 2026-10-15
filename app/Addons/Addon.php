<?php

namespace App\Addons;

use App\Services\ContentTypes\ContentTypeDefinition;

/**
 * Contract an installed addon package implements (usually via {@see AddonServiceProvider}).
 *
 * Widget and kit definitions use the same shape as WidgetRegistry / KitRegistry
 * (label, category, default_settings, settings_fields) so the builder needs no addon-specific UI.
 *
 * @phpstan-type LeafDef array{label: string, category: string, default_settings: array<string, mixed>, settings_fields: list<array<string, mixed>>}
 * @phpstan-type NavItem array{label: string|array<string, string>, href: string, permission?: string|null}
 */
interface Addon
{
    /** Feature module key that gates everything this addon contributes (e.g. `shop`). */
    public function moduleKey(): string;

    /** @return list<class-string<ContentTypeDefinition>> */
    public function contentTypes(): array;

    /** @return array<string, LeafDef> */
    public function widgets(): array;

    /** @return array<string, LeafDef> */
    public function kits(): array;

    /**
     * Dashboard links; `href` is locale-less and must live under `/admin/addons/{moduleKey}/`.
     *
     * @return list<NavItem>
     */
    public function adminNav(): array;
}
