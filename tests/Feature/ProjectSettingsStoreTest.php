<?php

namespace Tests\Feature;

use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectSettingsStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_and_load_round_trip(): void
    {
        ProjectSettingsStore::save(['site_name' => 'ITQANDEV', 'homepage_builder' => ['sections' => []]]);

        $loaded = ProjectSettingsStore::load();
        $this->assertSame('ITQANDEV', $loaded['site_name']);
        $this->assertSame(['sections' => []], $loaded['homepage_builder']);
    }

    public function test_load_runs_no_queries_after_first_read_in_request_or_across_requests(): void
    {
        ProjectSettingsStore::save(['site_name' => 'Memo']);
        ProjectSettingsStore::load();

        DB::enableQueryLog();
        ProjectSettingsStore::load();
        $this->app->forgetScopedInstances();
        $this->assertSame('Memo', ProjectSettingsStore::load()['site_name']);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_save_replaces_memoized_and_cached_payload(): void
    {
        ProjectSettingsStore::save(['site_name' => 'Before']);
        $this->assertSame('Before', ProjectSettingsStore::load()['site_name']);

        ProjectSettingsStore::merge(['site_name' => 'After']);

        $this->assertSame('After', ProjectSettingsStore::load()['site_name']);
        $this->app->forgetScopedInstances();
        $this->assertSame('After', ProjectSettingsStore::load()['site_name']);
    }

    public function test_flush_picks_up_raw_sql_writes(): void
    {
        ProjectSettingsStore::save(['site_name' => 'Old']);
        ProjectSettingsStore::load();

        DB::table('project_settings')->where('id', 1)->update(['payload' => json_encode(['site_name' => 'Raw'])]);
        $this->assertSame('Old', ProjectSettingsStore::load()['site_name']);

        ProjectSettingsStore::flush();
        $this->assertSame('Raw', ProjectSettingsStore::load()['site_name']);
    }

    public function test_import_from_legacy_file_rewrites_local_app_url(): void
    {
        Storage::fake('local');
        config(['app.url' => 'https://base.itqandev.com']);
        Storage::disk('local')->put('project-settings.json', json_encode([
            'logo' => 'http://127.0.0.1:8000/storage/logo.png',
            'site_name' => 'From File',
        ]));

        $this->assertTrue(ProjectSettingsStore::importFromLegacyFile());
        $loaded = ProjectSettingsStore::load();
        $this->assertSame('From File', $loaded['site_name']);
        $this->assertSame('https://base.itqandev.com/storage/logo.png', $loaded['logo']);
    }

    public function test_import_skips_when_row_already_has_data_unless_forced(): void
    {
        Storage::fake('local');
        ProjectSettingsStore::save(['site_name' => 'Existing']);
        Storage::disk('local')->put('project-settings.json', json_encode(['site_name' => 'File']));

        $this->assertFalse(ProjectSettingsStore::importFromLegacyFile());
        $this->assertSame('Existing', ProjectSettingsStore::load()['site_name']);

        $this->assertTrue(ProjectSettingsStore::importFromLegacyFile(true));
        $this->assertSame('File', ProjectSettingsStore::load()['site_name']);
    }
}
