<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/** `cache:prune-expired` filters on `expiration`; without an index every run scans the whole table. */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->table();
        if ($this->schema()->hasTable($table) && ! $this->schema()->hasIndex($table, ['expiration'])) {
            $this->schema()->table($table, static fn (Blueprint $blueprint) => $blueprint->index('expiration'));
        }
    }

    public function down(): void
    {
        $table = $this->table();
        if ($this->schema()->hasTable($table) && $this->schema()->hasIndex($table, ['expiration'])) {
            $this->schema()->table($table, static fn (Blueprint $blueprint) => $blueprint->dropIndex(['expiration']));
        }
    }

    private function table(): string
    {
        return (string) config('cache.stores.database.table', 'cache');
    }

    private function schema(): Builder
    {
        return Schema::connection(config('cache.stores.database.connection'));
    }
};
