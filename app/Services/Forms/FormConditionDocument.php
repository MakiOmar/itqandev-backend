<?php

namespace App\Services\Forms;

/**
 * AND/OR visibility groups for form fields.
 *
 * { relation: "and"|"or", rules: [{ field, op, value }] }
 * ops: equals | contains | empty | not_empty
 */
final class FormConditionDocument
{
    public const OPS = ['equals', 'contains', 'empty', 'not_empty'];

    /**
     * @return array{relation: string, rules: list<array{field: string, op: string, value: string}>}|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $relation = strtolower(trim((string) ($raw['relation'] ?? 'and')));
        if ($relation !== 'or') {
            $relation = 'and';
        }
        $rules = [];
        foreach (is_array($raw['rules'] ?? null) ? $raw['rules'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $field = trim((string) ($row['field'] ?? ''));
            $op = strtolower(trim((string) ($row['op'] ?? 'equals')));
            if ($field === '' || ! in_array($op, self::OPS, true)) {
                continue;
            }
            $rules[] = [
                'field' => mb_substr($field, 0, 64),
                'op' => $op,
                'value' => mb_substr(trim((string) ($row['value'] ?? '')), 0, 255),
            ];
            if (count($rules) >= 12) {
                break;
            }
        }
        if ($rules === []) {
            return null;
        }

        return ['relation' => $relation, 'rules' => $rules];
    }

    /**
     * @param  array<string, mixed>  $values  submitted field id => value
     */
    public static function isVisible(?array $conditions, array $values): bool
    {
        if ($conditions === null || ($conditions['rules'] ?? []) === []) {
            return true;
        }
        $relation = ($conditions['relation'] ?? 'and') === 'or' ? 'or' : 'and';
        $results = [];
        foreach ($conditions['rules'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $raw = $values[(string) ($rule['field'] ?? '')] ?? null;
            $hay = is_array($raw) ? implode(',', $raw) : (string) $raw;
            $op = (string) ($rule['op'] ?? 'equals');
            $want = (string) ($rule['value'] ?? '');
            $results[] = match ($op) {
                'empty' => trim($hay) === '',
                'not_empty' => trim($hay) !== '',
                'contains' => $want !== '' && str_contains(mb_strtolower($hay), mb_strtolower($want)),
                default => mb_strtolower($hay) === mb_strtolower($want),
            };
        }
        if ($results === []) {
            return true;
        }
        if ($relation === 'or') {
            return in_array(true, $results, true);
        }

        return ! in_array(false, $results, true);
    }
}
