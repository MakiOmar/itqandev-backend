<?php

namespace App\Support;

use Closure;

/**
 * Per-request value cache. Bound as a scoped singleton so queue workers and tests
 * start each job/request empty (`once()` is not reset between queue jobs).
 */
final class RequestMemo
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $resolve();
        }

        return $this->values[$key];
    }

    public function forget(string $key): void
    {
        unset($this->values[$key]);
    }
}
