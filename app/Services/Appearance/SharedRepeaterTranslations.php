<?php

namespace App\Services\Appearance;

use Illuminate\Support\Str;

/**
 * "Shared" repeaters: rows (order, icons, links) are the same in every language, only item fields
 * marked translatable differ. Per-locale copy lives in settings.translations.{locale}.{key} as
 * {rowId: {itemKey: string}}, so rows need stable ids.
 */
final class SharedRepeaterTranslations
{
    private const MAX_TEXT_LENGTH = 500;

    /**
     * Map of shared repeater key => translatable item keys.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, list<string>>
     */
    public static function fromFields(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ($field['type'] ?? '') !== 'repeater') {
                continue;
            }
            if (AppearanceLocalizedSettings::isFieldTranslatable($field)) {
                continue;
            }
            $itemKeys = [];
            foreach (is_array($field['item_fields'] ?? null) ? $field['item_fields'] : [] as $item) {
                if (is_array($item) && ($item['translatable'] ?? false) === true && ($item['key'] ?? '') !== '') {
                    $itemKeys[] = (string) $item['key'];
                }
            }
            $key = (string) ($field['key'] ?? '');
            if ($key !== '' && $itemKeys !== []) {
                $map[$key] = $itemKeys;
            }
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ensureIds(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = self::cleanId($row['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                $id = 'itm_'.Str::lower(Str::random(8));
            }
            $seen[$id] = true;
            $row['id'] = $id;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Keep only string values for translatable item keys of rows that still exist.
     *
     * @param  list<string>  $itemKeys
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, string>>
     */
    public static function sanitizeBag(mixed $bag, array $itemKeys, array $rows): array
    {
        if (! is_array($bag)) {
            return [];
        }
        $ids = array_fill_keys(array_column($rows, 'id'), true);
        $out = [];
        foreach ($bag as $id => $values) {
            $id = self::cleanId($id);
            if ($id === '' || ! isset($ids[$id]) || ! is_array($values)) {
                continue;
            }
            $kept = [];
            foreach ($itemKeys as $itemKey) {
                $value = $values[$itemKey] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $kept[$itemKey] = mb_substr(strip_tags(trim($value)), 0, self::MAX_TEXT_LENGTH);
                }
            }
            if ($kept !== []) {
                $out[$id] = $kept;
            }
        }

        return $out;
    }

    /**
     * Replace translatable item values by row id; missing/empty translations keep the primary text.
     *
     * @param  list<string>  $itemKeys
     * @return list<array<string, mixed>>
     */
    public static function overlay(mixed $rows, mixed $bag, array $itemKeys): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $bag = is_array($bag) ? $bag : [];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $translated = $bag[(string) ($row['id'] ?? '')] ?? null;
            if (is_array($translated)) {
                foreach ($itemKeys as $itemKey) {
                    $value = $translated[$itemKey] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        $row[$itemKey] = $value;
                    }
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    private static function cleanId(mixed $id): string
    {
        $id = trim((string) $id);

        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : '';
    }
}
