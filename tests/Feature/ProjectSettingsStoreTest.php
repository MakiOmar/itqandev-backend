<?php

namespace Tests\Feature;

use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
