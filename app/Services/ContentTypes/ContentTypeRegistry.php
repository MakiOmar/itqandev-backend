<?php

namespace App\Services\ContentTypes;

use App\Addons\AddonRegistry;
use App\Support\FeatureModules;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The content types listed in config/content-types.php plus those of installed addons. Disabled feature modules are skipped.
 *
 * Bound as a singleton; definitions are built once, module gating is re-checked per call.
 */
final class ContentTypeRegistry
{
    /** First URL segments the website already routes; addon types may not claim them. */
    public const RESERVED_PREFIXES = ['admin', 'about', 'api-check', 'blog', 'contact', 'forms', 'pages', 'portfolio', 'pricing', 'services', 'work'];

    /** @var array<string, ContentTypeDefinition>|null */
    private ?array $configured = null;

    /**
     * Every configured type, including those whose module is disabled.
     *
     * @return array<string, ContentTypeDefinition>
     */
    public function configured(): array
    {
        if ($this->configured !== null) {
            return $this->configured;
        }

        $out = [];
        foreach ((array) config('content-types.types', []) as $class) {
            $this->add($out, $class, 'config/content-types.php');
        }
        foreach (AddonRegistry::contentTypeClasses() as $class => $module) {
            $definition = $this->add($out, $class, "Addon [{$module}]");
            if ($definition->module() !== $module) {
                throw new InvalidArgumentException("Addon [{$module}]: content type [{$definition->key()}] must return module() [{$module}].");
            }
            foreach ($definition->publicPathPrefixes() as $prefix) {
                if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $prefix) || in_array($prefix, self::RESERVED_PREFIXES, true)) {
                    throw new InvalidArgumentException("Addon [{$module}]: public prefix [{$prefix}] is reserved or invalid.");
                }
            }
        }
        $owners = [];
        foreach ($out as $key => $definition) {
            foreach ($definition->publicPathPrefixes() as $prefix) {
                if (isset($owners[$prefix])) {
                    throw new InvalidArgumentException("Content types [{$owners[$prefix]}] and [{$key}] share public prefix [{$prefix}].");
                }
                $owners[$prefix] = $key;
            }
        }

        return $this->configured = $out;
    }

    /**
     * @param  array<string, ContentTypeDefinition>  $out
     */
    private function add(array &$out, mixed $class, string $source): ContentTypeDefinition
    {
        if (! is_string($class) || ! is_subclass_of($class, ContentTypeDefinition::class)) {
            throw new InvalidArgumentException($source.': ['.(is_string($class) ? $class : gettype($class)).'] must implement '.ContentTypeDefinition::class.'.');
        }
        /** @var ContentTypeDefinition $definition */
        $definition = app($class);
        $key = $definition->key();
        if (isset($out[$key])) {
            throw new InvalidArgumentException("{$source}: duplicate content type key [{$key}].");
        }

        return $out[$key] = $definition;
    }

    /**
     * @return array<string, ContentTypeDefinition>
     */
    public function all(): array
    {
        return array_filter(
            $this->configured(),
            static fn (ContentTypeDefinition $d) => $d->module() === null || FeatureModules::enabled($d->module())
        );
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function get(string $key): ?ContentTypeDefinition
    {
        return $this->all()[strtolower(trim($key))] ?? null;
    }

    public function forModel(Model $record): ?ContentTypeDefinition
    {
        foreach ($this->all() as $definition) {
            if (is_a($record, $definition->modelClass())) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Single-record public path `/{prefix}/{slug}`.
     *
     * @return array{0: ContentTypeDefinition, 1: string}|null definition, slug
     */
    public function forPath(string $path): ?array
    {
        if (! preg_match('#^/([^/]+)/([^/]+)/?$#', $path, $m)) {
            return null;
        }
        $prefix = strtolower($m[1]);
        foreach ($this->all() as $definition) {
            if (in_array($prefix, $definition->publicPathPrefixes(), true)) {
                return [$definition, $m[2]];
            }
        }

        return null;
    }

    /** Listing path `/{prefix}` of a type that has an archive. */
    public function forArchivePath(string $path): ?ContentTypeDefinition
    {
        $prefix = strtolower(trim($path, '/'));
        foreach ($this->all() as $definition) {
            if ($definition->archive() !== null && in_array($prefix, $definition->publicPathPrefixes(), true)) {
                return $definition;
            }
        }

        return null;
    }

    public function forArchiveContext(string $context): ?ContentTypeDefinition
    {
        foreach ($this->all() as $definition) {
            if (($definition->archive()['context'] ?? null) === $context) {
                return $definition;
            }
        }

        return null;
    }

    public function forArchivePageSlug(string $slug): ?ContentTypeDefinition
    {
        foreach ($this->all() as $definition) {
            if (($definition->archive()['page_slug'] ?? null) === $slug) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function archiveContexts(): array
    {
        $out = [];
        foreach ($this->all() as $definition) {
            $archive = $definition->archive();
            if ($archive !== null) {
                $out[] = $archive['context'];
            }
        }

        return $out;
    }

    public function forLoopSource(string $source): ?ContentTypeDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->loopSource() === $source) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * loop_grid `source` options for every configured loopable type (stored settings stay valid when a module is off).
     *
     * @return list<array{value: string, label: string}>
     */
    public function loopSourceOptions(): array
    {
        $out = [];
        foreach ($this->configured() as $definition) {
            $source = $definition->loopSource();
            if ($source !== null) {
                $out[] = ['value' => $source, 'label' => $definition->pluralLabel()];
            }
        }

        return $out;
    }

    /**
     * Admin metadata for the enabled types.
     *
     * @return list<array{key: string, label: string, plural_label: string, loop_source: string|null, archive: array{context: string, page_slug: string, label: string}|null}>
     */
    public function toAdminArray(): array
    {
        $out = [];
        foreach ($this->all() as $definition) {
            $out[] = [
                'key' => $definition->key(),
                'label' => $definition->label(),
                'plural_label' => $definition->pluralLabel(),
                'loop_source' => $definition->loopSource(),
                'archive' => $definition->archive(),
            ];
        }

        return $out;
    }
}
