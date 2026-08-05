<?php

namespace App\Services\Forms;

/**
 * Mutable submit context shared across action handlers.
 */
final class FormSubmissionContext
{
    /**
     * @param  array<string, mixed>  $values  Normalized field values keyed by field id
     * @param  array<string, mixed>  $labeled  Human-readable label => value
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $responseExtras
     */
    public function __construct(
        public array $values,
        public array $labeled,
        public array $fields,
        public string $locale,
        public ?string $ip,
        public ?string $userAgent,
        public array $responseExtras = [],
        public ?FormSubmissionResultHolder $submissionHolder = null,
    ) {}

    public function findEmail(): ?string
    {
        foreach ($this->fields as $field) {
            if (($field['type'] ?? '') === 'email') {
                $id = (string) ($field['id'] ?? '');
                $val = $this->values[$id] ?? null;
                if (is_string($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    return $val;
                }
            }
        }
        foreach ($this->values as $val) {
            if (is_string($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                return $val;
            }
        }

        return null;
    }

    public function valueByFieldNameOrId(string $hint): mixed
    {
        $hint = trim($hint);
        if ($hint === '') {
            return null;
        }
        if (array_key_exists($hint, $this->values)) {
            return $this->values[$hint];
        }
        foreach ($this->fields as $field) {
            $name = (string) ($field['settings']['name'] ?? '');
            $id = (string) ($field['id'] ?? '');
            if ($name === $hint || $id === $hint) {
                return $this->values[$id] ?? null;
            }
        }

        return null;
    }
}
