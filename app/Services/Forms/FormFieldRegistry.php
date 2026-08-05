<?php

namespace App\Services\Forms;

/**
 * Canonical form field types for the Forms builder.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int, translatable?: bool}
 * @phpstan-type FieldTypeDef array{label: string, max_instances: int|null, palette: bool, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class FormFieldRegistry
{
    /**
     * @return array<string, FieldTypeDef>
     */
    public static function all(): array
    {
        return [
            'text' => self::inputField('Text', ['placeholder' => '', 'required' => true]),
            'email' => self::inputField('Email', ['placeholder' => '', 'required' => true]),
            'tel' => self::inputField('Phone', ['placeholder' => '', 'required' => false]),
            'url' => self::inputField('URL', ['placeholder' => '', 'required' => false]),
            'number' => self::inputField('Number', ['placeholder' => '', 'required' => false, 'min' => null, 'max' => null], [
                ['key' => 'min', 'type' => 'number', 'label' => 'Min', 'translatable' => false],
                ['key' => 'max', 'type' => 'number', 'label' => 'Max', 'translatable' => false],
            ]),
            'textarea' => self::inputField('Textarea', ['placeholder' => '', 'required' => true, 'rows' => 4], [
                ['key' => 'rows', 'type' => 'number', 'label' => 'Rows', 'min' => 2, 'max' => 20, 'translatable' => false],
            ]),
            'select' => self::choiceField('Select', true),
            'radio' => self::choiceField('Radio', true),
            'checkbox' => [
                'label' => 'Checkbox',
                'max_instances' => null,
                'palette' => true,
                'default_settings' => [
                    'label' => 'Checkbox',
                    'required' => false,
                    'options' => ['Option A', 'Option B'],
                    'help' => '',
                    'name' => '',
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field name (optional)', 'translatable' => false],
                    ['key' => 'required', 'type' => 'boolean', 'label' => 'Required', 'translatable' => false],
                    ['key' => 'options', 'type' => 'json', 'label' => 'Options (JSON array of strings)', 'translatable' => true],
                    ['key' => 'help', 'type' => 'text', 'label' => 'Help text'],
                ],
            ],
            'date' => self::inputField('Date', ['required' => false]),
            'hidden' => [
                'label' => 'Hidden',
                'max_instances' => null,
                'palette' => true,
                'default_settings' => [
                    'label' => 'Hidden',
                    'name' => '',
                    'value' => '',
                    'required' => false,
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Admin label'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field name', 'translatable' => false],
                    ['key' => 'value', 'type' => 'text', 'label' => 'Value', 'translatable' => true],
                ],
            ],
            'consent' => [
                'label' => 'Consent',
                'max_instances' => 3,
                'palette' => true,
                'default_settings' => [
                    'label' => 'I agree to the privacy policy',
                    'required' => true,
                    'help' => '',
                    'name' => 'consent',
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Consent text'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field name', 'translatable' => false],
                    ['key' => 'required', 'type' => 'boolean', 'label' => 'Required', 'translatable' => false],
                    ['key' => 'help', 'type' => 'text', 'label' => 'Help text'],
                ],
            ],
            'file' => [
                'label' => 'File upload',
                'max_instances' => 5,
                'palette' => true,
                'default_settings' => [
                    'label' => 'Attachment',
                    'required' => false,
                    'accept' => 'image/*,.pdf',
                    'max_kb' => 5120,
                    'help' => '',
                    'name' => '',
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field name (optional)', 'translatable' => false],
                    ['key' => 'required', 'type' => 'boolean', 'label' => 'Required', 'translatable' => false],
                    ['key' => 'accept', 'type' => 'text', 'label' => 'Accept', 'translatable' => false],
                    ['key' => 'max_kb', 'type' => 'number', 'label' => 'Max size (KB)', 'min' => 64, 'max' => 20480, 'translatable' => false],
                    ['key' => 'help', 'type' => 'text', 'label' => 'Help text'],
                ],
            ],
            'honeypot' => [
                'label' => 'Honeypot',
                'max_instances' => 1,
                'palette' => false,
                'default_settings' => [
                    'label' => 'Website',
                    'name' => 'website_url',
                ],
                'settings_fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field name', 'translatable' => false],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extraDefaults
     * @param  list<SettingsField>  $extraFields
     * @return FieldTypeDef
     */
    private static function inputField(string $label, array $extraDefaults = [], array $extraFields = []): array
    {
        return [
            'label' => $label,
            'max_instances' => null,
            'palette' => true,
            'default_settings' => array_merge([
                'label' => $label,
                'help' => '',
                'name' => '',
            ], $extraDefaults),
            'settings_fields' => array_merge([
                ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                ['key' => 'name', 'type' => 'text', 'label' => 'Field name (optional)', 'translatable' => false],
                ['key' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                ['key' => 'required', 'type' => 'boolean', 'label' => 'Required', 'translatable' => false],
                ['key' => 'help', 'type' => 'text', 'label' => 'Help text'],
                ['key' => 'visible_when_field', 'type' => 'text', 'label' => 'Show when field name/id (optional)', 'translatable' => false],
                ['key' => 'visible_when_value', 'type' => 'text', 'label' => 'Show when value equals (optional)', 'translatable' => false],
            ], $extraFields),
        ];
    }

    /**
     * @return FieldTypeDef
     */
    private static function choiceField(string $label, bool $requiredDefault): array
    {
        return [
            'label' => $label,
            'max_instances' => null,
            'palette' => true,
            'default_settings' => [
                'label' => $label,
                'required' => $requiredDefault,
                'options' => ['Option A', 'Option B', 'Option C'],
                'help' => '',
                'name' => '',
                'placeholder' => '',
            ],
            'settings_fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                ['key' => 'name', 'type' => 'text', 'label' => 'Field name (optional)', 'translatable' => false],
                ['key' => 'required', 'type' => 'boolean', 'label' => 'Required', 'translatable' => false],
                ['key' => 'options', 'type' => 'json', 'label' => 'Options (JSON array of strings)', 'translatable' => true],
                ['key' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                ['key' => 'help', 'type' => 'text', 'label' => 'Help text'],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $type): array
    {
        return self::all()[$type]['default_settings'] ?? [];
    }

    public static function maxInstances(string $type): ?int
    {
        $max = self::all()[$type]['max_instances'] ?? null;

        return is_int($max) ? $max : null;
    }

    public static function isPalette(string $type): bool
    {
        return (bool) (self::all()[$type]['palette'] ?? true);
    }

    /**
     * @return list<array{type: string, label: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
     */
    public static function forAdmin(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            if (! ($def['palette'] ?? true)) {
                continue;
            }
            $out[] = [
                'type' => $type,
                'label' => $def['label'],
                'max_instances' => $def['max_instances'],
                'default_settings' => $def['default_settings'],
                'settings_fields' => $def['settings_fields'],
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function translatableKeys(string $type): array
    {
        $keys = [];
        foreach (self::all()[$type]['settings_fields'] ?? [] as $field) {
            $explicit = $field['translatable'] ?? null;
            $isTextLike = in_array($field['type'] ?? '', ['text', 'textarea', 'json'], true);
            if ($explicit === true || ($explicit === null && $isTextLike)) {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }
}
