<?php

namespace App\Services\Forms;

/**
 * Canonical submit actions for Forms.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, min?: int, max?: int, translatable?: bool}
 * @phpstan-type ActionTypeDef array{label: string, default_enabled: bool, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class FormActionRegistry
{
    /**
     * @return array<string, ActionTypeDef>
     */
    public static function all(): array
    {
        return [
            'store_submission' => [
                'label' => 'Store submission',
                'default_enabled' => true,
                'default_settings' => [
                    'store_ip' => true,
                ],
                'settings_fields' => [
                    ['key' => 'store_ip', 'type' => 'boolean', 'label' => 'Store IP / user agent', 'translatable' => false],
                ],
            ],
            'email_admins' => [
                'label' => 'Email admins',
                'default_enabled' => true,
                'default_settings' => [
                    'recipients' => '',
                    'subject' => 'New form submission: {{form_title}}',
                    'notify_database' => true,
                ],
                'settings_fields' => [
                    ['key' => 'recipients', 'type' => 'text', 'label' => 'Recipients (comma-separated emails; empty = admins)', 'translatable' => false],
                    ['key' => 'subject', 'type' => 'text', 'label' => 'Subject template'],
                    ['key' => 'notify_database', 'type' => 'boolean', 'label' => 'Also create in-app notification', 'translatable' => false],
                ],
            ],
            'email_autoreply' => [
                'label' => 'Auto-reply to submitter',
                'default_enabled' => false,
                'default_settings' => [
                    'email_field' => '',
                    'subject' => 'We received your message',
                    'body' => 'Thank you for contacting us. We will get back to you soon.',
                ],
                'settings_fields' => [
                    ['key' => 'email_field', 'type' => 'text', 'label' => 'Email field name/id (empty = first email field)', 'translatable' => false],
                    ['key' => 'subject', 'type' => 'text', 'label' => 'Subject'],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Body'],
                ],
            ],
            'redirect' => [
                'label' => 'Redirect after submit',
                'default_enabled' => false,
                'default_settings' => [
                    'url' => '',
                    'page_slug' => '',
                ],
                'settings_fields' => [
                    ['key' => 'url', 'type' => 'text', 'label' => 'External URL', 'translatable' => false],
                    ['key' => 'page_slug', 'type' => 'text', 'label' => 'Or internal page slug', 'translatable' => false],
                ],
            ],
            'webhook' => [
                'label' => 'Webhook',
                'default_enabled' => false,
                'default_settings' => [
                    'url' => '',
                    'secret' => '',
                ],
                'settings_fields' => [
                    ['key' => 'url', 'type' => 'text', 'label' => 'Webhook URL', 'translatable' => false],
                    ['key' => 'secret', 'type' => 'text', 'label' => 'Optional HMAC secret', 'translatable' => false],
                ],
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

    /**
     * @return list<array{type: string, label: string, default_enabled: bool, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
     */
    public static function forAdmin(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[] = [
                'type' => $type,
                'label' => $def['label'],
                'default_enabled' => $def['default_enabled'],
                'default_settings' => $def['default_settings'],
                'settings_fields' => $def['settings_fields'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>
     */
    public static function defaultActions(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            if (! ($def['default_enabled'] ?? false) && $type !== 'store_submission') {
                continue;
            }
            if ($type === 'webhook') {
                continue;
            }
            $out[] = [
                'id' => $type.'_default',
                'type' => $type,
                'enabled' => (bool) ($def['default_enabled'] ?? false),
                'settings' => $def['default_settings'],
            ];
        }

        // Ensure store is first and enabled
        usort($out, static function ($a, $b) {
            if ($a['type'] === 'store_submission') {
                return -1;
            }
            if ($b['type'] === 'store_submission') {
                return 1;
            }

            return 0;
        });

        return array_values(array_filter($out, static fn ($a) => in_array($a['type'], ['store_submission', 'email_admins'], true)));
    }
}
