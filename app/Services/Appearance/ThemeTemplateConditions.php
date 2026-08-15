<?php

namespace App\Services\Appearance;

use App\Models\ThemeTemplate;
use Illuminate\Http\Request;

/**
 * Matches theme template display conditions against a resolved public context.
 *
 * Rule shape:
 * { "include": true, "group": "entire|singular|archive|advanced", "key": string, "value": mixed }
 * Document: { "relation": "and"|"or", "rules": list<rule> }
 */
final class ThemeTemplateConditions
{
    public const RELATION_AND = 'and';

    public const RELATION_OR = 'or';

    /**
     * @param  array{relation?: string, rules?: list<array<string, mixed>>}|list<array<string, mixed>>|null  $conditions
     * @return array{relation: string, rules: list<array{include: bool, group: string, key: string, value: mixed}>}
     */
    public static function normalize(mixed $conditions): array
    {
        $relation = self::RELATION_AND;
        $rawRules = [];

        if (is_array($conditions)) {
            if (array_is_list($conditions)) {
                $rawRules = $conditions;
            } else {
                $relation = strtolower(trim((string) ($conditions['relation'] ?? self::RELATION_AND)));
                if ($relation !== self::RELATION_OR) {
                    $relation = self::RELATION_AND;
                }
                $rawRules = is_array($conditions['rules'] ?? null) ? $conditions['rules'] : [];
            }
        }

        $rules = [];
        foreach ($rawRules as $row) {
            if (! is_array($row)) {
                continue;
            }
            $group = strtolower(trim((string) ($row['group'] ?? '')));
            $key = strtolower(trim((string) ($row['key'] ?? '')));
            if ($group === '' || $key === '') {
                continue;
            }
            if (! in_array($group, ['entire', 'singular', 'archive', 'advanced'], true)) {
                continue;
            }
            $include = array_key_exists('include', $row)
                ? filter_var($row['include'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true;
            if ($include === null) {
                $include = true;
            }
            $rules[] = [
                'include' => $include,
                'group' => $group,
                'key' => $key,
                'value' => $row['value'] ?? null,
            ];
        }

        if ($rules === []) {
            $rules[] = [
                'include' => true,
                'group' => 'entire',
                'key' => 'site',
                'value' => null,
            ];
        }

        return [
            'relation' => $relation,
            'rules' => $rules,
        ];
    }

    /**
     * @param  array{
     *   context: string,
     *   content_type: string,
     *   record_id: int|null,
     *   query: array<string, string>,
     *   device: string|null,
     *   role: string|null,
     *   authenticated: bool
     * }  $ctx
     */
    public static function matches(ThemeTemplate $template, array $ctx): bool
    {
        $doc = self::normalize($template->conditions);
        $rules = $doc['rules'];
        $relation = $doc['relation'];

        $includeRules = array_values(array_filter($rules, fn ($r) => ($r['include'] ?? true) === true));
        $excludeRules = array_values(array_filter($rules, fn ($r) => ($r['include'] ?? true) === false));

        foreach ($excludeRules as $rule) {
            if (self::ruleMatches($rule, $ctx)) {
                return false;
            }
        }

        if ($includeRules === []) {
            return true;
        }

        if ($relation === self::RELATION_OR) {
            foreach ($includeRules as $rule) {
                if (self::ruleMatches($rule, $ctx)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($includeRules as $rule) {
            if (! self::ruleMatches($rule, $ctx)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Higher score wins. Tie-break by template id elsewhere.
     *
     * @param  array{context: string, content_type: string, record_id: int|null, ...}  $ctx
     */
    public static function specificity(ThemeTemplate $template, array $ctx): int
    {
        $doc = self::normalize($template->conditions);
        $score = 0;
        $hasAdvanced = false;

        foreach ($doc['rules'] as $rule) {
            if (($rule['include'] ?? true) !== true) {
                continue;
            }
            if (! self::ruleMatches($rule, $ctx)) {
                continue;
            }

            $group = $rule['group'];
            $key = $rule['key'];
            $value = $rule['value'];

            if ($group === 'advanced') {
                $hasAdvanced = true;
                $score += 5;
                continue;
            }

            if ($group === 'entire' && $key === 'site') {
                $score = max($score, 10);
                continue;
            }

            if ($group === 'singular') {
                if ($key === 'not_found') {
                    $score = max($score, 90);
                } elseif ($key === 'homepage') {
                    $score = max($score, 80);
                } elseif (in_array($key, ['page', 'blog_post', 'project', 'service'], true)) {
                    if ($value !== null && $value !== '' && (int) $value > 0) {
                        $score = max($score, 100);
                    } else {
                        $score = max($score, 50);
                    }
                }
                continue;
            }

            if ($group === 'archive') {
                $score = max($score, 70);
            }
        }

        if ($hasAdvanced) {
            $score += 5;
        }

        return $score;
    }

    /**
     * @param  array{include: bool, group: string, key: string, value: mixed}  $rule
     * @param  array{
     *   context: string,
     *   content_type: string,
     *   record_id: int|null,
     *   query: array<string, string>,
     *   device: string|null,
     *   role: string|null,
     *   authenticated: bool
     * }  $ctx
     */
    private static function ruleMatches(array $rule, array $ctx): bool
    {
        $group = $rule['group'];
        $key = $rule['key'];
        $value = $rule['value'];
        $context = (string) ($ctx['context'] ?? '');
        $contentType = (string) ($ctx['content_type'] ?? '');
        $recordId = $ctx['record_id'] ?? null;

        if ($group === 'entire') {
            return $key === 'site';
        }

        if ($group === 'singular') {
            if ($key === 'homepage') {
                return $context === 'homepage' || $contentType === 'homepage';
            }
            if ($key === 'not_found') {
                return $context === 'not_found';
            }
            if (in_array($key, ['page', 'blog_post', 'project', 'service'], true)) {
                if ($contentType !== $key && $context !== $key) {
                    return false;
                }
                if ($value !== null && $value !== '' && (int) $value > 0) {
                    return $recordId !== null && (int) $recordId === (int) $value;
                }

                return true;
            }

            return false;
        }

        if ($group === 'archive') {
            return match ($key) {
                'blog_index' => $context === 'blog_index',
                'portfolio_index' => $context === 'portfolio_index',
                'services_index' => $context === 'services_index',
                default => false,
            };
        }

        if ($group === 'advanced') {
            if ($key === 'device') {
                $want = strtolower(trim((string) $value));
                $have = strtolower(trim((string) ($ctx['device'] ?? '')));

                return $want !== '' && $want === $have;
            }
            if ($key === 'role') {
                $want = strtolower(trim((string) $value));
                if ($want === 'guest') {
                    return ! ($ctx['authenticated'] ?? false);
                }
                if ($want === 'authenticated') {
                    return (bool) ($ctx['authenticated'] ?? false);
                }
                $have = strtolower(trim((string) ($ctx['role'] ?? '')));

                return $want !== '' && $want === $have;
            }
            if ($key === 'url_param') {
                $raw = is_string($value) ? $value : (is_array($value) ? '' : (string) $value);
                $raw = trim($raw);
                if ($raw === '') {
                    return false;
                }
                $paramKey = $raw;
                $paramVal = null;
                if (str_contains($raw, '=')) {
                    [$paramKey, $paramVal] = explode('=', $raw, 2);
                }
                $paramKey = strtolower(trim($paramKey));
                $query = $ctx['query'] ?? [];
                if ($paramKey === '' || ! is_array($query)) {
                    return false;
                }
                $found = null;
                foreach ($query as $qk => $qv) {
                    if (strtolower((string) $qk) === $paramKey) {
                        $found = (string) $qv;
                        break;
                    }
                }
                if ($found === null) {
                    return false;
                }
                if ($paramVal === null) {
                    return true;
                }

                return $found === $paramVal;
            }
        }

        return false;
    }

    /**
     * Build matcher context from resolver output + optional request hints.
     *
     * @param  array{0: string, 1: ?\Illuminate\Database\Eloquent\Model, 2?: string}  $pathContext
     * @return array{
     *   context: string,
     *   content_type: string,
     *   record_id: int|null,
     *   query: array<string, string>,
     *   device: string|null,
     *   role: string|null,
     *   authenticated: bool
     * }
     */
    public static function contextFromResolver(
        string $contentType,
        ?\Illuminate\Database\Eloquent\Model $record,
        ?string $routeContext = null,
        ?Request $request = null,
    ): array {
        $context = $routeContext !== null && $routeContext !== ''
            ? $routeContext
            : $contentType;

        $query = [];
        $device = null;
        $role = null;
        $authenticated = false;

        if ($request !== null) {
            foreach ($request->query() as $k => $v) {
                if (is_scalar($v)) {
                    $query[(string) $k] = (string) $v;
                }
            }
            $device = strtolower(trim((string) $request->header('X-Device-Breakpoint', '')));
            if ($device === '') {
                $device = null;
            }
            $user = $request->user();
            if ($user !== null) {
                $authenticated = true;
                $roleName = method_exists($user, 'getRoleNames')
                    ? $user->getRoleNames()->first()
                    : null;
                $role = $roleName !== null ? strtolower((string) $roleName) : null;
            }
        }

        return [
            'context' => $context,
            'content_type' => $contentType,
            'record_id' => $record !== null ? (int) $record->getKey() : null,
            'query' => $query,
            'device' => $device,
            'role' => $role,
            'authenticated' => $authenticated,
        ];
    }
}
