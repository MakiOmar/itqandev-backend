<?php

namespace App\Services\Appearance;

/**
 * Sanitize leaf `styles` maps (desktop / tablet / mobile). Unknown keys dropped.
 *
 * @phpstan-type StyleBag array<string, mixed>
 * @phpstan-type BuilderStyles array{desktop?: StyleBag, tablet?: StyleBag, mobile?: StyleBag}
 */
final class BuilderStyleDocument
{
    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

    public const UNITS = ['px', '%', 'em', 'rem', 'vw', 'vh', 'auto'];

    public const OBJECT_FIT = ['fill', 'cover', 'contain', 'none', 'scale-down'];

    public const ALIGN = ['left', 'center', 'right'];

    public const OVERFLOW = ['visible', 'hidden', 'auto', 'clip'];

    public const BORDER_STYLE = ['none', 'solid', 'dashed', 'dotted', 'double'];

    public const HOVER_ANIMATION = ['none', 'grow', 'shrink', 'float', 'sink'];

    public const FONT_WEIGHT = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];

    public const TEXT_TRANSFORM = ['none', 'uppercase', 'lowercase', 'capitalize'];

    public const FONT_STYLE = ['normal', 'italic'];

    public const TEXT_DECORATION = ['none', 'underline', 'line-through'];

    public const OBJECT_POSITION = [
        'center', 'top', 'bottom', 'left', 'right',
        'top left', 'top right', 'bottom left', 'bottom right',
    ];

    /** Scalar / structured keys allowed in a style bag. */
    private const KEYS = [
        'align', 'width', 'max_width', 'height', 'object_fit', 'object_position', 'overflow', 'z_index',
        'margin', 'padding',
        'opacity', 'filters',
        'border_style', 'border_width', 'border_color', 'radius', 'box_shadow',
        'hover_opacity', 'hover_filters', 'hover_transition', 'hover_animation', 'hover_box_shadow',
        'caption_align', 'caption_color', 'caption_font_size', 'caption_font_weight',
        'caption_transform', 'caption_font_style', 'caption_decoration',
        'caption_line_height', 'caption_letter_spacing', 'caption_spacing',
        'custom_css',
        'font_family', 'font_size', 'font_weight', 'line_height', 'letter_spacing',
        'text_color', 'text_transform', 'font_style', 'text_decoration',
        'type_role',
        'tab_font_size', 'tab_font_weight', 'tab_color', 'tab_bg', 'tab_hover_color', 'tab_hover_bg',
        'tab_active_color', 'tab_active_bg', 'tab_indicator_color', 'tab_radius',
        'nav_size', 'nav_icon_size', 'nav_color', 'nav_bg', 'nav_border_color', 'nav_hover_color',
        'nav_hover_bg', 'nav_radius',
        'link_font_size', 'link_font_weight', 'link_transform', 'link_letter_spacing', 'link_color',
        'link_hover_color',
        'title_font_size', 'title_font_weight', 'title_line_height', 'title_letter_spacing', 'title_transform',
        'title_color',
        'subtitle_font_size', 'subtitle_font_weight', 'subtitle_line_height', 'subtitle_letter_spacing',
        'subtitle_transform', 'subtitle_color',
    ];

    /** Button parts (e.g. hero CTAs); each gets every BUTTON_* suffix as `{part}_{suffix}`. */
    private const BUTTON_PARTS = ['btn_primary', 'btn_secondary'];

    private const BUTTON_COLOR_SUFFIXES = ['color', 'bg', 'border_color', 'hover_color', 'hover_bg', 'hover_border_color'];

    private const BUTTON_LENGTH_SUFFIXES = ['font_size', 'letter_spacing', 'border_width', 'radius', 'min_width', 'padding_x', 'padding_y'];

    private const BUTTON_SHADOW_SUFFIXES = ['shadow', 'hover_shadow'];

    /**
     * @return list<string>
     */
    private static function allowedKeys(): array
    {
        static $keys = null;
        if ($keys === null) {
            $suffixes = [
                ...self::BUTTON_COLOR_SUFFIXES, ...self::BUTTON_LENGTH_SUFFIXES, ...self::BUTTON_SHADOW_SUFFIXES,
                'font_weight', 'transform',
            ];
            $keys = self::KEYS;
            foreach (self::BUTTON_PARTS as $part) {
                foreach ($suffixes as $suffix) {
                    $keys[] = "{$part}_{$suffix}";
                }
            }
        }

        return $keys;
    }

    private static function normalizeButtonValue(string $suffix, mixed $value): mixed
    {
        return match (true) {
            in_array($suffix, self::BUTTON_COLOR_SUFFIXES, true) => self::color($value),
            in_array($suffix, self::BUTTON_LENGTH_SUFFIXES, true) => self::length($value),
            in_array($suffix, self::BUTTON_SHADOW_SUFFIXES, true) => self::shadow($value),
            $suffix === 'font_weight' => self::enum((string) $value, self::FONT_WEIGHT),
            $suffix === 'transform' => self::enum($value, self::TEXT_TRANSFORM),
            default => null,
        };
    }

    /**
     * @param  mixed  $raw
     * @return BuilderStyles|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $out = [];
        foreach (self::BREAKPOINTS as $bp) {
            if (! isset($raw[$bp]) || ! is_array($raw[$bp])) {
                continue;
            }
            $bag = self::normalizeBag($raw[$bp]);
            if ($bag !== []) {
                $out[$bp] = $bag;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function appendTo(array $node, mixed $raw): array
    {
        $styles = self::normalize($raw);
        if ($styles === null) {
            return $node;
        }
        $node['styles'] = $styles;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return StyleBag
     */
    private static function normalizeBag(array $bag): array
    {
        $out = [];
        foreach (self::allowedKeys() as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }
            $value = self::normalizeValue($key, $bag[$key]);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function normalizeValue(string $key, mixed $value): mixed
    {
        if (preg_match('/^btn_(?:primary|secondary)_(.+)$/', $key, $m) === 1) {
            return self::normalizeButtonValue($m[1], $value);
        }

        return match ($key) {
            'align', 'caption_align' => self::enum($value, self::ALIGN),
            'object_fit' => self::enum($value, self::OBJECT_FIT),
            'object_position' => self::enum($value, self::OBJECT_POSITION),
            'overflow' => self::enum($value, self::OVERFLOW),
            'border_style' => self::enum($value, self::BORDER_STYLE),
            'hover_animation' => self::enum($value, self::HOVER_ANIMATION),
            'caption_font_weight' => self::enum((string) $value, self::FONT_WEIGHT),
            'caption_transform' => self::enum($value, self::TEXT_TRANSFORM),
            'caption_font_style' => self::enum($value, self::FONT_STYLE),
            'caption_decoration' => self::enum($value, self::TEXT_DECORATION),
            'width', 'max_width', 'height', 'border_width', 'radius',
            'caption_font_size', 'caption_line_height', 'caption_letter_spacing', 'caption_spacing' => self::length($value),
            'margin', 'padding' => self::dimensions($value),
            'opacity', 'hover_opacity' => self::ratio($value),
            'z_index' => self::intInRange($value, -9999, 9999),
            'hover_transition' => self::intInRange($value, 0, 5000),
            'border_color', 'caption_color', 'text_color' => self::color($value),
            'font_family' => self::fontFamily($value),
            'font_size', 'line_height', 'letter_spacing' => self::length($value),
            'font_weight' => self::enum((string) $value, self::FONT_WEIGHT),
            'text_transform' => self::enum($value, self::TEXT_TRANSFORM),
            'font_style' => self::enum($value, self::FONT_STYLE),
            'text_decoration' => self::enum($value, self::TEXT_DECORATION),
            'type_role' => self::enum($value, ['heading', 'body', 'accent']),
            'tab_color', 'tab_bg', 'tab_hover_color', 'tab_hover_bg', 'tab_active_color', 'tab_active_bg',
            'tab_indicator_color', 'nav_color', 'nav_bg', 'nav_border_color', 'nav_hover_color', 'nav_hover_bg',
            'link_color', 'link_hover_color', 'title_color', 'subtitle_color' => self::color($value),
            'tab_font_size', 'tab_radius', 'nav_size', 'nav_icon_size', 'nav_radius',
            'link_font_size', 'link_letter_spacing',
            'title_font_size', 'title_line_height', 'title_letter_spacing',
            'subtitle_font_size', 'subtitle_line_height', 'subtitle_letter_spacing' => self::length($value),
            'tab_font_weight', 'link_font_weight', 'title_font_weight', 'subtitle_font_weight' => self::enum((string) $value, self::FONT_WEIGHT),
            'link_transform', 'title_transform', 'subtitle_transform' => self::enum($value, self::TEXT_TRANSFORM),
            'filters', 'hover_filters' => self::filters($value),
            'box_shadow', 'hover_box_shadow' => self::shadow($value),
            'custom_css' => self::customCss($value),
            default => null,
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function enum(mixed $value, array $allowed): ?string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '' || ! in_array($v, $allowed, true)) {
            return null;
        }

        return $v;
    }

    /**
     * @return array{value: float, unit: string}|null
     */
    private static function length(mixed $value): ?array
    {
        if (is_array($value)) {
            $unit = strtolower(trim((string) ($value['unit'] ?? 'px')));
            if (! in_array($unit, self::UNITS, true)) {
                return null;
            }
            if ($unit === 'auto') {
                return ['value' => 0.0, 'unit' => 'auto'];
            }
            $n = self::finiteFloat($value['value'] ?? null);
            if ($n === null) {
                return null;
            }

            return ['value' => $n, 'unit' => $unit];
        }

        $s = trim((string) $value);
        if ($s === '' || strtolower($s) === 'auto') {
            return $s === '' ? null : ['value' => 0.0, 'unit' => 'auto'];
        }
        if (preg_match('/^(-?\d+(?:\.\d+)?)(px|%|em|rem|vw|vh)$/i', $s, $m) !== 1) {
            return null;
        }

        return ['value' => (float) $m[1], 'unit' => strtolower($m[2])];
    }

    /**
     * @return array{top: float, right: float, bottom: float, left: float, unit: string, linked: bool}|null
     */
    private static function dimensions(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $unit = strtolower(trim((string) ($value['unit'] ?? 'px')));
        if (! in_array($unit, self::UNITS, true) || $unit === 'auto') {
            $unit = 'px';
        }
        $top = self::finiteFloat($value['top'] ?? 0) ?? 0.0;
        $right = self::finiteFloat($value['right'] ?? $top) ?? $top;
        $bottom = self::finiteFloat($value['bottom'] ?? $top) ?? $top;
        $left = self::finiteFloat($value['left'] ?? $right) ?? $right;

        return [
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left,
            'unit' => $unit,
            'linked' => filter_var($value['linked'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private static function ratio(mixed $value): ?float
    {
        $n = self::finiteFloat($value);
        if ($n === null) {
            return null;
        }

        return max(0.0, min(1.0, $n));
    }

    private static function intInRange(mixed $value, int $min, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return max($min, min($max, $n));
    }

    private static function color(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $s) === 1) {
            return strtolower($s);
        }
        if (preg_match('/^rgba?\(\s*[\d.]+(?:\s*,\s*[\d.]+){2,3}\s*\)$/i', $s) === 1) {
            return $s;
        }

        return null;
    }

    /**
     * @return array{blur: float, brightness: float, contrast: float, saturate: float, hue: float}|null
     */
    private static function filters(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $blur = max(0.0, self::finiteFloat($value['blur'] ?? 0) ?? 0.0);
        $brightness = self::finiteFloat($value['brightness'] ?? 100) ?? 100.0;
        $contrast = self::finiteFloat($value['contrast'] ?? 100) ?? 100.0;
        $saturate = self::finiteFloat($value['saturate'] ?? 100) ?? 100.0;
        $hue = self::finiteFloat($value['hue'] ?? 0) ?? 0.0;
        if ($blur === 0.0 && $brightness === 100.0 && $contrast === 100.0 && $saturate === 100.0 && $hue === 0.0) {
            return null;
        }

        return [
            'blur' => min(50.0, $blur),
            'brightness' => max(0.0, min(200.0, $brightness)),
            'contrast' => max(0.0, min(200.0, $contrast)),
            'saturate' => max(0.0, min(200.0, $saturate)),
            'hue' => max(-180.0, min(180.0, $hue)),
        ];
    }

    /**
     * @return array{color: string, h: float, v: float, blur: float, spread: float, inset: bool}|null
     */
    private static function shadow(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $color = self::color($value['color'] ?? '#00000066');
        if ($color === null) {
            $color = '#00000066';
        }

        return [
            'color' => $color,
            'h' => self::finiteFloat($value['h'] ?? 0) ?? 0.0,
            'v' => self::finiteFloat($value['v'] ?? 0) ?? 0.0,
            'blur' => max(0.0, self::finiteFloat($value['blur'] ?? 0) ?? 0.0),
            'spread' => self::finiteFloat($value['spread'] ?? 0) ?? 0.0,
            'inset' => filter_var($value['inset'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private static function fontFamily(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '' || mb_strlen($s) > 80) {
            return null;
        }
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9 _-]*$/', $s) !== 1) {
            return null;
        }

        return $s;
    }

    private static function customCss(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/<\/style>/i', '', $s) ?? '';
        $s = preg_replace('/@import\b[^;]*;?/i', '', $s) ?? '';
        $s = preg_replace('/expression\s*\(/i', '', $s) ?? '';
        $s = preg_replace('/javascript\s*:/i', '', $s) ?? '';
        $s = preg_replace('/-moz-binding/i', '', $s) ?? '';
        $s = preg_replace('/behavior\s*:/i', '', $s) ?? '';
        $s = trim($s);

        return $s === '' ? null : mb_substr($s, 0, 8000);
    }

    private static function finiteFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return is_finite($n) ? $n : null;
    }
}
