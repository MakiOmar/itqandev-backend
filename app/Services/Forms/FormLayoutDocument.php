<?php

namespace App\Services\Forms;

use App\Services\Appearance\AppearanceLocalizedSettings;
use App\Services\Appearance\BuilderStyleDocument;
use App\Services\Appearance\LayoutHideOn;
use Illuminate\Support\Str;

/**
 * Normalize / present Forms layout (rows → fields) and actions documents.
 */
final class FormLayoutDocument
{
    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public static function normalizeLayout(mixed $input): array
    {
        $rowsIn = [];
        if (is_array($input)) {
            if (isset($input['rows']) && is_array($input['rows'])) {
                $rowsIn = $input['rows'];
            } elseif (array_is_list($input)) {
                $rowsIn = $input;
            }
        }

        $counts = [];
        $rows = [];
        foreach ($rowsIn as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fields = [];
            $fieldsIn = $row['fields'] ?? [];
            if (! is_array($fieldsIn)) {
                $fieldsIn = [];
            }
            foreach ($fieldsIn as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $type = (string) ($field['type'] ?? '');
                if (! FormFieldRegistry::has($type) || $type === 'honeypot') {
                    continue;
                }
                $max = FormFieldRegistry::maxInstances($type);
                $counts[$type] = ($counts[$type] ?? 0) + 1;
                if ($max !== null && $counts[$type] > $max) {
                    continue;
                }
                $settings = is_array($field['settings'] ?? null)
                    ? $field['settings']
                    : FormFieldRegistry::defaultSettings($type);
                $fieldRow = [
                    'id' => self::id($field['id'] ?? null),
                    'type' => $type,
                    'span' => self::normalizeSpan($field['span'] ?? null),
                    'settings' => $settings,
                ];
                $fieldRow = LayoutHideOn::appendTo($fieldRow, $field['hide_on'] ?? null);
                $fields[] = BuilderStyleDocument::appendTo($fieldRow, $field['styles'] ?? null);
            }
            if ($fields === []) {
                continue;
            }
            $rowOut = [
                'id' => self::id($row['id'] ?? null),
                'fields' => $fields,
            ];
            $rows[] = LayoutHideOn::appendTo($rowOut, $row['hide_on'] ?? null);
        }

        if ($rows === []) {
            $rows[] = [
                'id' => self::id(null),
                'fields' => [
                    [
                        'id' => self::id(null),
                        'type' => 'text',
                        'span' => self::normalizeSpan(['desktop' => 6, 'tablet' => 6, 'mobile' => 12]),
                        'settings' => array_merge(FormFieldRegistry::defaultSettings('text'), [
                            'label' => 'Name',
                            'name' => 'name',
                            'required' => true,
                        ]),
                    ],
                    [
                        'id' => self::id(null),
                        'type' => 'email',
                        'span' => self::normalizeSpan(['desktop' => 6, 'tablet' => 6, 'mobile' => 12]),
                        'settings' => array_merge(FormFieldRegistry::defaultSettings('email'), [
                            'label' => 'Email',
                            'name' => 'email',
                            'required' => true,
                        ]),
                    ],
                ],
            ];
            $rows[] = [
                'id' => self::id(null),
                'fields' => [
                    [
                        'id' => self::id(null),
                        'type' => 'textarea',
                        'span' => self::normalizeSpan(12),
                        'settings' => array_merge(FormFieldRegistry::defaultSettings('textarea'), [
                            'label' => 'Message',
                            'name' => 'message',
                            'required' => true,
                        ]),
                    ],
                ],
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * @return list<array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>
     */
    public static function normalizeActions(mixed $input): array
    {
        $list = [];
        if (is_array($input)) {
            $list = array_is_list($input) ? $input : ($input['actions'] ?? []);
        }
        if (! is_array($list) || $list === []) {
            return FormActionRegistry::defaultActions();
        }

        $out = [];
        $seenStore = false;
        foreach ($list as $action) {
            if (! is_array($action)) {
                continue;
            }
            $type = (string) ($action['type'] ?? '');
            if (! FormActionRegistry::has($type)) {
                continue;
            }
            if ($type === 'store_submission') {
                $seenStore = true;
            }
            $settings = is_array($action['settings'] ?? null)
                ? array_merge(FormActionRegistry::defaultSettings($type), $action['settings'])
                : FormActionRegistry::defaultSettings($type);
            $out[] = [
                'id' => self::id($action['id'] ?? null),
                'type' => $type,
                'enabled' => array_key_exists('enabled', $action) ? (bool) $action['enabled'] : true,
                'settings' => $settings,
            ];
        }

        if (! $seenStore) {
            array_unshift($out, [
                'id' => 'store_submission_default',
                'type' => 'store_submission',
                'enabled' => true,
                'settings' => FormActionRegistry::defaultSettings('store_submission'),
            ]);
        }

        return $out === [] ? FormActionRegistry::defaultActions() : $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeSettings(mixed $input): array
    {
        $in = is_array($input) ? $input : [];

        $mode = (string) ($in['success_mode'] ?? 'message');
        $captcha = (string) ($in['captcha'] ?? 'none');

        return [
            'submit_label' => (string) ($in['submit_label'] ?? 'Submit'),
            'success_message' => (string) ($in['success_message'] ?? 'Thank you. We received your submission.'),
            'error_message' => (string) ($in['error_message'] ?? 'Something went wrong. Please try again.'),
            'success_mode' => in_array($mode, ['message', 'redirect'], true) ? $mode : 'message',
            'honeypot' => array_key_exists('honeypot', $in) ? (bool) $in['honeypot'] : true,
            'captcha' => in_array($captcha, ['none', 'turnstile', 'recaptcha_v2', 'recaptcha_v3'], true)
                ? $captcha
                : 'none',
            'store_ip' => array_key_exists('store_ip', $in) ? (bool) $in['store_ip'] : true,
            'translations' => is_array($in['translations'] ?? null) ? $in['translations'] : [],
        ];
    }

    /**
     * Flatten fields for validation / submit.
     *
     * @param  array{rows?: list<array<string, mixed>>}|list<mixed>  $layout
     * @return list<array<string, mixed>>
     */
    public static function flattenFields(array $layout): array
    {
        $normalized = self::normalizeLayout($layout);
        $fields = [];
        foreach ($normalized['rows'] as $row) {
            foreach ($row['fields'] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Present form definition for public embed.
     *
     * @return array<string, mixed>
     */
    public static function presentPublic(array $layout, array $settings, string $locale, string $primaryLocale): array
    {
        $normalized = self::normalizeLayout($layout);
        $rows = [];
        foreach ($normalized['rows'] as $row) {
            $fields = [];
            foreach ($row['fields'] as $field) {
                $type = (string) $field['type'];
                $resolved = AppearanceLocalizedSettings::resolveForLocale(
                    is_array($field['settings'] ?? null) ? $field['settings'] : [],
                    $locale,
                    $primaryLocale,
                    FormFieldRegistry::translatableKeys($type)
                );
                $fieldOut = [
                    'id' => $field['id'],
                    'type' => $type,
                    'span' => $field['span'],
                    'settings' => $resolved,
                ];
                $fieldOut = LayoutHideOn::appendTo($fieldOut, $field['hide_on'] ?? null);
                $fields[] = BuilderStyleDocument::appendTo($fieldOut, $field['styles'] ?? null);
            }
            $rowOut = [
                'id' => $row['id'],
                'fields' => $fields,
            ];
            $rows[] = LayoutHideOn::appendTo($rowOut, $row['hide_on'] ?? null);
        }

        $settingKeys = ['submit_label', 'success_message', 'error_message'];
        $resolvedSettings = AppearanceLocalizedSettings::resolveForLocale($settings, $locale, $primaryLocale, $settingKeys);

        return [
            'rows' => $rows,
            'settings' => array_merge($settings, $resolvedSettings, [
                'captcha' => $settings['captcha'] ?? 'none',
                'honeypot' => $settings['honeypot'] ?? true,
                'success_mode' => $settings['success_mode'] ?? 'message',
            ]),
            'locale' => $locale,
            'primary_locale' => $primaryLocale,
        ];
    }

    /**
     * @return array{mobile: int, tablet: int, desktop: int}
     */
    public static function normalizeSpan(mixed $span): array
    {
        if (is_int($span) || is_float($span) || (is_string($span) && is_numeric($span))) {
            $n = max(1, min(12, (int) $span));

            return ['mobile' => 12, 'tablet' => $n, 'desktop' => $n];
        }
        $arr = is_array($span) ? $span : [];
        $desktop = max(1, min(12, (int) ($arr['desktop'] ?? 12)));
        $tablet = max(1, min(12, (int) ($arr['tablet'] ?? $desktop)));
        $mobile = max(1, min(12, (int) ($arr['mobile'] ?? 12)));

        return ['mobile' => $mobile, 'tablet' => $tablet, 'desktop' => $desktop];
    }

    private static function id(mixed $id): string
    {
        $s = is_string($id) ? trim($id) : '';

        return $s !== '' ? $s : (string) Str::uuid();
    }
}
