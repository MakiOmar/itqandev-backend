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
     * @param  list<string>  $htmlKeys  Top-level setting keys rendered as HTML (rich text); `html` tags stay markup there.
     */
    public static function apply(mixed $value, ?array $recordPayload, ?string $locale = null, array $htmlKeys = []): mixed
    {
        if (is_string($value)) {
            return self::replaceString($value, $recordPayload, $locale);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = is_string($v) && in_array($k, $htmlKeys, true)
                    ? self::replaceString($v, $recordPayload, $locale, true)
                    : self::apply($v, $recordPayload, $locale);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $recordPayload
     * @param  bool  $allowHtml  False for text fields: `html` tag values are reduced to plain text, since the
     *                           widget escapes them and would show raw `<p>` markup.
     */
    public static function replaceString(string $value, ?array $recordPayload, ?string $locale = null, bool $allowHtml = false): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function (array $m) use ($recordPayload, $locale, $allowHtml) {
            $id = strtolower($m[1]);
            if (! DynamicTagRegistry::isAllowed($id)) {
                return '';
            }
            $resolved = self::resolve($id, $recordPayload, $locale);

            return ! $allowHtml && DynamicTagRegistry::kind($id) === 'html' ? self::plainText($resolved) : $resolved;
        }, $value) ?? $value;
    }

    private static function plainText(string $html): string
    {
        $spaced = (string) preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6]|\/blockquote)\b[^>]*>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
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
