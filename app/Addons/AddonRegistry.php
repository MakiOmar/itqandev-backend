<?php

namespace App\Addons;

use App\Services\Appearance\KitRegistry;
use App\Services\Appearance\PageLeafRegistry;
use App\Services\Appearance\WidgetRegistry;
use App\Support\FeatureModules;
use InvalidArgumentException;

/**
 * Installed addons, filled by {@see AddonServiceProvider::register()} (Laravel package discovery).
 *
 * Static so the static widget/kit registries can read it. Everything an addon contributes is keyed by
 * its module: definitions of a disabled module are hidden from builders and public output.
 *
 * @phpstan-import-type LeafDef from Addon
 */
final class AddonRegistry
{
    /** @var array<string, Addon> */
    private static array $addons = [];

    /** @var array<string, array<string, array<string, LeafDef>>>|null module => kind => type => def */
    private static ?array $leaves = null;

    public static function register(Addon $addon): void
    {
        $key = $addon->moduleKey();
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Addon module key [{$key}] must be snake_case.");
        }
        if (in_array($key, FeatureModules::builtInKeys(), true)) {
            throw new InvalidArgumentException("Addon module key [{$key}] collides with a built-in module.");
        }
        if (isset(self::$addons[$key]) && $addon::class !== self::$addons[$key]::class) {
            throw new InvalidArgumentException("Addon module key [{$key}] is registered twice.");
        }
        self::$addons[$key] = $addon;
        self::$leaves = null;
    }

    public static function forget(string $moduleKey): void
    {
        unset(self::$addons[$moduleKey]);
        self::$leaves = null;
    }

    /** @return array<string, Addon> */
    public static function all(): array
    {
        return self::$addons;
    }

    /** @return list<string> */
    public static function moduleKeys(): array
    {
        return array_keys(self::$addons);
    }

    public static function enabled(string $moduleKey): bool
    {
        return isset(self::$addons[$moduleKey]) && FeatureModules::enabled($moduleKey);
    }

    /**
     * Content type classes from every installed addon, with the module that owns them.
     *
     * @return array<class-string, string>
     */
    public static function contentTypeClasses(): array
    {
        $out = [];
        foreach (self::$addons as $key => $addon) {
            foreach ($addon->contentTypes() as $class) {
                $out[$class] = $key;
            }
        }

        return $out;
    }

    /**
     * Built-in definitions plus those of enabled addons.
     *
     * @param  PageLeafRegistry::KIND_WIDGET|PageLeafRegistry::KIND_KIT  $kind
     * @param  array<string, LeafDef>  $builtIn
     * @return array<string, LeafDef>
     */
    public static function mergeLeaves(string $kind, array $builtIn): array
    {
        if (self::$addons === []) {
            return $builtIn;
        }
        foreach (self::leaves() as $key => $byKind) {
            if (($byKind[$kind] ?? []) !== [] && FeatureModules::enabled($key)) {
                $builtIn += $byKind[$kind];
            }
        }

        return $builtIn;
    }

    /**
     * Definition of a leaf whose addon is installed but disabled, so saved layouts keep the block.
     *
     * @return array{0: string, 1: LeafDef}|null kind, definition
     */
    public static function dormantLeaf(string $type, ?string $kindHint = null): ?array
    {
        if (self::$addons === []) {
            return null;
        }
        foreach (self::leaves() as $key => $byKind) {
            if (FeatureModules::enabled($key)) {
                continue;
            }
            $kinds = $kindHint === PageLeafRegistry::KIND_KIT
                ? [PageLeafRegistry::KIND_KIT, PageLeafRegistry::KIND_WIDGET]
                : [PageLeafRegistry::KIND_WIDGET, PageLeafRegistry::KIND_KIT];
            foreach ($kinds as $kind) {
                if (isset($byKind[$kind][$type])) {
                    return [$kind, $byKind[$kind][$type]];
                }
            }
        }

        return null;
    }

    /**
     * Dashboard links of enabled addons for GET /api/settings `addons`.
     *
     * @return list<array{key: string, module: string, label: array<string, string>, href: string, permission: string|null}>
     */
    public static function adminNav(): array
    {
        $out = [];
        foreach (self::$addons as $key => $addon) {
            if (! FeatureModules::enabled($key)) {
                continue;
            }
            foreach (array_values($addon->adminNav()) as $i => $item) {
                $href = '/'.trim((string) ($item['href'] ?? ''), '/').'/';
                if (! str_starts_with($href, '/admin/addons/'.$key.'/')) {
                    throw new InvalidArgumentException("Addon [{$key}] nav href [{$href}] must live under /admin/addons/{$key}/.");
                }
                $label = $item['label'] ?? $key;
                $permission = isset($item['permission']) && $item['permission'] !== '' ? (string) $item['permission'] : null;
                $out[] = [
                    'key' => $key.'.'.$i,
                    'module' => $key,
                    'label' => is_array($label) ? array_map('strval', $label) : ['en' => (string) $label],
                    'href' => $href,
                    'permission' => $permission,
                ];
            }
        }

        return $out;
    }

    /**
     * Every addon leaf, validated once: type keys must be unique across built-ins and addons.
     *
     * @return array<string, array<string, array<string, LeafDef>>>
     */
    private static function leaves(): array
    {
        if (self::$leaves !== null) {
            return self::$leaves;
        }
        $taken = array_fill_keys(array_merge(
            array_keys(WidgetRegistry::builtIn()),
            array_keys(KitRegistry::builtIn()),
        ), 'core');
        $out = [];
        foreach (self::$addons as $key => $addon) {
            $out[$key] = [
                PageLeafRegistry::KIND_WIDGET => $addon->widgets(),
                PageLeafRegistry::KIND_KIT => $addon->kits(),
            ];
            foreach ($out[$key] as $defs) {
                foreach (array_keys($defs) as $type) {
                    if (isset($taken[$type])) {
                        throw new InvalidArgumentException("Addon [{$key}] leaf type [{$type}] is already registered by [{$taken[$type]}].");
                    }
                    $taken[$type] = $key;
                }
            }
        }

        return self::$leaves = $out;
    }
}
