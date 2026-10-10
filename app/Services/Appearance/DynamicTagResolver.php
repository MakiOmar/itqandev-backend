<?php

namespace App\Services\Appearance;

use App\Services\ContentTypes\ContentTypeRegistry;
use App\Support\ProjectSettingsStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Server-side, locale-aware replacement of {{tag}} tokens in builder strings.
 *
 * The record payload is a tag id => value map built by the record's content type definition.
 */
final class DynamicTagResolver
{
    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function apply(mixed $value, ?array $recordPayload, ?string $locale = null): mixed
    {
        if (is_string($value)) {
            return self::replaceString($value, $recordPayload, $locale);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::apply($v, $recordPayload, $locale);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function replaceString(string $value, ?array $recordPayload, ?string $locale = null): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function (array $m) use ($recordPayload, $locale) {
            $id = strtolower($m[1]);
            if (! DynamicTagRegistry::isAllowed($id)) {
                return '';
            }

            return self::resolve($id, $recordPayload, $locale);
        }, $value) ?? $value;
    }

    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function resolve(string $id, ?array $recordPayload, ?string $locale = null): string
    {
        $record = is_array($recordPayload) ? $recordPayload : [];

        if (str_starts_with($id, 'site.')) {
            return self::siteValue($id);
        }
        if ($id === DynamicTagRegistry::ARCHIVE_TITLE) {
            return (string) ($record[DynamicTagRegistry::ARCHIVE_TITLE] ?? $record[DynamicTagRegistry::POST_TITLE] ?? '');
        }

        $value = $record[$id] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Tag payload for a record (via its content type) or, without one, the listing context's archive title.
     *
     * @return array<string, mixed>
     */
    public static function payloadFromModel(?Model $record, string $context, ?string $locale = null): array
    {
        $registry = app(ContentTypeRegistry::class);
        $definition = $record !== null ? $registry->forModel($record) : null;
        if ($definition !== null) {
            return $definition->values($record, $locale);
        }

        $archiveTitle = match ($context) {
            'not_found' => 'Not found',
            'homepage' => (string) (ProjectSettingsStore::load()['site_name'] ?? ''),
            default => (string) ($registry->forArchiveContext($context)?->archive()['label'] ?? ''),
        };

        return [
            DynamicTagRegistry::ARCHIVE_TITLE => $archiveTitle,
            DynamicTagRegistry::POST_TITLE => $archiveTitle,
        ];
    }

    private static function siteValue(string $id): string
    {
        $settings = ProjectSettingsStore::load();

        return match ($id) {
            'site.name' => (string) ($settings['site_name'] ?? $settings['name'] ?? ''),
            'site.tagline' => (string) ($settings['site_description'] ?? $settings['description'] ?? ''),
            'site.url' => rtrim((string) config('app.url'), '/'),
            'site.logo' => (string) ($settings['logo'] ?? $settings['site_logo'] ?? ''),
            'site.year' => (string) Carbon::now()->year,
            default => '',
        };
    }
}
