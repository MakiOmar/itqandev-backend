<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_expired_rows_from_the_database_store(): void
    {
        $now = now()->getTimestamp();
        DB::table('cache')->insert([
            ['key' => 'old-version', 'value' => 's:1:"a";', 'expiration' => $now - 3600],
            ['key' => 'just-expired', 'value' => 's:1:"b";', 'expiration' => $now],
            ['key' => 'live', 'value' => 's:1:"c";', 'expiration' => $now + 600],
        ]);

        $this->artisan('cache:prune-expired', ['--store' => 'database'])
            ->expectsOutputToContain('Deleted 2 expired cache row(s)')
            ->assertSuccessful();

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
    }

    public function test_other_stores_are_left_alone(): void
    {
        $this->artisan('cache:prune-expired', ['--store' => 'array'])
            ->expectsOutputToContain('not a database store')
            ->assertSuccessful();
    }

    public function test_it_runs_hourly(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(static fn ($event) => str_contains((string) $event->command, 'cache:prune-expired'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
