<?php

namespace Tests\Feature;

use App\Models\BuilderGlobal;
use App\Models\ProjectSetting;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReplaceUrlCommandTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = 'http://127.0.0.1:8000';

    private const TO = 'https://base.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the read-time cast a no-op so these tests see raw stored values.
        config(['app.url' => self::FROM]);
    }

    public function test_dry_run_reports_counts_without_writing(): void
    {
        ProjectSettingsStore::save(['site_logo' => self::FROM.'/storage/media/logo.webp']);

        $this->artisan('app:replace-url', ['from' => self::FROM, 'to' => self::TO, '--dry-run' => true])
            ->expectsOutputToContain('project_settings')
            ->expectsOutputToContain('Dry run: 1 row match(es)')
            ->assertSuccessful();

        $this->assertStringContainsString('127.0.0.1:8000', $this->rawPayload());
    }

    public function test_replaces_plain_and_json_escaped_urls(): void
    {
        ProjectSettingsStore::save([
            'site_logo' => self::FROM.'/storage/media/logo.webp',
            'html' => '<img src="'.self::FROM.'/storage/a.png">',
        ]);
        DB::table('cache')->insert([
            'key' => 'k',
            'value' => 's:41:"'.self::FROM.'/storage/media/x.png";',
            'expiration' => time() + 60,
        ]);

        $this->assertStringContainsString('http:\/\/127.0.0.1:8000', $this->rawPayload());

        $this->artisan('app:replace-url', ['from' => self::FROM, 'to' => self::TO])
            ->expectsOutputToContain('Updated 1 row(s)')
            ->assertSuccessful();

        $payload = ProjectSettingsStore::load();
        $this->assertSame(self::TO.'/storage/media/logo.webp', $payload['site_logo']);
        $this->assertSame('<img src="'.self::TO.'/storage/a.png">', $payload['html']);
        $this->assertStringNotContainsString('127.0.0.1', $this->rawPayload());

        // Serialized cache rows are skipped (length prefixes would break).
        $this->assertStringContainsString(self::FROM, (string) DB::table('cache')->value('value'));

        $this->artisan('app:replace-url', ['from' => self::FROM, 'to' => self::TO, '--dry-run' => true])
            ->expectsOutputToContain('No stored values contain')
            ->assertSuccessful();
    }

    public function test_table_option_limits_scope(): void
    {
        ProjectSettingsStore::save(['site_logo' => self::FROM.'/storage/logo.webp']);

        $this->artisan('app:replace-url', ['from' => self::FROM, 'to' => self::TO, '--table' => ['pages']])
            ->expectsOutputToContain('No stored values contain')
            ->assertSuccessful();

        $this->assertStringContainsString('127.0.0.1', $this->rawPayload());
    }

    public function test_rejects_identical_urls_and_excluded_tables(): void
    {
        $this->artisan('app:replace-url', ['from' => self::TO, 'to' => self::TO.'/'])->assertFailed();

        $this->artisan('app:replace-url', ['from' => self::FROM, 'to' => self::TO, '--table' => ['cache']])
            ->expectsOutputToContain('not available')
            ->assertFailed();
    }

    public function test_cast_maps_loopback_storage_urls_to_app_url_on_read(): void
    {
        BuilderGlobal::query()->create([
            'name' => 'Header',
            'slug' => 'header',
            'status' => 'published',
            'document' => [
                'icon' => ['url' => 'http://localhost:8000/storage/media/i.svg'],
                'link' => 'http://localhost:8000/admin',
            ],
        ]);

        config(['app.url' => self::TO]);
        $document = BuilderGlobal::query()->firstOrFail()->document;

        $this->assertSame(self::TO.'/storage/media/i.svg', $document['icon']['url']);
        $this->assertSame('http://localhost:8000/admin', $document['link']);
    }

    public function test_cast_is_noop_when_app_url_is_loopback(): void
    {
        ProjectSettingsStore::save(['site_logo' => self::FROM.'/storage/logo.webp']);

        $this->assertSame(self::FROM.'/storage/logo.webp', ProjectSettingsStore::load()['site_logo']);
    }

    private function rawPayload(): string
    {
        return (string) DB::table((new ProjectSetting())->getTable())->value('payload');
    }
}
