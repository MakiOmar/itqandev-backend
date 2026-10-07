<?php

namespace App\Casts;

use App\Support\LoopbackMediaUrls;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Same storage as the `array` cast; on read, loopback `/storage/` URLs are mapped to APP_URL.
 *
 * @implements CastsAttributes<array<mixed>|null, mixed>
 */
class JsonWithAppMediaUrls implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? LoopbackMediaUrls::rewrite($decoded) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
