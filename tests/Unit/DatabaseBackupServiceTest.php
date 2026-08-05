<?php

namespace Tests\Unit;

use App\Services\System\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    private string $dbFile;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbFile = storage_path('framework/testing/backup-unit-'.uniqid('', true).'.sqlite');
        $this->backupDir = storage_path('framework/testing/backup-unit-dir-'.uniqid('', true));
        File::ensureDirectoryExists($this->backupDir);
        File::put($this->dbFile, '');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => $this->dbFile,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database-backup.path' => $this->backupDir,
            'database-backup.max_files' => 5,
            'database-backup.sqlite3_path' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        DB::connection()->getPdo()->exec(
            'CREATE TABLE sample_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name TEXT NOT NULL)'
        );
        DB::connection()->getPdo()->exec(
            "INSERT INTO sample_items (name) VALUES ('alpha'), ('beta')"
        );
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        if (isset($this->dbFile) && File::isFile($this->dbFile)) {
            File::delete($this->dbFile);
        }
        if (isset($this->backupDir) && File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }
        parent::tearDown();
    }

    public function test_sqlite_create_and_restore_round_trip(): void
    {
        $service = app(DatabaseBackupService::class);
        $created = $service->create();
        $this->assertFileExists($created['path']);
        $this->assertStringContainsString('sample_items', File::get($created['path']));

        DB::connection()->getPdo()->exec('DELETE FROM sample_items');
        $this->assertSame(0, (int) DB::connection()->getPdo()->query('SELECT COUNT(*) FROM sample_items')->fetchColumn());

        $service->restoreFromStoredFile($created['filename']);

        $count = (int) DB::connection()->getPdo()->query('SELECT COUNT(*) FROM sample_items')->fetchColumn();
        $this->assertSame(2, $count);
    }
}
