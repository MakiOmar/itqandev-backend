<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait CountsTableQueries
{
    /**
     * Number of SQL statements reading from `$table` while `$callback` runs.
     */
    protected function queriesAgainst(string $table, callable $callback): int
    {
        $count = 0;
        $listening = true;
        DB::listen(function ($query) use ($table, &$count, &$listening): void {
            if ($listening && preg_match('/\bfrom\s+["`]?'.preg_quote($table, '/').'["`]?/i', $query->sql)) {
                $count++;
            }
        });
        $callback();
        $listening = false;

        return $count;
    }
}
