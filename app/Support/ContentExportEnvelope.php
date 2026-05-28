<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Shared JSON envelope for locale-aware content export/import.
 */
final class ContentExportEnvelope
{
    public const FORMAT = 'credocode.content-export';

    public const VERSION = 1;

    public const ENTITY_CATEGORIES = 'categories';

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function build(string $entity, string $locale, array $items): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'entity' => $entity,
            'locale' => strtolower(trim($locale)),
            'exported_at' => now()->toIso8601String(),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public static function validate(array $payload, string $expectedEntity, string $expectedLocale): void
    {
        $errors = [];

        if (($payload['format'] ?? null) !== self::FORMAT) {
            $errors['format'] = ['Invalid export format. Expected '.self::FORMAT.'.'];
        }

        if ((int) ($payload['version'] ?? 0) !== self::VERSION) {
            $errors['version'] = ['Unsupported export version. Expected '.self::VERSION.'.'];
        }

        if (($payload['entity'] ?? null) !== $expectedEntity) {
            $errors['entity'] = ['Invalid entity. Expected '.$expectedEntity.'.'];
        }

        $fileLocale = strtolower(trim((string) ($payload['locale'] ?? '')));
        $expected = strtolower(trim($expectedLocale));
        if ($fileLocale === '' || $fileLocale !== $expected) {
            $errors['locale'] = ['File locale must match the current content locale ('.$expected.').'];
        }

        if (! isset($payload['items']) || ! is_array($payload['items'])) {
            $errors['items'] = ['Items must be an array.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
