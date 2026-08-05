<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\System\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class DatabaseBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->backupDir = storage_path('framework/testing/backups-restore-'.uniqid('', true));
        File::ensureDirectoryExists($this->backupDir);
        config([
            'database-backup.path' => $this->backupDir,
            'database-backup.max_files' => 5,
            'database-backup.confirm_phrase' => 'CONFIRM',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->backupDir) && File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }
        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        return $user;
    }

    public function test_admin_can_restore_from_stored_backup(): void
    {
        $admin = $this->admin();

        $mock = Mockery::mock(DatabaseBackupService::class);
        $mock->shouldReceive('confirmPhrase')->andReturn('CONFIRM');
        $mock->shouldReceive('restoreFromStoredFile')->once()->with('demo-backup.sql');
        $this->app->instance(DatabaseBackupService::class, $mock);

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/backups/restore', [
                'confirmation' => 'CONFIRM',
                'filename' => 'demo-backup.sql',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Database restored successfully.');
    }

    public function test_admin_can_restore_from_uploaded_sql(): void
    {
        $admin = $this->admin();

        $mock = Mockery::mock(DatabaseBackupService::class);
        $mock->shouldReceive('confirmPhrase')->andReturn('CONFIRM');
        $mock->shouldReceive('directory')->andReturn($this->backupDir);
        $mock->shouldReceive('restoreFromPath')->once()->with(Mockery::type('string'));
        $this->app->instance(DatabaseBackupService::class, $mock);

        $upload = UploadedFile::fake()->createWithContent('restore-upload.sql', "-- dump\nSELECT 1;\n");

        $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/system/backups/restore', [
                'confirmation' => 'CONFIRM',
                'file' => $upload,
            ])
            ->assertOk();
    }
}
