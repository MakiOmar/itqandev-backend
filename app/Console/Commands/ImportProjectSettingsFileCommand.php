<?php

namespace App\Console\Commands;

use App\Support\ProjectSettingsStore;
use Illuminate\Console\Command;

class ImportProjectSettingsFileCommand extends Command
{
    protected $signature = 'settings:import-file
                            {--force : Overwrite the database row even if it already has data}';

    protected $description = 'Import legacy storage/app/private/project-settings.json into the project_settings table';

    public function handle(): int
    {
        if (ProjectSettingsStore::readLegacyFile() === null) {
            $this->warn('No legacy project-settings.json found on the local disk.');

            return self::FAILURE;
        }

        $wrote = ProjectSettingsStore::importFromLegacyFile((bool) $this->option('force'));
        if (! $wrote) {
            $this->warn('Database already has settings. Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        $this->info('Imported project settings into the database.');

        return self::SUCCESS;
    }
}
