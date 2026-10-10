<?php

namespace App\Services\ContentTypes;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Stringable;

/**
 * One allowlisted dynamic tag (`{{id}}`) shown in the builder dropdown.
 */
final class TagDefinition
{
    public const KINDS = ['text', 'url', 'image', 'html', 'date', 'number'];

    public function __construct(
        public readonly string $id,
        public readonly string $group,
        public readonly string $label,
        public readonly string $kind = 'text',
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown dynamic tag kind [{$kind}] for [{$id}].");
        }
    }

    /**
     * @return array{id: string, group: string, label: string, kind: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'label' => $this->label,
            'kind' => $this->kind,
        ];
    }

    /**
     * Read a field for a tag value: an attribute name or a closure receiving the model.
     */
    public static function read(Model $model, string|Closure $source): string
    {
        return self::stringify($source instanceof Closure ? $source($model) : $model->getAttribute($source));
    }

    public static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }
}
