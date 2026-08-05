<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->backupDir = storage_path('framework/testing/backups-'.uniqid('', true));
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

    public function test_company_cannot_list_backups(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/system/backups')
            ->assertForbidden();
    }

    public function test_admin_can_create_list_download_and_delete_backup(): void
    {
        $admin = $this->admin();

        $create = $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/backups')
            ->assertCreated()
            ->json('data.filename');

        $this->assertIsString($create);
        $this->assertTrue(File::exists($this->backupDir.DIRECTORY_SEPARATOR.$create));

        $list = $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/v1/system/backups')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $this->assertSame($create, $list[0]['filename']);

        $this->withHeaders($this->bearerHeaders($admin))
            ->get('/api/v1/system/backups/'.$create)
            ->assertOk();

        $this->withHeaders($this->bearerHeaders($admin))
            ->deleteJson('/api/v1/system/backups/'.$create)
            ->assertOk();

        $this->assertFalse(File::exists($this->backupDir.DIRECTORY_SEPARATOR.$create));
    }

    public function test_restore_requires_confirmation_phrase(): void
    {
        $admin = $this->admin();

        $filename = $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/backups')
            ->assertCreated()
            ->json('data.filename');

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/backups/restore', [
                'confirmation' => 'nope',
                'filename' => $filename,
            ])
            ->assertStatus(422);
    }
}
