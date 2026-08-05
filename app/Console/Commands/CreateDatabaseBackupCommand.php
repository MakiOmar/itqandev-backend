<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:database {--force : Run even when automatic backups are disabled}';

    protected $description = 'Create a SQL database backup dump (scheduled or manual)';

    public function handle(DatabaseBackupService $backups): int
    {
        $interval = strtolower(trim((string) config('database-backup.schedule_interval', 'disabled')));
        if (! $this->option('force') && in_array($interval, ['', 'disabled', 'off', 'false', '0'], true)) {
            $this->info('Automatic database backups are disabled (DB_BACKUP_INTERVAL=disabled).');

            return self::SUCCESS;
        }

        try {
            $created = $backups->create();
        } catch (Throwable $e) {
            $this->error('Database backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created: '.$created['filename'].' ('.$created['size'].' bytes)');

        return self::SUCCESS;
    }
}
