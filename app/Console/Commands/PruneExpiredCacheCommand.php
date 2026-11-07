<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The database cache store only deletes an expired row when that exact key is read again. Versioned
 * keys are never read after a bump, so without this the `cache` table grows forever.
 */
class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'cache:prune-expired {--store= : Cache store name (defaults to the default store)}';

    protected $description = 'Delete expired rows from a database cache store.';

    /** Small batches keep each delete's row locks short on a busy table. */
    private const CHUNK = 1000;

    public function handle(): int
    {
        $name = (string) ($this->option('store') ?: config('cache.default'));
        $config = config("cache.stores.{$name}");
        if (! is_array($config) || ($config['driver'] ?? null) !== 'database') {
            $this->info("Cache store [{$name}] is not a database store; nothing to prune.");

            return self::SUCCESS;
        }

        $rows = static fn (): Builder => DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'cache');
        $now = now()->getTimestamp();
        $deleted = 0;
        do {
            $keys = $rows()->where('expiration', '<=', $now)->limit(self::CHUNK)->pluck('key')->all();
            if ($keys !== []) {
                $deleted += $rows()->whereIn('key', $keys)->where('expiration', '<=', $now)->delete();
            }
        } while (count($keys) === self::CHUNK);

        $this->info("Deleted {$deleted} expired cache row(s) from [{$name}].");

        return self::SUCCESS;
    }
}
