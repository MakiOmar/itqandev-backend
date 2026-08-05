<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\System\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->backupDir = storage_path('framework/testing/backups-schedule-'.uniqid('', true));
        File::ensureDirectoryExists($this->backupDir);
        config([
            'database-backup.path' => $this->backupDir,
            'database-backup.max_files' => 5,
            'database-backup.confirm_phrase' => 'CONFIRM',
            'database-backup.schedule_interval' => 'daily',
            'database-backup.schedule_at' => '03:30',
            'database-backup.schedule_weekly_day' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->backupDir) && File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }
        parent::tearDown();
    }

    public function test_backup_database_command_creates_dump_with_force(): void
    {
        config(['database-backup.schedule_interval' => 'disabled']);

        $exit = Artisan::call('backup:database', ['--force' => true]);
        $this->assertSame(0, $exit);
        $this->assertNotEmpty(File::files($this->backupDir));
    }

    public function test_backup_database_command_skips_when_disabled_without_force(): void
    {
        config(['database-backup.schedule_interval' => 'disabled']);
        File::cleanDirectory($this->backupDir);

        $exit = Artisan::call('backup:database');
        $this->assertSame(0, $exit);
        $this->assertSame([], File::files($this->backupDir));
    }

    public function test_list_meta_includes_schedule(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
        ])
            ->getJson('/api/v1/system/backups')
            ->assertOk()
            ->assertJsonPath('meta.schedule.interval', 'daily')
            ->assertJsonPath('meta.schedule.at', '03:30')
            ->assertJsonPath('meta.schedule.enabled', true);
    }

    public function test_schedule_meta_normalizes_aliases(): void
    {
        config(['database-backup.schedule_interval' => 'every6hours']);
        $meta = app(DatabaseBackupService::class)->scheduleMeta();
        $this->assertSame('every_six_hours', $meta['interval']);
        $this->assertTrue($meta['enabled']);
    }
}
