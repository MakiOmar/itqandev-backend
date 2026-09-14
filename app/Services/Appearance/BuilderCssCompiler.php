<?php

namespace App\Services\Appearance;

/**
 * Compile used layout types + custom CSS into a hashed stylesheet string.
 */
final class BuilderCssCompiler
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array{hash: string, css: string}
     */
    public static function compile(array $sections, string $tokenCss = ''): array
    {
        $types = [];
        $custom = [];
        self::walk($sections, $types, $custom);
        ksort($types);
        $parts = [];
        if ($tokenCss !== '') {
            $parts[] = $tokenCss;
        }
        if (isset($types['lottie'])) {
            $parts[] = '.b-lottie{max-width:100%}@media (prefers-reduced-motion:reduce){.b-lottie-player{animation:none!important}}';
        }
        if (isset($types['flip_box'])) {
            $parts[] = '.b-flip{perspective:1000px}.b-flip-inner{transform-style:preserve-3d;transition:transform .5s}.b-flip:hover .b-flip-inner,.b-flip:focus-within .b-flip-inner{transform:rotateY(180deg)}@media (prefers-reduced-motion:reduce){.b-flip-inner{transition:opacity .3s;transform:none}.b-flip:hover .b-flip-inner{opacity:.92}}';
        }
        if (isset($types['header_menu'])) {
            $parts[] = '.b-mega{display:none}.b-mega-open .b-mega{display:grid}';
        }
        foreach ($custom as $chunk) {
            $parts[] = $chunk;
        }
        $css = implode('', $parts);

        return [
            'hash' => substr(sha1($css), 0, 12),
            'css' => $css,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, true>  $types
     * @param  list<string>  $custom
     */
    private static function walk(array $nodes, array &$types, array &$custom): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = strtolower(trim((string) ($node['type'] ?? '')));
            if ($type !== '') {
                $types[$type] = true;
            }
            $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
            foreach (['desktop', 'tablet', 'mobile'] as $bp) {
                $css = trim((string) ($styles[$bp]['custom_css'] ?? ''));
                if ($css !== '') {
                    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($node['id'] ?? 'x')) ?: 'x';
                    $custom[] = str_replace('selector', '#b-'.$id, $css);
                }
            }
            foreach (['rows', 'columns', 'blocks'] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    self::walk($node[$key], $types, $custom);
                }
            }
        }
    }
}
