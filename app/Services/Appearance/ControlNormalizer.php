<?php

namespace App\Services\Appearance;

use App\Services\HtmlSanitizerService;

/**
 * Sanitize widget/kit settings against the registry field catalog.
 */
final class ControlNormalizer
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function normalizeSettings(array $settings, array $fields): array
    {
        $html = app(HtmlSanitizerService::class);
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
            if ($key === '' || ! array_key_exists($key, $settings)) {
                continue;
            }
            $settings[$key] = self::normalizeValue($settings[$key], $type, $field, $html);
        }

        if (isset($settings['html']) && is_string($settings['html'])) {
            $settings['html'] = EmbedHostAllowlist::sanitizeIframeHtml($settings['html']);
        }
        if (isset($settings['embed_url'])) {
            $url = BuilderUrlSanitizer::sanitize($settings['embed_url']);
            $settings['embed_url'] = ($url !== '' && EmbedHostAllowlist::isAllowedUrl($url)) ? $url : '';
        }
        if (isset($settings['video_url'])) {
            $url = BuilderUrlSanitizer::sanitize($settings['video_url']);
            $settings['video_url'] = ($url !== '' && EmbedHostAllowlist::isAllowedUrl($url)) ? $url : '';
        }
        if (isset($settings['open_in_new_tab'])) {
            $settings['open_in_new_tab'] = filter_var($settings['open_in_new_tab'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($settings['rel'])) {
            $settings['rel'] = self::rel($settings['rel']);
        }
        if (isset($settings['whatsapp_number'])) {
            // wa.me accepts only the international number as digits (E.164 is at most 15).
            $settings['whatsapp_number'] = substr((string) preg_replace('/\D+/', '', (string) $settings['whatsapp_number']), 0, 15);
        }
        if (isset($settings['overlay_id'])) {
            $oid = (int) $settings['overlay_id'];
            $settings['overlay_id'] = $oid > 0 ? $oid : null;
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function normalizeValue(
        mixed $value,
        string $type,
        array $field,
        HtmlSanitizerService $html,
    ): mixed {
        return match ($type) {
            'richtext' => is_string($value) ? (string) $html->sanitize($value) : '',
            'url', 'link', 'video' => BuilderUrlSanitizer::sanitize($value),
            'number' => is_numeric($value) ? 0 + $value : ($field['min'] ?? 0),
            'boolean', 'switcher' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'media' => is_numeric($value) ? (int) $value : $value,
            'icon' => IconValueNormalizer::normalize($value),
            'repeater' => self::normalizeRepeater($value, is_array($field['item_fields'] ?? null) ? $field['item_fields'] : [], $html),
            default => $value,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $itemFields
     * @return list<array<string, mixed>>
     */
    private static function normalizeRepeater(mixed $value, array $itemFields, HtmlSanitizerService $html): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = self::normalizeSettings($row, $itemFields);
            if (count($out) >= 48) {
                break;
            }
        }

        return $out;
    }

    private static function rel(mixed $value): string
    {
        $parts = preg_split('/\s+/', strtolower(trim((string) $value))) ?: [];
        $allowed = ['noopener', 'noreferrer', 'nofollow'];
        $kept = [];
        foreach ($parts as $part) {
            if (in_array($part, $allowed, true) && ! in_array($part, $kept, true)) {
                $kept[] = $part;
            }
        }

        return implode(' ', $kept);
    }
}
